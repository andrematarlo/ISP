<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get bill ID from URL
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$bill_id) {
    header('Location: bills.php');
    exit();
}

// Get bill details and verify it belongs to the current user
$sql = "SELECT b.*, c.full_name, c.phone, c.address, p.name as plan_name, p.speed,
        s.start_date as subscription_start, u.email
        FROM bills b 
        JOIN subscriptions s ON b.subscription_id = s.id 
        JOIN customers c ON s.customer_id = c.id 
        JOIN users u ON c.user_id = u.id
        JOIN plans p ON s.plan_id = p.id 
        WHERE b.id = ? AND u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $bill_id, $_SESSION['user_id']);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();

if (!$bill) {
    header('Location: bills.php');
    exit();
}

// Get payment history
$sql = "SELECT * FROM payments WHERE bill_id = ? ORDER BY payment_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $bill_id);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get success/error messages
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

require_once '../includes/customer_header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Bill Details</h2>
        <div>
            <a href="print_bill.php?id=<?php echo $bill_id; ?>" class="btn btn-secondary" target="_blank">
                <i class="fas fa-print"></i> Print Bill
            </a>
            <a href="bills.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Bills
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column -->
        <div class="col-md-8">
            <!-- Bill Information -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Bill Information</h4>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Bill ID:</strong> <?php echo $bill['id']; ?></p>
                            <p class="mb-1"><strong>Subscription:</strong> <?php echo $bill['plan_name'] . ' (' . $bill['speed'] . ' Mbps)'; ?></p>
                            <p class="mb-1"><strong>Subscription Start:</strong> <?php echo date('M j, Y', strtotime($bill['subscription_start'])); ?></p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Bill Date:</strong> <?php echo date('M j, Y', strtotime($bill['bill_date'])); ?></p>
                            <p class="mb-1"><strong>Due Date:</strong> <?php echo date('M j, Y', strtotime($bill['due_date'])); ?></p>
                            <p class="mb-1"><strong>Status:</strong> 
                                <span class="badge <?php echo $bill['status'] === 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo ucfirst($bill['status']); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <div class="border-start ps-3">
                                <h5 class="mb-2">Amount Details</h5>
                                <p class="mb-1"><strong>Total Amount:</strong></p>
                                <h4 class="text-primary">₱<?php echo number_format($bill['amount'], 2); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Payment History</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($payments)): ?>
                        <p class="text-muted">No payment history available.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y g:i A', strtotime($payment['payment_date'])); ?></td>
                                        <td><span class="badge bg-info"><?php echo ucfirst($payment['payment_method']); ?></span></td>
                                        <td><?php echo htmlspecialchars($payment['reference_number'] ?? 'N/A'); ?></td>
                                        <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $payment['status'] === 'completed' ? 'bg-success' : 
                                                     ($payment['status'] === 'failed' ? 'bg-danger' : 'bg-warning'); 
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

        <!-- Right Column -->
        <div class="col-md-4">
            <!-- Customer Details -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Customer Details</h4>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($bill['full_name']); ?></p>
                    <p class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($bill['phone']); ?></p>
                    <p class="mb-2"><strong>Address:</strong> <?php echo htmlspecialchars($bill['address']); ?></p>
                </div>
            </div>

            <?php if ($bill['status'] === 'unpaid'): ?>
            <!-- GCash Payment Instructions -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">GCash Payment Instructions</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle text-primary mt-1 me-2"></i>
                                <div>
                                    <strong>GCash Payment Steps:</strong>
                                    <ol class="ps-3 mb-0">
                                        <li>Open GCash app</li>
                                        <li>Send to: <strong>09195700051</strong></li>
                                        <li>Amount: <strong>₱<?php echo number_format($bill['amount'], 2); ?></strong></li>
                                        <li>Save reference number</li>
                                        <li>Enter details below</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="text-center">
                                <p class="mb-2"><strong>Scan QR Code</strong></p>
                                <img src="../assets/images/gcashqr.jpg" 
                                     alt="GCash QR Code" class="img-fluid border p-2 rounded" style="max-width: 150px;">
                                <p class="small text-muted mt-2">Scan with GCash app</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pay Bill via GCash -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Pay Bill via GCash</h4>
                </div>
                <div class="card-body">
                    <form action="process_gcash_payment.php" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="bill_id" value="<?php echo $bill_id; ?>">
                        <input type="hidden" name="amount" value="<?php echo $bill['amount']; ?>">

                        <div class="mb-3">
                            <label for="gcash_number" class="form-label">GCash Number</label>
                            <input type="text" class="form-control" id="gcash_number" name="gcash_number" 
                                   pattern="^(09|\+639)\d{9}$" required>
                            <div class="form-text">Format: 09XX XXX XXXX or +639XX XXX XXXX</div>
                        </div>

                        <div class="mb-3">
                            <label for="gcash_reference" class="form-label">GCash Reference Number</label>
                            <input type="text" class="form-control" id="gcash_reference" name="gcash_reference" required>
                        </div>

                        <div class="mb-3">
                            <label for="payment_date" class="form-label">Payment Date</label>
                            <input type="datetime-local" class="form-control" id="payment_date" name="payment_date" 
                                   value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-check me-2"></i>Confirm Payment
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/customer_footer.php'; ?>
