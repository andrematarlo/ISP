<?php
// Enable detailed error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';

// Ensure log directory exists
$log_dir = 'C:\xampp\htdocs\ISPSystem\logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}

// Enhanced logging function
function logPlanAction($plan_id, $action, $success = true, $error_message = '') {
    $log_message = sprintf(
        "PLAN_ACTION: PlanID=%d, Action=%s, Success=%s, Error=%s, AdminID=%d, Timestamp=%s",
        $plan_id,
        $action,
        $success ? 'YES' : 'NO',
        $error_message,
        $_SESSION['user_id'] ?? 0,
        date('Y-m-d H:i:s')
    );
    error_log($log_message);
    
    // Use the new log directory
    $log_file = 'C:\xampp\htdocs\ISPSystem\logs\plan_actions.log';
    file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
}

// Validate admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    logPlanAction(0, 'unauthorized_access', false, 'Unauthorized access attempt');
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logPlanAction(0, 'invalid_method', false, 'Invalid request method');
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

// Updated input sanitization
$action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
$plan_id = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT);

// Debug logging
error_log("Received POST data: " . print_r($_POST, true));

try {
    // Add new plan or update existing plan
    if ($action === 'add' || $action === 'update') {
        // Updated input sanitization
        $name = filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        $speed = filter_input(INPUT_POST, 'speed', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $description = filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);

        // Validate inputs
        if (!$name || !$speed || $price === false) {
            error_log("Invalid input parameters: " . print_r([
                'name' => $name,
                'speed' => $speed,
                'price' => $price,
                'description' => $description
            ], true));

            logPlanAction(0, $action, false, 'Invalid input parameters');
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid input parameters',
                'details' => [
                    'name' => $name ? 'Valid' : 'Invalid',
                    'speed' => $speed ? 'Valid' : 'Invalid',
                    'price' => $price !== false ? 'Valid' : 'Invalid'
                ]
            ]);
            exit();
        }

        if ($action === 'add') {
            // Prepare insert statement
            $stmt = $conn->prepare("INSERT INTO plans (name, speed, price, description, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->bind_param('ssds', $name, $speed, $price, $description);
            
            if ($stmt->execute()) {
                $new_plan_id = $stmt->insert_id;
                logPlanAction($new_plan_id, 'add', true);
                header('Content-Type: application/json');
                echo json_encode([
                    'message' => 'Plan added successfully', 
                    'plan_id' => $new_plan_id
                ]);
            } else {
                error_log("Failed to add plan: " . $stmt->error);
                logPlanAction(0, 'add', false, $stmt->error);
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['error' => 'Failed to add plan', 'details' => $stmt->error]);
            }
            $stmt->close();
        } else {
            // Update existing plan
            if (!$plan_id) {
                error_log("Update failed: Invalid plan ID");
                logPlanAction(0, 'update', false, 'Invalid plan ID');
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['error' => 'Invalid plan ID']);
                exit();
            }

            // Prepare update statement
            $stmt = $conn->prepare("UPDATE plans SET name = ?, speed = ?, price = ?, description = ? WHERE id = ?");
            $stmt->bind_param('ssdsi', $name, $speed, $price, $description, $plan_id);
            
            if ($stmt->execute()) {
                logPlanAction($plan_id, 'update', true);
                header('Content-Type: application/json');
                echo json_encode(['message' => 'Plan updated successfully']);
            } else {
                error_log("Failed to update plan: " . $stmt->error);
                logPlanAction($plan_id, 'update', false, $stmt->error);
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update plan', 'details' => $stmt->error]);
            }
            $stmt->close();
        }
    } 
    // Change plan status (soft delete)
    elseif ($action === 'change_status') {
        if (!$plan_id) {
            logPlanAction(0, 'change_status', false, 'Invalid plan ID');
            http_response_code(400);
            echo json_encode(['error' => 'Invalid plan ID']);
            exit();
        }

        // Get current status
        $status_stmt = $conn->prepare("SELECT status FROM plans WHERE id = ?");
        $status_stmt->bind_param('i', $plan_id);
        $status_stmt->execute();
        $result = $status_stmt->get_result();
        
        if ($result->num_rows === 0) {
            logPlanAction($plan_id, 'change_status', false, 'Plan not found');
            http_response_code(404);
            echo json_encode(['error' => 'Plan not found']);
            exit();
        }

        $current_plan = $result->fetch_assoc();
        $new_status = $current_plan['status'] === 'active' ? 'inactive' : 'active';
        $status_stmt->close();

        // Update status
        $update_stmt = $conn->prepare("UPDATE plans SET status = ? WHERE id = ?");
        $update_stmt->bind_param('si', $new_status, $plan_id);
        
        if ($update_stmt->execute()) {
            logPlanAction($plan_id, 'change_status', true, "Status changed to $new_status");
            header('Content-Type: application/json');
            echo json_encode([
                'message' => 'Plan status updated successfully', 
                'new_status' => $new_status
            ]);
        } else {
            logPlanAction($plan_id, 'change_status', false, $update_stmt->error);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update plan status']);
        }
        $update_stmt->close();
    } 
    else {
        // Invalid action
        logPlanAction(0, 'invalid_action', false, "Unknown action: $action");
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    // Catch any unexpected errors
    logPlanAction($plan_id ?? 0, 'exception', false, $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred']);
} finally {
    // Close database connection
    $conn->close();
    exit();
}
?>
