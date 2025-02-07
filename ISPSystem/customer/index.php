<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../login.php');
    exit();
}

// Get customer information
$user_id = $_SESSION['user_id'];
$sql = "SELECT c.*, u.email FROM customers c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

// Get current subscription
$sql = "SELECT s.*, p.name as plan_name, p.speed, p.price 
        FROM subscriptions s 
        JOIN plans p ON s.plan_id = p.id 
        WHERE s.customer_id = ? AND s.status = 'active' 
        ORDER BY s.start_date DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer['id']);
$stmt->execute();
$subscription = $stmt->get_result()->fetch_assoc();

// Get billing summary
$sql = "SELECT 
            COUNT(*) as total_bills,
            SUM(CASE WHEN b.status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_bills,
            SUM(CASE WHEN b.status = 'unpaid' THEN b.amount ELSE 0 END) as total_due
        FROM bills b 
        JOIN subscriptions s ON b.subscription_id = s.id 
        WHERE s.customer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer['id']);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();

// Get latest support ticket
$sql = "SELECT * FROM service_requests 
        WHERE customer_id = ? 
        ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer['id']);
$stmt->execute();
$latest_ticket = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - ISP Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.2.2/css/dataTables.bootstrap5.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">ISP System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="subscription.php">My Subscription</a></li>
                    <li class="nav-item"><a class="nav-link" href="bills.php">My Bills</a></li>
                    <li class="nav-item"><a class="nav-link" href="support.php">Support</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            My Account
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="password.php">Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-4">
        <!-- Welcome Banner -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1">Welcome back, <?php echo htmlspecialchars($customer['full_name']); ?>!</h4>
                                <p class="mb-0">Account Status: 
                                    <span class="badge bg-<?php echo $customer['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($customer['status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="text-end">
                                <p class="mb-0">Customer ID: <?php echo $customer['id']; ?></p>
                                <small>Member since: <?php echo date('F Y', strtotime($customer['created_at'])); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Current Plan</h5>
                        <?php if ($subscription): ?>
                            <h3><?php echo htmlspecialchars($subscription['plan_name']); ?></h3>
                            <p class="text-muted mb-0">Speed: <?php echo htmlspecialchars($subscription['speed']); ?></p>
                            <p class="text-muted">Monthly: $<?php echo number_format($subscription['price'], 2); ?></p>
                            <a href="subscription.php" class="btn btn-primary btn-sm">Manage Plan</a>
                        <?php else: ?>
                            <p class="text-muted">No active subscription</p>
                            <a href="plans.php" class="btn btn-primary btn-sm">View Plans</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Billing Summary</h5>
                        <div class="mb-3">
                            <h3>$<?php echo number_format($billing['total_due'], 2); ?></h3>
                            <p class="text-muted mb-0">Total Amount Due</p>
                        </div>
                        <p class="mb-2">
                            <span class="badge bg-warning"><?php echo $billing['unpaid_bills']; ?> Unpaid Bills</span>
                        </p>
                        <a href="bills.php" class="btn btn-primary btn-sm">View Bills</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Support Status</h5>
                        <?php if ($latest_ticket): ?>
                            <p class="mb-2">Latest Ticket: 
                                <span class="badge bg-<?php 
                                    echo $latest_ticket['status'] === 'open' ? 'danger' : 
                                        ($latest_ticket['status'] === 'in_progress' ? 'warning' : 'success'); 
                                    ?>">
                                    <?php echo ucfirst($latest_ticket['status']); ?>
                                </span>
                            </p>
                            <p class="text-muted"><?php echo htmlspecialchars($latest_ticket['subject']); ?></p>
                        <?php else: ?>
                            <p class="text-muted">No recent support tickets</p>
                        <?php endif; ?>
                        <a href="support.php?new=1" class="btn btn-primary btn-sm">New Support Ticket</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Quick Actions</h5>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <a href="pay_bill.php" class="btn btn-outline-primary w-100">
                                    Pay Bill
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="upgrade.php" class="btn btn-outline-success w-100">
                                    Upgrade Plan
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="support.php" class="btn btn-outline-info w-100">
                                    Contact Support
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="profile.php" class="btn btn-outline-secondary w-100">
                                    Update Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Graph Placeholder -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Internet Usage</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Monthly Usage Statistics</p>
                <div class="progress mb-3">
                    <div class="progress-bar" role="progressbar" style="width: 75%;" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100">75% Used</div>
                </div>
                <small class="text-muted">750GB of 1000GB Monthly Limit Used</small>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.2.2/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.2.2/js/dataTables.bootstrap5.js"></script>
</body>
</html>
