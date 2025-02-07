<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin or customer
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'customer'])) {
    header('Location: ../login.php');
    exit();
}

// Get bill ID from URL
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$bill_id) {
    header('Location: bills.php');
    exit();
}

// Handle payment acceptance/rejection (admin only)
if ($_SESSION['role'] === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_action'])) {
    $payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
    $action = isset($_POST['payment_action']) ? $_POST['payment_action'] : '';
    $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';

    // Validate inputs
    if (!$payment_id || !in_array($action, ['accept', 'reject'])) {
        $_SESSION['error'] = "Invalid payment processing parameters.";
        header("Location: view_bill.php?id=$bill_id");
        exit();
    }

    // Verify payment exists and is pending
    $check_sql = "SELECT p.*, b.status as bill_status 
                 FROM payments p 
                 JOIN bills b ON p.bill_id = b.id 
                 WHERE p.id = ? AND p.bill_id = ? AND p.status = 'pending'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('ii', $payment_id, $bill_id);
    $check_stmt->execute();
    $payment = $check_stmt->get_result()->fetch_assoc();

    if (!$payment) {
        $_SESSION['error'] = "Payment not found or already processed.";
        header("Location: view_bill.php?id=$bill_id");
        exit();
    }

    if ($payment['bill_status'] !== 'unpaid') {
        $_SESSION['error'] = "Bill is already paid.";
        header("Location: view_bill.php?id=$bill_id");
        exit();
    }

    // Process the payment
    if ($action === 'reject' && empty($admin_notes)) {
        $_SESSION['error'] = "Please provide a reason for rejection.";
        header("Location: view_bill.php?id=$bill_id");
        exit();
    }

    $conn->begin_transaction();
    try {
        // Update payment status
        $new_status = ($action === 'accept') ? 'completed' : 'failed';
        $update_payment_sql = "UPDATE payments 
                             SET status = ?, 
                                 notes = ?
                             WHERE id = ? AND status = 'pending'";
        $update_stmt = $conn->prepare($update_payment_sql);
        $update_stmt->bind_param('ssi', $new_status, $admin_notes, $payment_id);
        $update_stmt->execute();

        if ($update_stmt->affected_rows === 0) {
            throw new Exception("Failed to update payment status.");
        }

        // If payment is accepted, update bill status
        if ($action === 'accept') {
            $update_bill_sql = "UPDATE bills 
                               SET status = 'paid', 
                                   paid_at = NOW() 
                               WHERE id = ? AND status = 'unpaid'";
            $update_bill_stmt = $conn->prepare($update_bill_sql);
            $update_bill_stmt->bind_param('i', $bill_id);
            $update_bill_stmt->execute();

            if ($update_bill_stmt->affected_rows === 0) {
                throw new Exception("Failed to update bill status.");
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Payment has been " . ($action === 'accept' ? 'accepted' : 'rejected') . " successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error processing payment: " . $e->getMessage();
        error_log("Payment processing error: " . $e->getMessage());
    }

    header("Location: view_bill.php?id=$bill_id");
    exit();
}

// If customer, ensure they can only view their own bill
if ($_SESSION['role'] === 'customer') {
    $customer_check_sql = "SELECT 1 FROM bills b
                           JOIN subscriptions s ON b.subscription_id = s.id
                           JOIN customers c ON s.customer_id = c.id
                           WHERE b.id = ? AND c.user_id = ?";
    $customer_check_stmt = $conn->prepare($customer_check_sql);
    $customer_check_stmt->bind_param('ii', $bill_id, $_SESSION['user_id']);
    $customer_check_stmt->execute();
    $customer_bill_exists = $customer_check_stmt->get_result()->fetch_assoc();
    
    if (!$customer_bill_exists) {
        header('Location: ../dashboard.php');
        exit();
    }
}

// Get bill details
$sql = "SELECT b.*, c.full_name, c.phone, c.address, p.name as plan_name, p.speed,
        s.start_date as subscription_start, s.end_date as subscription_end
        FROM bills b 
        JOIN subscriptions s ON b.subscription_id = s.id 
        JOIN customers c ON s.customer_id = c.id 
        JOIN plans p ON s.plan_id = p.id 
        WHERE b.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $bill_id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();

if (!$bill) {
    header('Location: bills.php');
    exit();
}

// Get payment history with admin action details
$sql = "SELECT p.*, 
               CASE 
                   WHEN p.status = 'completed' THEN 'Accepted'
                   WHEN p.status = 'failed' THEN 'Rejected'
                   ELSE 'Pending'
               END as action_status
        FROM payments p
        WHERE p.bill_id = ? 
        ORDER BY p.payment_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $bill_id);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get pending payments
$sql = "SELECT * 
        FROM payments 
        WHERE bill_id = ? AND status = 'pending'
        ORDER BY payment_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $bill_id);
