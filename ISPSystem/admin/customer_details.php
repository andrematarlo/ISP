<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get customer ID from URL
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$customer_id) {
    header('Location: customers.php');
    exit();
}

// Fetch customer details
$customer_sql = "SELECT c.*, u.email, u.username 
                 FROM customers c
                 JOIN users u ON c.user_id = u.id
                 WHERE c.id = ?";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param('i', $customer_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer = $customer_result->fetch_assoc();

if (!$customer) {
    header('Location: customers.php');
    exit();
}

// Fetch active subscription
$subscription_sql = "SELECT s.*, p.name as plan_name, p.speed, p.price
                     FROM subscriptions s
                     JOIN plans p ON s.plan_id = p.id
                     WHERE s.customer_id = ? AND s.status = 'active'
                     ORDER BY s.start_date DESC
                     LIMIT 1";
$subscription_stmt = $conn->prepare($subscription_sql);
$subscription_stmt->bind_param('i', $customer_id);
$subscription_stmt->execute();
$subscription_result = $subscription_stmt->get_result();
$subscription = $subscription_result->fetch_assoc();

// Fetch billing history
$bills_sql = "SELECT b.*, p.name as plan_name
              FROM bills b
              JOIN subscriptions s ON b.subscription_id = s.id
              JOIN plans p ON s.plan_id = p.id
              WHERE s.customer_id = ?
              ORDER BY b.bill_date DESC
              LIMIT 10";
$bills_stmt = $conn->prepare($bills_sql);
$bills_stmt->bind_param('i', $customer_id);
$bills_stmt->execute();
$bills_result = $bills_stmt->get_result();

require_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Customer Profile</h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-user-circle fa-5x text-muted"></i>
                    </div>
                    <h4><?php echo htmlspecialchars($customer['full_name']); ?></h4>
                    <p class="text-muted mb-1"><?php echo htmlspecialchars($customer['email']); ?></p>
                    <p class="text-muted">
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($customer['phone']); ?>
                    </p>
                </div>
            </div>

            <?php if ($subscription): ?>
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Active Subscription</h5>
                </div>
                <div class="card-body">
                    <h6 class="card-title"><?php echo htmlspecialchars($subscription['plan_name']); ?></h6>
                    <p class="card-text">
                        <strong>Speed:</strong> <?php echo htmlspecialchars($subscription['speed']); ?><br>
                        <strong>Price:</strong> ₱<?php echo number_format($subscription['price'], 2); ?><br>
                        <strong>Start Date:</strong> <?php echo date('F j, Y', strtotime($subscription['start_date'])); ?>
                    </p>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelSubscriptionModal">
                        <i class="fas fa-times-circle"></i> Cancel Subscription
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Customer Details</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">Username</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($customer['username']); ?></dd>

                        <dt class="col-sm-3">Full Name</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($customer['full_name']); ?></dd>

                        <dt class="col-sm-3">Email</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($customer['email']); ?></dd>

                        <dt class="col-sm-3">Phone</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($customer['phone']); ?></dd>

                        <dt class="col-sm-3">Address</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($customer['address']); ?></dd>

                        <dt class="col-sm-3">Status</dt>
                        <dd class="col-sm-9">
                            <span class="badge 
                                <?php echo $customer['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo ucfirst($customer['status']); ?>
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Billing History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Bill Date</th>
                                    <th>Plan</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($bill = $bills_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($bill['bill_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($bill['plan_name']); ?></td>
                                        <td>₱<?php echo number_format($bill['amount'], 2); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                echo $bill['status'] === 'paid' ? 'bg-success' : 
                                                    ($bill['status'] === 'unpaid' ? 'bg-danger' : 'bg-warning'); 
                                                ?>">
                                                <?php echo ucfirst($bill['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_bill.php?id=<?php echo $bill['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
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

<!-- Cancel Subscription Modal -->
<?php if ($subscription): ?>
<div class="modal fade" id="cancelSubscriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Cancel Subscription</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="cancelSubscriptionForm">
                    <input type="hidden" name="subscription_id" value="<?php echo $subscription['id']; ?>">
                    
                    <div class="mb-3">
                        <label for="cancel_reason" class="form-label">Reason for Cancellation</label>
                        <select class="form-select" id="cancel_reason" name="cancel_reason" required>
                            <option value="">Select a reason</option>
                            <option value="Customer Request">Customer Request</option>
                            <option value="Payment Issues">Payment Issues</option>
                            <option value="Service Dissatisfaction">Service Dissatisfaction</option>
                            <option value="Moving">Moving</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div id="otherReasonContainer" style="display:none;" class="mb-3">
                        <label for="other_reason" class="form-label">Please specify the reason</label>
                        <textarea class="form-control" id="other_reason" name="other_reason" rows="3"></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This will immediately cancel the customer's active subscription.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="confirmCancelSubscription">
                    <i class="fas fa-times-circle"></i> Confirm Cancellation
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle reason selection
    const cancelReasonSelect = document.getElementById('cancel_reason');
    const otherReasonContainer = document.getElementById('otherReasonContainer');
    
    cancelReasonSelect.addEventListener('change', function() {
        otherReasonContainer.style.display = 
            this.value === 'Other' ? 'block' : 'none';
    });

    // Confirm subscription cancellation
    document.getElementById('confirmCancelSubscription').addEventListener('click', function() {
        const form = document.getElementById('cancelSubscriptionForm');
        const formData = new FormData(form);
        
        // Validate form
        if (cancelReasonSelect.value === '') {
            alert('Please select a cancellation reason');
            return;
        }
        
        if (cancelReasonSelect.value === 'Other' && 
            document.getElementById('other_reason').value.trim() === '') {
            alert('Please specify the reason for cancellation');
            return;
        }

        // Send cancellation request
        fetch('cancel_subscription.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred');
        });
    });
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>
