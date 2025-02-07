<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Initialize response
$response = [
    'success' => false,
    'message' => ''
];

// Log all incoming POST data for debugging
error_log("Incoming Subscription Cancellation Request: " . json_encode($_POST));

try {
    // Check if subscription ID is provided
    if (!isset($_POST['subscription_id']) || empty($_POST['subscription_id'])) {
        // Log additional details about the request
        error_log("Subscription Cancellation Error: No subscription ID provided");
        error_log("Full POST data: " . json_encode($_POST));
        error_log("Server variables: " . json_encode($_SERVER));
        throw new Exception("Subscription ID is required.");
    }

    $subscription_id = $_POST['subscription_id'];
    $cancel_reason = $_POST['cancel_reason'] ?? 'Admin Cancellation';

    // Log the specific subscription being cancelled
    error_log("Attempting to cancel subscription ID: {$subscription_id}");

    // Start transaction
    $conn->begin_transaction();

    // Fetch subscription details before cancellation
    $fetch_sql = "SELECT customer_id, plan_id FROM subscriptions WHERE id = ?";
    $fetch_stmt = $conn->prepare($fetch_sql);
    $fetch_stmt->bind_param('i', $subscription_id);
    $fetch_stmt->execute();
    $fetch_result = $fetch_stmt->get_result();
    $subscription = $fetch_result->fetch_assoc();

    if (!$subscription) {
        throw new Exception("Subscription not found.");
    }

    // Check which columns exist
    $columns_to_check = ['cancel_reason', 'cancelled_by'];
    $existing_columns = [];

    foreach ($columns_to_check as $column) {
        $check_column_sql = "SHOW COLUMNS FROM subscriptions LIKE '$column'";
        $column_result = $conn->query($check_column_sql);
        if ($column_result->num_rows > 0) {
            $existing_columns[] = $column;
        }
    }

    // Prepare dynamic update SQL
    $update_sql = "UPDATE subscriptions 
                   SET status = 'cancelled', 
                       end_date = NOW()";
    
    // Add columns to update if they exist
    if (in_array('cancel_reason', $existing_columns)) {
        $update_sql .= ", cancel_reason = ?";
    }
    if (in_array('cancelled_by', $existing_columns)) {
        $update_sql .= ", cancelled_by = ?";
    }
    
    $update_sql .= " WHERE id = ?";

    // Prepare statement dynamically
    $update_stmt = $conn->prepare($update_sql);

    // Bind parameters dynamically
    $param_types = '';
    $param_values = [];

    if (in_array('cancel_reason', $existing_columns)) {
        $param_types .= 's';
        $param_values[] = $cancel_reason;
    }
    if (in_array('cancelled_by', $existing_columns)) {
        $param_types .= 'i';
        $param_values[] = $_SESSION['user_id'];
    }
    
    // Always add subscription ID
    $param_types .= 'i';
    $param_values[] = $subscription_id;

    // Dynamically bind parameters
    $update_stmt->bind_param($param_types, ...$param_values);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to cancel subscription: " . $update_stmt->error);
    }

    // Check if subscription_cancellations table exists
    $check_table_sql = "SHOW TABLES LIKE 'subscription_cancellations'";
    $table_result = $conn->query($check_table_sql);
    $table_exists = ($table_result->num_rows > 0);

    // Create table if it doesn't exist
    if (!$table_exists) {
        $create_table_sql = "CREATE TABLE subscription_cancellations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subscription_id INT NOT NULL,
            customer_id INT NOT NULL,
            plan_id INT NOT NULL,
            cancelled_by INT NOT NULL,
            cancel_reason VARCHAR(255) NOT NULL,
            cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
            FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        if (!$conn->query($create_table_sql)) {
            error_log("Failed to create subscription_cancellations table: " . $conn->error);
        } else {
            // Create indexes
            $conn->query("CREATE INDEX idx_subscription_cancellations_customer ON subscription_cancellations(customer_id)");
            $conn->query("CREATE INDEX idx_subscription_cancellations_cancelled_at ON subscription_cancellations(cancelled_at)");
        }
    }

    // Create cancellation log
    $log_sql = "INSERT INTO subscription_cancellations 
                (subscription_id, customer_id, plan_id, cancelled_by, cancel_reason, cancelled_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param('iiiis', 
        $subscription_id, 
        $subscription['customer_id'], 
        $subscription['plan_id'], 
        $_SESSION['user_id'], 
        $cancel_reason
    );
    
    if (!$log_stmt->execute()) {
        // Log the error but don't stop the process
        error_log("Failed to log subscription cancellation: " . $log_stmt->error);
    }

    // Commit transaction
    $conn->commit();

    // Prepare response
    $response['success'] = true;
    $response['message'] = "Subscription successfully cancelled.";

    // Log the cancellation
    error_log("Subscription ID {$subscription_id} cancelled by admin {$_SESSION['user_id']}");

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();

    // Log error
    error_log("Subscription Cancellation Error: " . $e->getMessage());

    // Prepare error response
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>
