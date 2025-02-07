<?php
// Disable error display for JSON responses
ini_set('display_errors', 0);
error_reporting(0);

// Clear any existing output buffering
ob_clean();

// Set headers for JSON response
header('Content-Type: application/json');

session_start();
require_once '../config/database.php';

// Enhanced logging function
function logStatusChange($customer_id, $new_status, $success = true, $error_message = '') {
    $log_message = sprintf(
        "STATUS_CHANGE: CustomerID=%d, NewStatus=%s, Success=%s, Error=%s, AdminID=%d, Timestamp=%s",
        $customer_id,
        $new_status,
        $success ? 'YES' : 'NO',
        $error_message,
        $_SESSION['user_id'] ?? 0,
        date('Y-m-d H:i:s')
    );
    error_log($log_message);
    file_put_contents('/tmp/customer_status_change.log', $log_message . PHP_EOL, FILE_APPEND);
}

// Detailed error logging
function logDetailedError($message, $context = []) {
    $log_entry = date('Y-m-d H:i:s') . " - ERROR: $message\n";
    $log_entry .= "Context: " . json_encode($context) . "\n";
    file_put_contents('/tmp/customer_status_error.log', $log_entry, FILE_APPEND);
}

// Check database connection
if ($conn->connect_error) {
    logDetailedError("Database Connection Failed", [
        'error' => $conn->connect_error,
        'errno' => $conn->connect_errno
    ]);
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}

// Validate session and admin role
if (!isset($_SESSION['user_id'])) {
    logDetailedError("No User Session", $_SESSION);
    http_response_code(401);
    die(json_encode(['error' => 'No active session']));
}

if ($_SESSION['role'] !== 'admin') {
    logDetailedError("Unauthorized Access", [
        'user_role' => $_SESSION['role'],
        'user_id' => $_SESSION['user_id']
    ]);
    http_response_code(403);
    die(json_encode(['error' => 'Admin access required']));
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logDetailedError("Invalid Request Method", [
        'method' => $_SERVER['REQUEST_METHOD']
    ]);
    http_response_code(405);
    die(json_encode(['error' => 'POST method required']));
}

// Log all incoming POST data (sanitized)
$safe_post = array_map('htmlspecialchars', $_POST);
logDetailedError("Incoming POST Data", $safe_post);

// Validate and sanitize inputs
$customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
$new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

// Validate inputs
if (!$customer_id) {
    logDetailedError("Invalid Customer ID", [
        'raw_customer_id' => $_POST['customer_id']
    ]);
    http_response_code(400);
    die(json_encode(['error' => 'Invalid customer ID']));
}

if (!in_array($new_status, ['active', 'inactive'])) {
    logDetailedError("Invalid Status", [
        'raw_status' => $_POST['status']
    ]);
    http_response_code(400);
    die(json_encode(['error' => 'Invalid status']));
}

try {
    // Check if customer exists and get current status
    $check_stmt = $conn->prepare("SELECT id, status FROM customers WHERE id = ?");
    $check_stmt->bind_param('i', $customer_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        logDetailedError("Customer Not Found", [
            'customer_id' => $customer_id
        ]);
        http_response_code(404);
        die(json_encode(['error' => 'Customer not found']));
    }

    $current_customer = $check_result->fetch_assoc();
    $check_stmt->close();

    // Prepare and execute update
    $stmt = $conn->prepare("UPDATE customers SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $new_status, $customer_id);
    
    if (!$stmt->execute()) {
        logDetailedError("Update Failed", [
            'customer_id' => $customer_id,
            'new_status' => $new_status,
            'mysql_error' => $stmt->error
        ]);
        http_response_code(500);
        die(json_encode(['error' => 'Failed to update status']));
    }

    // Log successful status change
    logStatusChange($customer_id, $new_status);

    // Return success response
    http_response_code(200);
    echo json_encode([
        'message' => 'Status updated successfully',
        'old_status' => $current_customer['status'],
        'new_status' => $new_status
    ]);
} catch (Exception $e) {
    // Log error
    logDetailedError("Exception in Status Update", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    // Return error response
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
} finally {
    // Close statement if it exists
    if (isset($stmt)) {
        $stmt->close();
    }
    exit(); // Ensure no additional output
}
?>
