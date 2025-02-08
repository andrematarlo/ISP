<?php
// Prevent PHP from outputting errors as HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session and set content type to JSON
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Include database connection
require_once '../config/database.php';

// Initialize response
$response = [
    'success' => false,
    'message' => ''
];

try {
    // Check if activity_log table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'activity_log'");
    if ($table_check->num_rows === 0) {
        // Create activity_log table
        $create_table_sql = "CREATE TABLE activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
        $conn->query($create_table_sql);
    }

    // Log all incoming POST data for debugging
    error_log("Incoming Subscription Cancellation Request: " . json_encode($_POST));

    // Check if subscription ID is provided
    if (!isset($_POST['subscription_id']) || empty($_POST['subscription_id'])) {
        throw new Exception("Subscription ID is required.");
    }

    $subscription_id = intval($_POST['subscription_id']);
    $cancel_reason = $_POST['cancel_reason'] ?? 'Admin Cancellation';

    // Start transaction
    $conn->begin_transaction();

    // First check if subscription exists and is active
    $check_sql = "SELECT id, status FROM subscriptions WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $subscription_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Subscription not found.");
    }
    
    $subscription = $result->fetch_assoc();
    if ($subscription['status'] === 'cancelled') {
        throw new Exception("Subscription is already cancelled.");
    }

    // Update subscription status
    $update_sql = "UPDATE subscriptions 
                   SET status = 'cancelled',
                       end_date = NOW(),
                       cancel_reason = ?,
                       cancelled_by = ?
                   WHERE id = ? AND status != 'cancelled'";
                   
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('sii', $cancel_reason, $_SESSION['user_id'], $subscription_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to cancel subscription: " . $update_stmt->error);
    }
    
    if ($update_stmt->affected_rows === 0) {
        throw new Exception("No changes made. Subscription may already be cancelled.");
    }

    // Log the cancellation
    $log_sql = "INSERT INTO activity_log (user_id, action, details) 
                VALUES (?, 'cancel_subscription', ?)";
    $log_details = json_encode([
        'subscription_id' => $subscription_id,
        'cancel_reason' => $cancel_reason,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param('is', $_SESSION['user_id'], $log_details);
    
    if (!$log_stmt->execute()) {
        // Log error but don't stop the process
        error_log("Failed to log subscription cancellation: " . $log_stmt->error);
    }

    // Commit transaction
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = "Subscription cancelled successfully.";
    
} catch (Exception $e) {
    // Rollback transaction
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Log error to file
    error_log("Subscription cancellation error: " . $e->getMessage());
}

// Send JSON response
echo json_encode($response);
exit();
?>
