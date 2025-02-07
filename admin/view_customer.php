<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
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

// Get customer details
$sql = "SELECT c.*, u.username, u.email, u.created_at 
        FROM customers c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    header('Location: customers.php');
    exit();
}

// Get customer's current subscription
$sql = "SELECT s.*, p.name as plan_name, p.speed, p.price 
        FROM subscriptions s 
        JOIN plans p ON s.plan_id = p.id 
        WHERE s.customer_id = ? AND s.status = 'active'
        ORDER BY s.start_date DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$subscription = $stmt->get_result()->fetch_assoc();

// Get billing history
$sql = "SELECT b.* 
        FROM bills b 
        JOIN subscriptions s ON b.subscription_id = s.id 
        WHERE s.customer_id = ? 
        ORDER BY b.bill_date DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$bills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get support tickets
$sql = "SELECT * FROM service_requests 
        WHERE customer_id = ? 
        ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<?php 
// Require admin header
require_once '../includes/admin_header.php'; 
?>

    <main class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Customer Details</h2>
            <div>
                <a href="edit_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Customer
                </a>
                <a href="customers.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Customer Information -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Customer Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th width="30%">Full Name:</th>
                                <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Username:</th>
                                <td><?php echo htmlspecialchars($customer['username']); ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            </tr>
                            <tr>
                                <th>Address:</th>
                                <td><?php echo htmlspecialchars($customer['address']); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge bg-<?php echo $customer['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($customer['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Joined:</th>
                                <td><?php echo date('F j, Y', strtotime($customer['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Current Subscription -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Current Subscription</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($subscription): ?>
                            <table class="table table-borderless">
                                <tr>
                                    <th width="30%">Plan:</th>
                                    <td><?php echo htmlspecialchars($subscription['plan_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Speed:</th>
                                    <td><?php echo htmlspecialchars($subscription['speed']); ?></td>
                                </tr>
                                <tr>
                                    <th>Price:</th>
                                    <td>₱<?php echo number_format($subscription['price'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Start Date:</th>
                                    <td><?php echo date('F j, Y', strtotime($subscription['start_date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>End Date:</th>
                                    <td><?php echo date('F j, Y', strtotime($subscription['end_date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge bg-<?php echo $subscription['status'] === 'active' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($subscription['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <p class="text-muted mb-0">No active subscription found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Bills -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Bills</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($bills): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Bill Date</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bills as $bill): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($bill['bill_date'])); ?></td>
                                                <td>₱<?php echo number_format($bill['amount'], 2); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($bill['due_date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $bill['status'] === 'paid' ? 'success' : 'danger'; ?>">
                                                        <?php echo ucfirst($bill['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No billing history found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Support Tickets -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Support Tickets</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($tickets): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Subject</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tickets as $ticket): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo match($ticket['status']) {
                                                            'open' => 'danger',
                                                            'in_progress' => 'warning',
                                                            'resolved' => 'success',
                                                            'closed' => 'secondary',
                                                            default => 'primary'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No support tickets found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php 
// Require admin footer
require_once '../includes/admin_footer.php'; 
