<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../login.php');
    exit();
}

// Default data structures with comprehensive default values
$customer = [
    'id' => 0,
    'full_name' => 'Customer',
    'username' => 'user',
    'address' => 'Not Provided',
    'phone' => 'Not Provided',
    'status' => 'inactive',
    'email' => ''
];

$subscription = [
    'id' => 0,
    'plan_name' => 'No Active Plan',
    'speed' => 'N/A',
    'price' => 0,
    'status' => 'inactive',
    'start_date' => date('Y-m-d')
];

$recent_bills = [];
$recent_tickets = [];

try {
    // Get user ID from session with fallback
    $user_id = $_SESSION['user_id'] ?? 0;

    // Direct database status check with comprehensive logging
    $debug_query = "SELECT 
        c.id AS customer_id, 
        c.user_id, 
        c.status AS customer_status, 
        c.full_name,
        u.username,
        u.role
    FROM customers c
    JOIN users u ON c.user_id = u.id
    WHERE c.user_id = ?";
    
    $debug_stmt = $conn->prepare($debug_query);
    $debug_stmt->bind_param('i', $user_id);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    $debug_data = $debug_result->fetch_assoc();

    // Extensive error logging
    error_log("CRITICAL STATUS DEBUG: " . json_encode([
        'user_id' => $user_id,
        'session_data' => $_SESSION,
        'debug_query_result' => $debug_data,
        'php_process_user' => get_current_user(),
        'server_user' => $_SERVER['USER'] ?? 'Unknown'
    ]));

    // Retrieve customer information with detailed status checking
    $sql = "SELECT c.*, u.email, u.username, 
            CASE 
                WHEN c.status IS NULL THEN 'inactive'
                WHEN c.status = '' THEN 'inactive'
                ELSE c.status 
            END as customer_status 
            FROM customers c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $customer_result = $stmt->get_result();
    $customer_data = $customer_result->fetch_assoc();

    // Update customer data if found
    if ($customer_data) {
        $customer = array_merge($customer, $customer_data);
        
        // EXPLICITLY set status from database query result
        $customer['status'] = $debug_data['customer_status'];
        
        // Additional detailed logging
        error_log(sprintf(
            "Customer Status Forced Update - User ID: %d, Customer ID: %d, Forced Status: %s, Debug Status: %s", 
            $user_id, 
            $customer['id'] ?? 0, 
            $customer['status'],
            $debug_data['customer_status'] ?? 'NULL'
        ));
    } else {
        // Log if no customer data found
        error_log("No customer data found for user ID: " . $user_id);
    }

    // Retrieve subscription if customer exists
    if ($customer['id'] > 0) {
        $sql = "SELECT s.*, p.name as plan_name, p.speed, p.price 
                FROM subscriptions s 
                JOIN plans p ON s.plan_id = p.id 
                WHERE s.customer_id = ? AND s.status = 'active' 
                ORDER BY s.start_date DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $customer['id']);
        $stmt->execute();
        $subscription_result = $stmt->get_result();
        $subscription_data = $subscription_result->fetch_assoc();

        // Update subscription if found
        if ($subscription_data) {
            $subscription = array_merge($subscription, $subscription_data);
        }

        // Retrieve recent bills
        $sql = "SELECT b.*, s.plan_id FROM bills b 
                JOIN subscriptions s ON b.subscription_id = s.id 
                WHERE s.customer_id = ? 
                ORDER BY b.bill_date DESC LIMIT 5";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $customer['id']);
        $stmt->execute();
        $bills_result = $stmt->get_result();
        
        while ($bill = $bills_result->fetch_assoc()) {
            $recent_bills[] = array_merge([
                'id' => 0,
                'bill_date' => date('Y-m-d'),
                'amount' => 0,
                'status' => 'unknown',
                'subscription_id' => 0,
                'plan_id' => 0
            ], $bill);
        }

        // Retrieve recent tickets
        $sql = "SELECT * FROM support_tickets 
                WHERE customer_id = ? 
                ORDER BY created_at DESC LIMIT 5";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $customer['id']);
        $stmt->execute();
        $tickets_result = $stmt->get_result();
        
        while ($ticket = $tickets_result->fetch_assoc()) {
            $recent_tickets[] = array_merge([
                'id' => 0,
                'subject' => 'No Subject',
                'status' => 'unknown',
                'created_at' => date('Y-m-d H:i:s'),
                'priority' => 'low'
            ], $ticket);
        }
    }

    // Ensure at least one placeholder bill and ticket if none found
    if (empty($recent_bills)) {
        $recent_bills[] = [
            'id' => 0,
            'bill_date' => date('Y-m-d'),
            'amount' => 0,
            'status' => 'No recent bills',
            'subscription_id' => 0,
            'plan_id' => 0
        ];
    }

    if (empty($recent_tickets)) {
        $recent_tickets[] = [
            'id' => 0,
            'subject' => 'No Recent Tickets',
            'status' => 'N/A',
            'created_at' => date('Y-m-d H:i:s'),
            'priority' => 'low'
        ];
    }
} catch (Exception $e) {
    // Log any unexpected errors
    error_log("Dashboard error: " . $e->getMessage());
}

