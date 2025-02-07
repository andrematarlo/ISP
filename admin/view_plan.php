<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get plan ID from URL
$plan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$plan_id) {
    header('Location: plans.php');
    exit();
}

// Get plan details
$sql = "SELECT * FROM plans WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $plan_id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {
    header('Location: plans.php');
    exit();
}

// Get active subscribers
$sql = "SELECT c.*, s.start_date, s.end_date, s.status as subscription_status 
        FROM subscriptions s 
        JOIN customers c ON s.customer_id = c.id 
        WHERE s.plan_id = ? AND s.status = 'active'
        ORDER BY s.start_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $plan_id);
$stmt->execute();
$subscribers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get subscription statistics
$sql = "SELECT 
            COUNT(*) as total_subscriptions,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_subscriptions,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_subscriptions
        FROM subscriptions 
        WHERE plan_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $plan_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Plan - ISP Management System</title>
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="customers.php">Customers</a></li>
                    <li class="nav-item"><a class="nav-link active" href="plans.php">Plans</a></li>
                    <li class="nav-item"><a class="nav-link" href="bills.php">Bills</a></li>
                    <li class="nav-item"><a class="nav-link" href="tickets.php">Support Tickets</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Plan Details</h2>
            <div>
                <a href="edit_plan.php?id=<?php echo $plan_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Plan
                </a>
                <a href="plans.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Plan Information -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Plan Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th width="30%">Name:</th>
                                <td><?php echo htmlspecialchars($plan['name']); ?></td>
                            </tr>
                            <tr>
                                <th>Speed:</th>
                                <td><?php echo htmlspecialchars($plan['speed']); ?></td>
                            </tr>
                            <tr>
                                <th>Price:</th>
                                <td>₱<?php echo number_format($plan['price'], 2); ?>/month</td>
                            </tr>
                            <tr>
                                <th>Description:</th>
                                <td><?php echo nl2br(htmlspecialchars($plan['description'])); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge bg-<?php echo $plan['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($plan['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Created:</th>
                                <td><?php echo date('F j, Y', strtotime($plan['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Subscription Statistics -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Subscription Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h3 class="text-primary"><?php echo $stats['total_subscriptions']; ?></h3>
                                <p class="text-muted">Total Subscriptions</p>
                            </div>
                            <div class="col-md-4">
                                <h3 class="text-success"><?php echo $stats['active_subscriptions']; ?></h3>
                                <p class="text-muted">Active Subscribers</p>
                            </div>
                            <div class="col-md-4">
                                <h3 class="text-danger"><?php echo $stats['inactive_subscriptions']; ?></h3>
                                <p class="text-muted">Inactive Subscribers</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Subscribers -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Active Subscribers</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="subscribersTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Customer Name</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscribers as $subscriber): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subscriber['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($subscriber['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($subscriber['address']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($subscriber['start_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($subscriber['end_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $subscriber['subscription_status'] === 'active' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($subscriber['subscription_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_customer.php?id=<?php echo $subscriber['id']; ?>" 
                                               class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
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
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.2.2/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.2.2/js/dataTables.bootstrap5.js"></script>
    <script src="https://kit.fontawesome.com/your-font-awesome-kit.js"></script>
    <script>
        $(document).ready(function() {
            $('#subscribersTable').DataTable({
                pageLength: 10,
                order: [[3, 'desc']], // Sort by start date by default
            });
        });
    </script>
</body>
</html>
