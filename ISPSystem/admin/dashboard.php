<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get counts for dashboard
$customers_count = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];
$active_plans = $conn->query("SELECT COUNT(*) as count FROM plans WHERE status = 'active'")->fetch_assoc()['count'];
$pending_tickets = $conn->query("SELECT COUNT(*) as count FROM service_requests WHERE status = 'open'")->fetch_assoc()['count'];
$unpaid_bills = $conn->query("SELECT COUNT(*) as count FROM bills WHERE status = 'unpaid'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ISP Management System</title>
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
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="customers.php">Customers</a></li>
                    <li class="nav-item"><a class="nav-link" href="plans.php">Plans</a></li>
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
        <h2>Admin Dashboard</h2>
        
        <!-- Dashboard Statistics -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Customers</h5>
                        <h2 class="card-text"><?php echo $customers_count; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Active Plans</h5>
                        <h2 class="card-text"><?php echo $active_plans; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title">Pending Tickets</h5>
                        <h2 class="card-text"><?php echo $pending_tickets; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Unpaid Bills</h5>
                        <h2 class="card-text"><?php echo $unpaid_bills; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Customers Table -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Customers</h5>
            </div>
            <div class="card-body">
                <table id="recentCustomers" class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT c.*, u.email FROM customers c 
                                JOIN users u ON c.user_id = u.id 
                                ORDER BY c.id DESC LIMIT 10";
                        $result = $conn->query($sql);
                        while($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $row['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="view_customer.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">View</a>
                                <a href="edit_customer.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Support Tickets -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Support Tickets</h5>
            </div>
            <div class="card-body">
                <table id="recentTickets" class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT sr.*, c.full_name FROM service_requests sr 
                                JOIN customers c ON sr.customer_id = c.id 
                                ORDER BY sr.created_at DESC LIMIT 10";
                        $result = $conn->query($sql);
                        while($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['subject']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $row['status'] === 'open' ? 'danger' : 
                                        ($row['status'] === 'in_progress' ? 'warning' : 
                                        ($row['status'] === 'resolved' ? 'success' : 'secondary')); 
                                    ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                            <td>
                                <a href="view_ticket.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">View</a>
                                <a href="edit_ticket.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.2.2/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.2.2/js/dataTables.bootstrap5.js"></script>
    <script>
        $(document).ready(function() {
            $('#recentCustomers').DataTable({
                pageLength: 5,
                lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
                order: [[0, 'desc']]
            });
            
            $('#recentTickets').DataTable({
                pageLength: 5,
                lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
                order: [[4, 'desc']]
            });
        });
    </script>
</body>
</html>