require_once '../includes/customer_header.php';
?>

<div class="container py-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-1">Welcome, <?php echo htmlspecialchars($customer['full_name'] ?? 'Customer'); ?>!</h4>
                            <p class="text-muted mb-1">Account Status: 
                                <span class="badge <?php 
                                    // Determine badge color based on status from database
                                    $status = $debug_data['customer_status'] ?? 'inactive';
                                    echo ($status === 'active') ? 'bg-success' : 'bg-danger'; 
                                ?>">
                                    <?php 
                                    // Ensure status is always set and capitalized
                                    echo ucfirst($status); 
                                    ?>
                                </span>
                            </p>

                            <!-- Debugging information -->
                            <?php if (isset($_SESSION['debug']) && $_SESSION['debug'] === true): ?>
                            <div class="small text-muted mt-2">
                                Debug Info:
                                <br>User ID: <?php echo $user_id; ?>
                                <br>Raw Status: <?php echo $customer_data['customer_status'] ?? 'N/A'; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="text-end">
                            <p class="text-muted mb-1">User ID</p>
                            <h5 class="mb-0"><?php echo $user_id; ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Plan Section -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Current Plan</h5>
                </div>
                <div class="card-body">
                    <?php if ($subscription): ?>
                        <h3 class="mb-3"><?php echo htmlspecialchars($subscription['plan_name'] ?? 'No Active Plan'); ?></h3>
                        <div class="row g-3">
                            <div class="col-6">
                                <p class="text-muted mb-1">Speed</p>
                                <h5><?php echo htmlspecialchars($subscription['speed'] ?? 'N/A'); ?> Mbps</h5>
                            </div>
                            <div class="col-6">
                                <p class="text-muted mb-1">Monthly Fee</p>
                                <h5>₱<?php echo number_format($subscription['price'] ?? 0, 2); ?></h5>
                            </div>
                            <div class="col-12">
                                <a href="subscription.php" class="btn btn-primary">
                                    <i class="fas fa-wifi me-2"></i>Manage Subscription
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-wifi text-muted mb-3" style="font-size: 3rem;"></i>
                            <p class="mb-3">No active subscription found.</p>
                            <a href="subscription.php" class="btn btn-primary">View Available Plans</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="tickets.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="fas fa-ticket-alt mb-2" style="font-size: 2rem;"></i>
                                Support Tickets
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="view_bill.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="fas fa-file-invoice-dollar mb-2" style="font-size: 2rem;"></i>
                                View Bills
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="subscription.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="fas fa-arrow-up mb-2" style="font-size: 2rem;"></i>
                                Upgrade Plan
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="tickets.php?new=1" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="fas fa-headset mb-2" style="font-size: 2rem;"></i>
                                Get Support
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <!-- Recent Bills -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Bills</h5>
                    <a href="view_bill.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Bill Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $bill_count = 0;
                                foreach ($recent_bills as $bill): 
                                    // Skip bills without an ID
                                    if (!isset($bill['id'])) {
                                        continue;
                                    }
                                    $bill_count++;
                                ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($bill['bill_date'] ?? 'now')); ?></td>
                                        <td>₱<?php echo number_format($bill['amount'] ?? 0, 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo isset($bill['status']) && $bill['status'] === 'paid' ? 'success' : 
                                                    (isset($bill['status']) && $bill['status'] === 'pending' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($bill['status'] ?? 'Unknown'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_bill.php?id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php 
                                    // Break if more than 5 bills
                                    if ($bill_count >= 5) break; 
                                endforeach; 
                                
                                // If no bills, show a message
                                if ($bill_count === 0): 
                                ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">
                                            No recent bills found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Support Tickets -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Support Tickets</h5>
                    <a href="tickets.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_tickets as $ticket): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ticket['subject'] ?? 'No Subject'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $ticket['status'] === 'closed' ? 'success' : 
                                                    ($ticket['status'] === 'open' ? 'danger' : 
                                                    ($ticket['status'] === 'in_progress' ? 'warning' : 'info')); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $ticket['status'] ?? 'unknown')); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($ticket['created_at'] ?? 'now')); ?></td>
                                        <td>
                                            <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                View
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
</div>

<?php require_once '../includes/footer.php'; ?>
