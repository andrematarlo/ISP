<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get total customers
$sql = "SELECT COUNT(*) as total FROM customers";
$result = $conn->query($sql);
$total_customers = $result->fetch_assoc()['total'];

// Get active subscriptions
$sql = "SELECT COUNT(*) as total FROM subscriptions WHERE status = 'active'";
$result = $conn->query($sql);
$active_subscriptions = $result->fetch_assoc()['total'];

// Get total revenue this month
$sql = "SELECT SUM(amount) as total FROM bills WHERE MONTH(bill_date) = MONTH(CURRENT_DATE()) AND YEAR(bill_date) = YEAR(CURRENT_DATE()) AND status = 'paid'";
$result = $conn->query($sql);
$monthly_revenue = $result->fetch_assoc()['total'] ?? 0;

// Get open support tickets
$sql = "SELECT COUNT(*) as total FROM support_tickets WHERE status IN ('open', 'in_progress')";
$result = $conn->query($sql);
$open_tickets = $result->fetch_assoc()['total'];

// Get recent activities
$sql = "SELECT 
            'subscription' as type,
            s.id,
            c.full_name as customer,
            p.name as plan,
            s.created_at,
            s.status
        FROM subscriptions s
        JOIN customers c ON s.customer_id = c.id
        JOIN plans p ON s.plan_id = p.id
        WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        UNION ALL
        SELECT 
            'bill' as type,
            b.id,
            c.full_name as customer,
            CONCAT('₱', b.amount) as plan,
            b.created_at,
            b.status
        FROM bills b
        JOIN subscriptions s ON b.subscription_id = s.id
        JOIN customers c ON s.customer_id = c.id
        WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        UNION ALL
        SELECT 
            'ticket' as type,
            t.id,
            c.full_name as customer,
            t.subject as plan,
            t.created_at,
            t.status
        FROM support_tickets t
        JOIN customers c ON t.customer_id = c.id
        WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY created_at DESC
        LIMIT 10";
$activities = $conn->query($sql);

require_once '../includes/admin_header.php';
?>

<main class="container">
    <!-- Dashboard Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Dashboard</h2>
        <div class="btn-group">
            <a href="generate_bills.php" class="btn btn-primary">
                <i class="fas fa-file-invoice-dollar"></i> Generate Bills
            </a>
            <a href="reports.php" class="btn btn-info text-white">
                <i class="fas fa-chart-bar"></i> View Reports
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Customers</h5>
                    <h3 class="mb-0"><?php echo number_format($total_customers); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Active Subscriptions</h5>
                    <h3 class="mb-0"><?php echo number_format($active_subscriptions); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Monthly Revenue</h5>
                    <h3 class="mb-0">₱<?php echo number_format($monthly_revenue, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Open Tickets</h5>
                    <h3 class="mb-0"><?php echo number_format($open_tickets); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Recent Activities</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($activity = $activities->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <i class="fas fa-<?php 
                                        echo $activity['type'] === 'subscription' ? 'wifi' : 
                                            ($activity['type'] === 'bill' ? 'file-invoice-dollar' : 'ticket-alt'); 
                                    ?>"></i>
                                    <?php echo ucfirst($activity['type']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($activity['customer']); ?></td>
                                <td><?php echo htmlspecialchars($activity['plan']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $activity['status'] === 'active' || $activity['status'] === 'paid' ? 'success' :
                                            ($activity['status'] === 'pending' || $activity['status'] === 'unpaid' ? 'warning' :
                                            ($activity['status'] === 'open' || $activity['status'] === 'in_progress' ? 'danger' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst($activity['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></td>
                                <td>
                                    <a href="<?php 
                                        echo $activity['type'] === 'subscription' ? 'view_customer.php' :
                                            ($activity['type'] === 'bill' ? 'view_bill.php' : 'view_ticket.php');
                                    ?>?id=<?php echo $activity['id']; ?>" 
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
</main>

<?php require_once '../includes/footer.php'; ?>
