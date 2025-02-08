<?php
ob_start(); // Start output buffering
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle bill payment
if (isset($_POST['mark_as_paid']) && isset($_POST['bill_id'])) {
    $bill_id = (int)$_POST['bill_id'];
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Update bill status
        $sql = "UPDATE bills SET status = 'paid', paid_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $bill_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update bill status");
        }
        
        // Log the action
        $log_sql = "INSERT INTO activity_log (user_id, action, details) VALUES (?, 'mark_bill_paid', ?)";
        $log_details = json_encode([
            'bill_id' => $bill_id,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param('is', $_SESSION['user_id'], $log_details);
        $log_stmt->execute();
        
        $conn->commit();
        $_SESSION['success'] = "Bill marked as paid successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error marking bill as paid: " . $e->getMessage();
    }
    
    // Clear output buffer before redirect
    ob_end_clean();
    header('Location: bills.php');
    exit();
}

// Include header after handling form submission
require_once '../includes/admin_header.php';

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

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

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
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="bill_id" value="<?php echo $bill['id']; ?>">
                                                <input type="hidden" name="mark_as_paid" value="1">
                                                <button type="submit" 
                                                        class="btn btn-sm btn-success"
                                                        onclick="return confirm('Are you sure you want to mark this bill as paid?');">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
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
    });
</script>

<?php require_once '../includes/footer.php'; ?>
