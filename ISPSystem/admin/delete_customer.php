<?php
session_start();
require_once '../config/database.php';

// Logging function
function logCustomerDeletion($customer_id, $success = true, $error_message = '') {
    $log_message = sprintf(
        "Customer Deletion - Customer ID: %d, Success: %s, Error: %s, Admin User ID: %d, Timestamp: %s", 
        $customer_id,
        $success ? 'Yes' : 'No',
        $error_message,
        $_SESSION['user_id'] ?? 0,
        date('Y-m-d H:i:s')
    );
    error_log($log_message);
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    logCustomerDeletion(0, false, 'Unauthorized access');
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized access']));
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logCustomerDeletion(0, false, 'Invalid request method');
    http_response_code(405);
    die(json_encode(['error' => 'Method Not Allowed']));
}

// Validate and sanitize inputs
$customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
$deletion_type = filter_input(INPUT_POST, 'delete_type', FILTER_SANITIZE_STRING);

// Validate inputs
if (!$customer_id || !in_array($deletion_type, ['soft', 'hard'])) {
    logCustomerDeletion($customer_id, false, 'Invalid input');
    http_response_code(400);
    die(json_encode(['error' => 'Invalid input']));
}

try {
    // Begin transaction
    $conn->begin_transaction();

    // Check if customer exists
    $check_stmt = $conn->prepare("SELECT id, status FROM customers WHERE id = ?");
    $check_stmt->bind_param('i', $customer_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $customer = $check_result->fetch_assoc();
    
    if (!$customer) {
        throw new Exception("Customer not found");
    }
    $check_stmt->close();

    // Perform deletion based on type
    if ($deletion_type === 'soft') {
        // Soft delete: mark as deleted or inactive
        $update_stmt = $conn->prepare("UPDATE customers SET status = 'deleted', deleted_at = NOW() WHERE id = ?");
        $update_stmt->bind_param('i', $customer_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to soft delete customer: " . $update_stmt->error);
        }
        $update_stmt->close();
    } else {
        // Hard delete: remove related records first
        $tables_to_clean = [
            'bills', 
            'subscriptions', 
            'support_tickets', 
            'ticket_replies',
            'payment_history'
        ];

        foreach ($tables_to_clean as $table) {
            $clean_stmt = $conn->prepare("DELETE FROM $table WHERE customer_id = ?");
            $clean_stmt->bind_param('i', $customer_id);
            $clean_stmt->execute();
            $clean_stmt->close();
        }

        // Delete user account
        $user_stmt = $conn->prepare("DELETE u FROM users u JOIN customers c ON c.user_id = u.id WHERE c.id = ?");
        $user_stmt->bind_param('i', $customer_id);
        $user_stmt->execute();
        $user_stmt->close();

        // Delete customer
        $delete_stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
        $delete_stmt->bind_param('i', $customer_id);
        
        if (!$delete_stmt->execute()) {
            throw new Exception("Failed to hard delete customer: " . $delete_stmt->error);
        }
        $delete_stmt->close();
    }

    // Commit transaction
    $conn->commit();

    // Log successful deletion
    logCustomerDeletion($customer_id);

    // Return success response
    http_response_code(200);
    echo json_encode([
        'message' => $deletion_type === 'soft' ? 'Customer soft deleted successfully' : 'Customer permanently deleted'
    ]);
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();

    // Log error
    logCustomerDeletion($customer_id, false, $e->getMessage());

    // Return error response
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
