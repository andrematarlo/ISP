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
$sql = "SELECT c.id FROM customers c 
        WHERE c.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$customer_result = $stmt->get_result();
$customer = $customer_result->fetch_assoc();

if (!$customer) {
    $_SESSION['error'] = "Customer profile not found.";
    header('Location: ../login.php');
    exit();
}

// Get bills with subscription and plan details
$sql = "SELECT b.*, p.name as plan_name, s.start_date as subscription_start 
        FROM bills b
        JOIN subscriptions s ON b.subscription_id = s.id
        JOIN plans p ON s.plan_id = p.id
        WHERE s.customer_id = ?
        ORDER BY b.bill_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer['id']);
$stmt->execute();
$bills_result = $stmt->get_result();

// Calculate total amounts
$total_billed = 0;
$total_paid = 0;
$total_unpaid = 0;

// Store bills for later use
$bills = [];
while ($bill = $bills_result->fetch_assoc()) {
    $bills[] = $bill;
    
    // Calculate totals
    $total_billed += $bill['amount'] ?? 0;
    
    if ($bill['status'] === 'paid') {
        $total_paid += $bill['amount'] ?? 0;
    } elseif ($bill['status'] === 'unpaid') {
        $total_unpaid += $bill['amount'] ?? 0;
    }
}

// Reset bills result for display
$bills_result = $bills;

require_once '../includes/customer_header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>My Bills</h2>
            </div>

            <?php if (count($bills_result) === 0): ?>
                <div class="alert alert-info">
                    You have no bills at the moment.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Bill Date</th>
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bills_result as $bill): ?>
                                <tr>
                                    <td><?php echo date('F j, Y', strtotime($bill['bill_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($bill['plan_name']); ?></td>
                                    <td>₱<?php echo number_format($bill['amount'] ?? 0, 2); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            switch($bill['status']) {
                                                case 'paid': echo 'bg-success'; break;
                                                case 'unpaid': echo 'bg-danger'; break;
                                                case 'partial': echo 'bg-warning'; break;
                                                default: echo 'bg-secondary';
                                            }
                                            ?>">
                                            <?php echo ucfirst($bill['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_bill.php?id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
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

<?php require_once '../includes/footer.php'; ?>
