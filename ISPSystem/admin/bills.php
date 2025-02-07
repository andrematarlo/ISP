<?php
function processPayment($conn, $payment_id, $bill_id, $action, $admin_notes = '') {
    try {
        $conn->begin_transaction();

        // Retrieve payment details
        $sql = "SELECT * FROM payments WHERE id = ? AND bill_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $payment_id, $bill_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();

        if (!$payment) {
            throw new Exception("Payment not found.");
        }

        // Update payment status
        $new_status = $action === 'accept' ? 'completed' : 'failed';
        $sql = "UPDATE payments SET 
                status = ?, 
                admin_notes = ?, 
                processed_at = NOW() 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssi', $new_status, $admin_notes, $payment_id);
        $stmt->execute();

        // If payment is accepted, update bill status
        if ($action === 'accept') {
            // Update bill status to paid
            $sql = "UPDATE bills SET 
                    status = 'paid', 
                    paid_at = NOW() 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $bill_id);
            $stmt->execute();
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Payment processing error: " . $e->getMessage());
        return false;
    }
}

session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle bill status update
if (isset($_POST['update_status'])) {
    $bill_id = (int)$_POST['bill_id'];
    $new_status = $_POST['status'];
    
    $sql = "UPDATE bills SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $new_status, $bill_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Bill status updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating bill status.";
    }
    header('Location: bills.php');
    exit();
}

// Get all bills with customer and subscription details
$sql = "SELECT b.*, 
               c.full_name as customer_name, 
               p.name as plan_name,
               p.price as plan_price,
               s.start_date,
               s.end_date
        FROM bills b
        JOIN subscriptions s ON b.subscription_id = s.id
        JOIN customers c ON s.customer_id = c.id
        JOIN plans p ON s.plan_id = p.id
        ORDER BY b.due_date ASC";
$result = $conn->query($sql);

// Get billing statistics
$stats_sql = "SELECT 
    COUNT(*) as total_bills,
    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_bills,
    SUM(CASE WHEN status = 'unpaid' AND due_date < CURRENT_DATE() THEN 1 ELSE 0 END) as overdue_bills,
    SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
    SUM(CASE WHEN status = 'unpaid' THEN amount ELSE 0 END) as total_unpaid
FROM bills";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

require_once '../includes/admin_header.php';
?>

<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Bills Management</h2>
        <div class="btn-group">
            <a href="generate_bills.php" class="btn btn-primary">
                <i class="fas fa-file-invoice-dollar"></i> Generate Bills
            </a>
            <a href="billing_reports.php" class="btn btn-info text-white">
                <i class="fas fa-chart-bar"></i> Billing Reports
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Bills</h6>
                    <h3 class="mb-0"><?php echo number_format($stats['total_bills']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Paid Bills</h6>
                    <h3 class="mb-0"><?php echo number_format($stats['paid_bills']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title">Overdue Bills</h6>
                    <h3 class="mb-0"><?php echo number_format($stats['overdue_bills']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Paid Amount</h6>
                    <h3 class="mb-0">₱<?php echo number_format($stats['total_paid'], 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Unpaid Amount</h6>
                    <h3 class="mb-0">₱<?php echo number_format($stats['total_unpaid'], 2); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Bills Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="billsTable">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Customer</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Bill Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($bill = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $bill['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($bill['customer_name']); ?><br>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($bill['start_date'])); ?> - 
                                        <?php echo date('M j, Y', strtotime($bill['end_date'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($bill['plan_name']); ?><br>
                                    <small class="text-muted">₱<?php echo number_format($bill['plan_price'], 2); ?>/month</small>
                                </td>
                                <td>₱<?php echo number_format($bill['amount'], 2); ?></td>
                                <td><?php echo date('M j, Y', strtotime($bill['bill_date'])); ?></td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($bill['due_date'])); ?>
                                    <?php if ($bill['status'] === 'unpaid' && strtotime($bill['due_date']) < time()): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $bill['status'] === 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($bill['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_bill.php?id=<?php echo $bill['id']; ?>" 
                                           class="btn btn-sm btn-info text-white">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($bill['status'] === 'unpaid'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-success mark-as-paid"
                                                    data-id="<?php echo $bill['id']; ?>">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="print_bill.php?id=<?php echo $bill['id']; ?>" 
                                           class="btn btn-sm btn-secondary" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Mark as Paid Modal -->
<div class="modal fade" id="markAsPaidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark Bill as Paid</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="markAsPaidForm" action="process_payment.php" method="POST">
                    <input type="hidden" name="bill_id" id="paid_bill_id">
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="gcash">GCash</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="payment_reference" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="payment_reference" name="payment_reference">
                        <small class="text-muted">Required for Bank Transfer and GCash payments</small>
                    </div>
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-success">Confirm Payment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add DataTables CSS -->
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<!-- Add DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#billsTable').DataTable({
            order: [[5, 'asc']]  // Sort by due date
        });

        // Handle mark as paid button
        $('.mark-as-paid').click(function() {
            const billId = $(this).data('id');
            $('#paid_bill_id').val(billId);
            $('#markAsPaidModal').modal('show');
        });

        // Show/hide reference number field based on payment method
        $('#payment_method').change(function() {
            const method = $(this).val();
            if (method === 'bank_transfer' || method === 'gcash') {
                $('#payment_reference').prop('required', true);
                $('#payment_reference').closest('.mb-3').show();
            } else {
                $('#payment_reference').prop('required', false);
                $('#payment_reference').closest('.mb-3').hide();
            }
        });

        // Handle payment form submission
        $('#markAsPaidForm').submit(function(e) {
            e.preventDefault();
            $.post($(this).attr('action'), $(this).serialize())
                .done(function() {
                    location.reload();
                })
                .fail(function(response) {
                    alert(response.responseText || 'Error processing payment');
                });
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>
