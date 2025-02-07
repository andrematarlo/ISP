<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../login.php');
    exit();
}

// Get user and customer details
$user_id = $_SESSION['user_id'];
$sql = "SELECT u.*, c.* 
        FROM users u
        JOIN customers c ON u.id = c.user_id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle profile update
$update_error = '';
$update_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and update profile
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validate inputs
    if (empty($full_name) || empty($phone) || empty($address) || empty($email)) {
        $update_error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $update_error = "Invalid email format.";
    } else {
        // Update customer details
        $update_sql = "UPDATE customers c
                       JOIN users u ON c.user_id = u.id
                       SET c.full_name = ?, c.phone = ?, c.address = ?, u.email = ?
                       WHERE c.user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('ssssi', $full_name, $phone, $address, $email, $user_id);
        
        if ($update_stmt->execute()) {
            $update_success = "Profile updated successfully!";
            // Refresh user data
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $update_error = "Error updating profile. Please try again.";
        }
    }
}

// Check for password change messages
$password_change_errors = isset($_SESSION['password_change_errors']) ? 
    $_SESSION['password_change_errors'] : [];
unset($_SESSION['password_change_errors']);

// Get active subscription
$subscription_sql = "SELECT s.*, p.name as plan_name, p.speed, p.price 
                     FROM subscriptions s
                     JOIN plans p ON s.plan_id = p.id
                     WHERE s.customer_id = ? AND s.status = 'active'
                     ORDER BY s.start_date DESC
                     LIMIT 1";
$subscription_stmt = $conn->prepare($subscription_sql);
$subscription_stmt->bind_param('i', $user['id']);
$subscription_stmt->execute();
$subscription_result = $subscription_stmt->get_result();
$subscription = $subscription_result->fetch_assoc();

require_once '../includes/customer_header.php';
?>

<main class="container py-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Profile Overview</h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-user-circle fa-5x text-muted"></i>
                    </div>
                    <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p class="text-muted mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="text-muted">
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?>
                    </p>
                </div>
            </div>

            <?php if ($subscription): ?>
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Current Subscription</h5>
                </div>
                <div class="card-body">
                    <h6 class="card-title"><?php echo htmlspecialchars($subscription['plan_name']); ?></h6>
                    <p class="card-text">
                        <strong>Speed:</strong> <?php echo htmlspecialchars($subscription['speed']); ?><br>
                        <strong>Price:</strong> ₱<?php echo number_format($subscription['price'], 2); ?><br>
                        <strong>Start Date:</strong> <?php echo date('F j, Y', strtotime($subscription['start_date'])); ?>
                    </p>
                    <a href="subscription.php" class="btn btn-outline-primary btn-sm">
                        Manage Subscription
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-8">
            <?php if ($update_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($update_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($update_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($update_success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($password_change_errors): ?>
                <?php foreach ($password_change_errors as $error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Edit Profile</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?php 
                                echo htmlspecialchars($user['address']); 
                            ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="change_password.php">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-lock"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>
