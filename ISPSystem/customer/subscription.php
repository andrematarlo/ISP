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
$sql = "SELECT s.*, p.name as plan_name, p.speed, p.price, p.description
        FROM subscriptions s 
        JOIN plans p ON s.plan_id = p.id 
        WHERE s.customer_id = ? AND s.status = 'active'
        ORDER BY s.start_date DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer['id']);
$stmt->execute();
$current_subscription = $stmt->get_result()->fetch_assoc();

// Get subscription history
$sql = "SELECT s.*, p.name as plan_name, p.speed, p.price
        FROM subscriptions s 
        JOIN plans p ON s.plan_id = p.id 
        WHERE s.customer_id = ?
        ORDER BY s.start_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer['id']);
$stmt->execute();
$subscription_history = $stmt->get_result();

// Get available plans for upgrade
$sql = "SELECT * FROM plans WHERE status = 'active' ORDER BY price ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$available_plans = $stmt->get_result();

require_once '../includes/customer_header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="card-title">Current Plan</h4>
                            <?php if ($current_subscription): ?>
                                <h2 class="display-6 mb-3"><?php echo htmlspecialchars($current_subscription['plan_name']); ?></h2>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <p class="mb-1 text-muted">Speed</p>
                                        <h5><?php echo htmlspecialchars($current_subscription['speed']); ?> Mbps</h5>
                                    </div>
                                    <div class="col-sm-6">
                                        <p class="mb-1 text-muted">Monthly Fee</p>
                                        <h5>₱<?php echo number_format($current_subscription['price'], 2); ?></h5>
                                    </div>
                                    <div class="col-sm-6">
                                        <p class="mb-1 text-muted">Start Date</p>
                                        <h5><?php echo date('F j, Y', strtotime($current_subscription['start_date'])); ?></h5>
                                    </div>
                                    <div class="col-sm-6">
                                        <p class="mb-1 text-muted">Status</p>
                                        <h5>
                                            <span class="badge bg-<?php echo $current_subscription['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($current_subscription['status']); ?>
                                            </span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <p class="mb-1 text-muted">Plan Features</p>
                                        <p><?php echo nl2br(htmlspecialchars($current_subscription['description'] ?? 'No description available.')); ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    You don't have an active subscription. Choose a plan below to get started.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-center d-flex align-items-center justify-content-center">
                            <div>
                                <i class="fas fa-wifi text-primary" style="font-size: 4rem;"></i>
                                <?php if ($current_subscription): ?>
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#upgradePlanModal">
                                            <i class="fas fa-arrow-up me-2"></i>Upgrade Plan
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Available Plans -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">Available Plans</h4>
        </div>
        <?php while ($plan = $available_plans->fetch_assoc()): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 <?php echo $current_subscription && $current_subscription['plan_id'] == $plan['id'] ? 'border-primary' : ''; ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($plan['name']); ?></h5>
                        <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($plan['speed']); ?> Mbps</h6>
                        <p class="card-text">
                            <?php echo nl2br(htmlspecialchars($plan['description'])); ?>
                        </p>
                        <div class="text-center mt-3">
                            <h3 class="mb-3">₱<?php echo number_format($plan['price'], 2); ?>/mo</h3>
                            <?php if ($current_subscription && $current_subscription['plan_id'] == $plan['id']): ?>
                                <button class="btn btn-outline-primary" disabled>Current Plan</button>
                            <?php else: ?>
                                <button class="btn btn-primary" onclick="requestUpgrade(<?php echo $plan['id']; ?>)">
                                    <?php echo $current_subscription ? 'Request Upgrade' : 'Subscribe'; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- Subscription History -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Subscription History</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Speed</th>
                            <th>Monthly Fee</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sub = $subscription_history->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sub['plan_name']); ?></td>
                                <td><?php echo htmlspecialchars($sub['speed']); ?> Mbps</td>
                                <td>₱<?php echo number_format($sub['price'], 2); ?></td>
                                <td><?php echo date('M j, Y', strtotime($sub['start_date'])); ?></td>
                                <td>
                                    <?php echo $sub['end_date'] ? date('M j, Y', strtotime($sub['end_date'])) : '-'; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $sub['status'] === 'active' ? 'success' : 
                                            ($sub['status'] === 'pending' ? 'warning' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst($sub['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Upgrade Plan Modal -->
<div class="modal fade" id="upgradePlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Plan Upgrade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="upgradeForm" action="process_upgrade.php" method="POST">
                    <input type="hidden" name="plan_id" id="upgradePlanId">
                    <div class="mb-3">
                        <label for="upgradeReason" class="form-label">Reason for Upgrade</label>
                        <textarea class="form-control" id="upgradeReason" name="reason" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="preferredDate" class="form-label">Preferred Installation Date</label>
                        <input type="date" class="form-control" id="preferredDate" name="preferred_date" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="upgradeForm" class="btn btn-primary">Submit Request</button>
            </div>
        </div>
    </div>
</div>

<script>
function requestUpgrade(planId) {
    document.getElementById('upgradePlanId').value = planId;
    new bootstrap.Modal(document.getElementById('upgradePlanModal')).show();
}

// Set minimum date for preferred installation date
document.getElementById('preferredDate').min = new Date().toISOString().split('T')[0];
</script>

<?php require_once '../includes/footer.php'; ?>
