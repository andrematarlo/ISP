<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Unauthorized access";
    exit();
}

// Check if required parameters are present
if (!isset($_POST['bill_id']) || !isset($_POST['status']) || !isset($_POST['update_status'])) {
    http_response_code(400);
    echo "Missing required parameters";
    exit();
}

$bill_id = (int)$_POST['bill_id'];
$new_status = $_POST['status'];

try {
    // Start transaction
    $conn->begin_transaction();

    // Update bill status
    $sql = "UPDATE bills SET status = ?, paid_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $new_status, $bill_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Error updating bill status");
    }

    // Log the payment
    $log_sql = "INSERT INTO activity_log (user_id, action, details) VALUES (?, 'mark_bill_paid', ?)";
    $log_details = json_encode([
        'bill_id' => $bill_id,
        'status' => $new_status,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param('is', $_SESSION['user_id'], $log_details);
    $log_stmt->execute();

    // Commit transaction
    $conn->commit();
    
    // Send success response
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Bill status updated successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log error
    error_log("Error updating bill status: " . $e->getMessage());
    
    // Send error response
    http_response_code(500);
    echo "Error updating bill status: " . $e->getMessage();
}
