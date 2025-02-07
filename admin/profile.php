<?php
session_start();
require_once '../config/database.php';
require_once '../includes/admin_header.php';

// Get admin's information
$user_id = $_SESSION['user_id'];
$sql = "SELECT u.*, a.full_name, a.phone, a.address 
        FROM users u 
        LEFT JOIN admin_profiles a ON u.id = a.user_id 
        WHERE u.id = ? AND u.role = 'admin'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $error = null;
    $success = null;

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update basic info
        $sql = "UPDATE users SET email = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();

        // Check if admin profile exists
        $sql = "SELECT user_id FROM admin_profiles WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $profile_exists = $stmt->get_result()->num_rows > 0;

        if ($profile_exists) {
            $sql = "UPDATE admin_profiles SET full_name = ?, phone = ?, address = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $full_name, $phone, $address, $user_id);
        } else {
            $sql = "INSERT INTO admin_profiles (user_id, full_name, phone, address) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $user_id, $full_name, $phone, $address);
        }
        $stmt->execute();

        // Handle password change if requested
        if (!empty($current_password) && !empty($new_password)) {
            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match");
            }

            // Verify current password
            $sql = "SELECT password FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $current_hash = $stmt->get_result()->fetch_assoc()['password'];

            if (!password_verify($current_password, $current_hash)) {
                throw new Exception("Current password is incorrect");
            }

            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_hash, $user_id);
            $stmt->execute();
        }

        $conn->commit();
        $success = "Profile updated successfully!";

        // Refresh admin data
        $sql = "SELECT u.*, a.full_name, a.phone, a.address 
                FROM users u 
                LEFT JOIN admin_profiles a ON u.id = a.user_id 
                WHERE u.id = ? AND u.role = 'admin'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-user-circle me-2"></i>Admin Profile</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($admin['username']); ?>" readonly>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter your full name.</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter your phone number.</div>
                            </div>

                            <div class="col-12 mb-4">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" required><?php echo htmlspecialchars($admin['address'] ?? ''); ?></textarea>
                                <div class="invalid-feedback">Please enter your address.</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3">Change Password</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                                <small class="text-muted">Leave blank to keep current password</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                <div class="invalid-feedback">Passwords do not match.</div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }

                // Password validation
                var currentPassword = form.querySelector('#current_password')
                var newPassword = form.querySelector('#new_password')
                var confirmPassword = form.querySelector('#confirm_password')

                if (currentPassword.value || newPassword.value || confirmPassword.value) {
                    if (!currentPassword.value) {
                        currentPassword.setCustomValidity('Current password is required to change password')
                        event.preventDefault()
                    } else {
                        currentPassword.setCustomValidity('')
                    }

                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match')
                        event.preventDefault()
                    } else {
                        confirmPassword.setCustomValidity('')
                    }
                }

                form.classList.add('was-validated')
            }, false)
        })
})()

// Auto-dismiss alerts after 5 seconds
window.addEventListener('DOMContentLoaded', (event) => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>
