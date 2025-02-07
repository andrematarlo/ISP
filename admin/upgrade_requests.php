<?php
session_start();
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

// Handle upgrade request status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    $admin_notes = filter_input(INPUT_POST, 'admin_notes', FILTER_SANITIZE_STRING);

    if ($request_id && in_array($action, ['approve', 'reject', 'schedule', 'more_info'])) {
        // Begin transaction
        $conn->begin_transaction();

        try {
            // Fetch request details first
            $request_stmt = $conn->prepare("SELECT * FROM upgrade_requests WHERE id = ?");
            $request_stmt->bind_param('i', $request_id);
            $request_stmt->execute();
            $request_details = $request_stmt->get_result()->fetch_assoc();

            if (!$request_details) {
                throw new Exception("Upgrade request not found.");
            }

            // Determine status and action
            switch ($action) {
                case 'approve':
                    $status = 'approved';
                    $ticket_status = 'in_progress';
                    
                    // First, log current active subscription
                    $current_sub_stmt = $conn->prepare("SELECT * FROM subscriptions 
                        WHERE customer_id = ? AND status = 'active' 
                        ORDER BY start_date DESC LIMIT 1");
                    $current_sub_stmt->bind_param('i', $request_details['customer_id']);
                    $current_sub_stmt->execute();
                    $current_sub = $current_sub_stmt->get_result()->fetch_assoc();

                    error_log(sprintf(
                        "Upgrade Request Approval - Current Subscription: %s", 
                        json_encode($current_sub ?? 'No active subscription')
                    ));

                    // Prepare to update subscription
                    $update_sub_stmt = $conn->prepare("
                        UPDATE subscriptions 
                        SET 
                            status = 'inactive', 
                            end_date = CURRENT_DATE 
                        WHERE customer_id = ? AND status = 'active'
                    ");
                    $update_sub_stmt->bind_param('i', $request_details['customer_id']);
                    $update_sub_stmt->execute();

                    error_log(sprintf(
                        "Deactivated existing subscriptions - Rows affected: %d, Error: %s", 
                        $update_sub_stmt->affected_rows,
                        $update_sub_stmt->error
                    ));

                    // Insert new active subscription
                    $new_sub_stmt = $conn->prepare("
                        INSERT INTO subscriptions 
                        (customer_id, plan_id, start_date, end_date, status) 
                        VALUES (?, ?, CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), 'active')
                    ");
                    $new_sub_stmt->bind_param('ii', 
                        $request_details['customer_id'], 
                        $request_details['plan_id']
                    );
                    $new_sub_insert_result = $new_sub_stmt->execute();

                    error_log(sprintf(
                        "New Subscription Insert - Success: %s, Error: %s", 
                        $new_sub_insert_result ? 'Yes' : 'No',
                        $new_sub_stmt->error
                    ));

                    if (!$new_sub_insert_result) {
                        throw new Exception("Failed to create new subscription: " . $new_sub_stmt->error);
                    }
                    break;

                case 'reject':
                    $status = 'rejected';
                    $ticket_status = 'closed';
                    break;

                case 'schedule':
                    $status = 'scheduled';
                    $ticket_status = 'in_progress';
                    break;

                case 'more_info':
                    $status = 'pending';
                    $ticket_status = 'open';
                    break;
            }

            // Update upgrade request
            $stmt = $conn->prepare("UPDATE upgrade_requests 
                SET status = ?, 
                    reason = CONCAT(reason, '\n\nAdmin Notes: ', ?), 
                    updated_at = NOW() 
                WHERE id = ?");
            $stmt->bind_param('ssi', $status, $admin_notes, $request_id);
            $stmt->execute();

            // Update related support ticket
            $ticket_stmt = $conn->prepare("UPDATE support_tickets 
                SET status = ? 
                WHERE customer_id = ? AND subject = 'Plan Upgrade Request' 
                ORDER BY created_at DESC LIMIT 1");
            $ticket_stmt->bind_param('si', $ticket_status, $request_details['customer_id']);
            $ticket_stmt->execute();

            $conn->commit();
            $_SESSION['success'] = "Upgrade request successfully processed.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error processing request: " . $e->getMessage();
        }

        header('Location: upgrade_requests.php');
        exit();
    }
}

// Fetch upgrade requests with customer and plan details
$query = "SELECT ur.*, c.full_name AS customer_name, p.name AS plan_name, p.speed, p.price 
          FROM upgrade_requests ur
          JOIN customers c ON ur.customer_id = c.id
          JOIN plans p ON ur.plan_id = p.id
          ORDER BY ur.created_at DESC";
$result = $conn->query($query);

require_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Upgrade Requests</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Current Plan</th>
                                    <th>Requested Plan</th>
                                    <th>Reason</th>
                                    <th>Preferred Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($request = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['customer_name']); ?></td>
                                        <td>
                                            <?php 
                                            // Fetch current plan
                                            $current_plan_stmt = $conn->prepare("SELECT p.name FROM plans p 
                                                JOIN subscriptions s ON p.id = s.plan_id 
                                                WHERE s.customer_id = ? AND s.status = 'active' 
                                                ORDER BY s.start_date DESC LIMIT 1");
                                            $current_plan_stmt->bind_param('i', $request['customer_id']);
                                            $current_plan_stmt->execute();
                                            $current_plan = $current_plan_stmt->get_result()->fetch_assoc();
                                            echo htmlspecialchars($current_plan ? $current_plan['name'] : 'No Active Plan'); 
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['plan_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['reason']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($request['preferred_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $request['status'] === 'pending' ? 'warning' : 
                                                    ($request['status'] === 'approved' ? 'success' : 
                                                        ($request['status'] === 'scheduled' ? 'info' : 'danger')); 
                                            ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($request['status'] === 'pending'): ?>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a href="#" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $request['id']; ?>">
                                                            <i class="fas fa-check text-success me-2"></i>Approve
                                                        </a>
                                                        <a href="#" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $request['id']; ?>">
                                                            <i class="fas fa-times text-danger me-2"></i>Reject
                                                        </a>
                                                        <a href="#" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#scheduleModal<?php echo $request['id']; ?>">
                                                            <i class="fas fa-calendar text-info me-2"></i>Schedule
                                                        </a>
                                                        <a href="#" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#moreInfoModal<?php echo $request['id']; ?>">
                                                            <i class="fas fa-question text-warning me-2"></i>More Info
                                                        </a>
                                                    </div>
                                                </div>

                                                <!-- Approve Modal -->
                                                <div class="modal fade" id="approveModal<?php echo $request['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Approve Upgrade Request</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                                    <input type="hidden" name="action" value="approve">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Admin Notes</label>
                                                                        <textarea class="form-control" name="admin_notes" rows="3" placeholder="Optional notes for the upgrade request"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-success">Confirm Approval</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Reject Modal -->
                                                <div class="modal fade" id="rejectModal<?php echo $request['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Reject Upgrade Request</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                                    <input type="hidden" name="action" value="reject">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Rejection Reason</label>
                                                                        <textarea class="form-control" name="admin_notes" rows="3" required placeholder="Please provide a detailed reason for rejecting this upgrade request"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Schedule Modal -->
                                                <div class="modal fade" id="scheduleModal<?php echo $request['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Schedule Upgrade Request</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                                    <input type="hidden" name="action" value="schedule">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Scheduling Notes</label>
                                                                        <textarea class="form-control" name="admin_notes" rows="3" placeholder="Optional notes about the scheduled upgrade (e.g., expected implementation date, additional considerations)"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-info">Confirm Scheduling</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- More Info Modal -->
                                                <div class="modal fade" id="moreInfoModal<?php echo $request['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Request More Information</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                                    <input type="hidden" name="action" value="more_info">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Additional Information Request</label>
                                                                        <textarea class="form-control" name="admin_notes" rows="3" required placeholder="Specify what additional information is needed from the customer to process this upgrade request"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-warning">Confirm Request</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">No actions</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Debug dropdown and modal interactions
    console.log('Upgrade Requests Page Loaded');

    // Ensure Bootstrap dropdown and modal functionality
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });

    // Manually trigger dropdown if needed
    document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            var dropdown = bootstrap.Dropdown.getInstance(this);
            if (dropdown) {
                dropdown.toggle();
            }
        });
    });

    // Ensure modals work
    var modalElements = document.querySelectorAll('.modal');
    modalElements.forEach(function(modalEl) {
        var modal = new bootstrap.Modal(modalEl, {
            keyboard: true,
            backdrop: 'static'
        });

        // Debug modal events
        modalEl.addEventListener('show.bs.modal', function (event) {
            console.log('Modal about to be shown:', event.target.id);
        });

        modalEl.addEventListener('shown.bs.modal', function (event) {
            console.log('Modal shown:', event.target.id);
        });
    });

    // Add click event listeners to dropdown items
    document.querySelectorAll('.dropdown-item').forEach(function(item) {
        item.addEventListener('click', function(e) {
            console.log('Dropdown item clicked:', this.textContent);
            
            // Find and trigger the corresponding modal
            var targetModalId = this.getAttribute('data-bs-target');
            if (targetModalId) {
                var targetModal = document.querySelector(targetModalId);
                if (targetModal) {
                    var modalInstance = new bootstrap.Modal(targetModal);
                    modalInstance.show();
                }
            }
        });
    });

    // Prevent form submission if validation fails
    var forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>
</body>
</html>
