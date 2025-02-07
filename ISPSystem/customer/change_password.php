<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../login.php');
    exit();
}

// Initialize variables
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Enhanced password validation
    $password_errors = [];
    
    if (empty($current_password)) {
        $password_errors[] = "Current password is required.";
    }
    
    if (empty($new_password)) {
        $password_errors[] = "New password is required.";
    } elseif (strlen($new_password) < 8) {
        $password_errors[] = "New password must be at least 8 characters long.";
    } elseif (strlen($new_password) > 50) {
        $password_errors[] = "New password must not exceed 50 characters.";
    }
    
    // Check for common weak passwords
    $weak_passwords = ['password', '12345678', 'qwerty', 'admin'];
    if (in_array(strtolower($new_password), $weak_passwords)) {
        $password_errors[] = "Your new password is too weak. Please choose a stronger password.";
    }
    
    if (empty($confirm_password)) {
        $password_errors[] = "Password confirmation is required.";
    }
    
    if ($new_password !== $confirm_password) {
        $password_errors[] = "New passwords do not match.";
    }

    // If no validation errors, proceed with password change
    if (empty($password_errors)) {
        // Fetch user's current password
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $password_errors[] = "Current password is incorrect.";
        } else {
            // Hash new password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param('si', $new_password_hash, $user_id);

            if ($update_stmt->execute()) {
                // Log password change
                error_log("Password changed for user ID: $user_id at " . date('Y-m-d H:i:s'));
                
                // Send email notification about password change
                $email_sql = "SELECT email FROM users WHERE id = ?";
                $email_stmt = $conn->prepare($email_sql);
                $email_stmt->bind_param('i', $user_id);
                $email_stmt->execute();
                $email_result = $email_stmt->get_result();
                $user_email = $email_result->fetch_assoc()['email'];

                // Prepare email content
                $to = $user_email;
                $subject = "Password Changed - ISP Management System";
                $message = "Your account password was recently changed. If this was not you, please contact support immediately.";
                $headers = "From: noreply@ispsystem.com\r\n";
                $headers .= "Reply-To: support@ispsystem.com\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();

                // Send email (commented out as actual email sending requires proper email configuration)
                // mail($to, $subject, $message, $headers);
                
                // Destroy session and force re-login for security
                session_destroy();
                
                // Redirect to login with success message
                $_SESSION['password_changed'] = "Your password has been successfully changed. Please log in with your new password.";
                header('Location: ../login.php');
                exit();
            } else {
                $password_errors[] = "Error updating password. Please try again.";
            }
        }
    }

    // Store errors in session to display after redirect
    $_SESSION['password_change_errors'] = $password_errors;
    header('Location: profile.php');
    exit();
} else {
    // Direct access prevention
    header('Location: profile.php');
    exit();
}
?>