$stmt->execute();
$pending_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get success/error messages
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

// Include header after all potential redirects
require_once '../includes/admin_header.php';

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">Bill Details #<?php echo $bill_id; ?></h2>
                    <div class="btn-group">
                        <a href="print_bill.php?id=<?php echo $bill_id; ?>" class="btn btn-secondary" target="_blank">
                            <i class="fas fa-print"></i> Print Bill
                        </a>
                        <a href="bills.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Bills
                        </a>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card-body">
                    <div class="row">
                        <!-- Bill Information -->
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Bill Information</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Status:</strong> 
                                        <span class="badge bg-<?php 
                                            echo $bill['status'] === 'paid' ? 'success' : 
                                                ($bill['status'] === 'unpaid' && strtotime($bill['due_date']) < time() ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo ucfirst($bill['status']); ?>
                                        </span>
                                    </p>
                                    <p><strong>Total Amount:</strong> ₱<?php echo number_format($bill['amount'], 2); ?></p>
                                    <p><strong>Bill Date:</strong> <?php echo date('M d, Y', strtotime($bill['bill_date'])); ?></p>
                                    <p><strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($bill['due_date'])); ?></p>
                                    <?php if ($bill['status'] === 'paid'): ?>
                                    <p><strong>Paid Date:</strong> <?php echo date('M d, Y', strtotime($bill['paid_at'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Information -->
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Customer Details</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($bill['full_name']); ?></p>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($bill['phone']); ?></p>
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($bill['address']); ?></p>
                                    <p><strong>Plan:</strong> <?php echo htmlspecialchars($bill['plan_name']); ?> (<?php echo htmlspecialchars($bill['speed']); ?> Mbps)</p>
                                    <p><strong>Subscription Period:</strong> 
                                        <?php echo date('M d, Y', strtotime($bill['subscription_start'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($bill['subscription_end'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Payment History -->
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Payment History</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($payments)): ?>
                                        <p class="text-muted">No payment history available.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Method</th>
                                                        <th>Amount</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($payments as $payment): ?>
                                                    <tr>
                                                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                                                        <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $payment['status'] === 'completed' ? 'success' : 
                                                                     ($payment['status'] === 'pending' ? 'warning' : 'danger'); 
                                                            ?>">
                                                                <?php echo ucfirst($payment['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Payments -->
                    <?php if (!empty($pending_payments) && $_SESSION['role'] === 'admin'): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Pending Payments</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Method</th>
                                                    <th>Reference</th>
                                                    <th>Amount</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_payments as $payment): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($payment['payment_date'])); ?></td>
                                                    <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                                    <td><?php echo htmlspecialchars($payment['reference_number'] ?? 'N/A'); ?></td>
                                                    <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                                    <td>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                            <input type="hidden" name="payment_action" value="accept">
                                                            <button type="submit" class="btn btn-success btn-sm">
                                                                <i class="fas fa-check"></i> Accept
                                                            </button>
                                                        </form>
                                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectPaymentModal" data-payment-id="<?php echo $payment['id']; ?>">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Rejection modal for admin
if ($_SESSION['role'] === 'admin'): 
?>
<div class="modal fade" id="rejectPaymentModal" tabindex="-1" aria-labelledby="rejectPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectPaymentModalLabel">Reject Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectPaymentForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="payment_id" id="rejectPaymentId">
                    <input type="hidden" name="payment_action" value="reject">
                    
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" required></textarea>
                        <div class="invalid-feedback">Please provide a reason for rejection.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle reject payment modal
    const rejectPaymentModal = document.getElementById('rejectPaymentModal');
    if (rejectPaymentModal) {
        rejectPaymentModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const paymentId = button.getAttribute('data-payment-id');
            document.getElementById('rejectPaymentId').value = paymentId;
        });

        // Reset form on modal close
        rejectPaymentModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('rejectPaymentForm').reset();
        });

        // Form validation
        const rejectForm = document.getElementById('rejectPaymentForm');
        rejectForm.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    }
});
</script>
<?php endif; ?>

<?php require_once '../includes/admin_footer.php'; ?>