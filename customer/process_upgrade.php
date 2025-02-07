<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    error_log("Unauthorized upgrade request attempt");
    header('Location: ../login.php');
    exit();
}

// Function to log upgrade request errors
function logUpgradeError($message, $user_id = null) {
    error_log(sprintf(
        "Upgrade Request Error%s: %s", 
        $user_id ? " (User ID: $user_id)" : "", 
        $message
    ));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user_id = $_SESSION['user_id'];
        $plan_id = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT);
        $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
        $preferred_date = filter_input(INPUT_POST, 'preferred_date', FILTER_SANITIZE_STRING);

        // Validate inputs
        if (!$plan_id || empty($reason) || empty($preferred_date)) {
            logUpgradeError("Invalid input data", $user_id);
            $_SESSION['error'] = "Invalid or missing input. Please fill all fields correctly.";
            header('Location: subscription.php');
            exit();
        }

        // Begin transaction for atomic operations
        $conn->begin_transaction();

        // Get customer ID
        $stmt = $conn->prepare("SELECT id FROM customers WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();

        if (!$customer) {
            logUpgradeError("Customer not found", $user_id);
            throw new Exception("Customer profile not found.");
        }

        // Check if an active upgrade request already exists
        $stmt = $conn->prepare("SELECT id FROM upgrade_requests WHERE customer_id = ? AND status = 'pending'");
        $stmt->bind_param('i', $customer['id']);
        $stmt->execute();
        $existing_request = $stmt->get_result()->fetch_assoc();

        if ($existing_request) {
            logUpgradeError("Duplicate upgrade request", $user_id);
            $_SESSION['error'] = "You already have a pending upgrade request.";
            header('Location: subscription.php');
            exit();
        }

        // Create upgrade request
        $stmt = $conn->prepare("INSERT INTO upgrade_requests 
            (customer_id, plan_id, reason, preferred_date, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param('iiss', $customer['id'], $plan_id, $reason, $preferred_date);

        if (!$stmt->execute()) {
            $error_details = [
                'errno' => $stmt->errno,
                'error' => $stmt->error,
                'sqlstate' => $stmt->sqlstate
            ];
            logUpgradeError("Failed to insert upgrade request: " . json_encode($error_details), $user_id);
            throw new Exception("Failed to submit upgrade request: " . $stmt->error);
        }

        // Create a support ticket for the upgrade request
        $ticket_description = sprintf(
            "Customer Plan Upgrade Request\n\nReason: %s\nPreferred Date: %s", 
            $reason, 
            $preferred_date
        );
        
        $stmt = $conn->prepare("INSERT INTO support_tickets 
            (customer_id, subject, message, priority, status, created_at) 
            VALUES (?, 'Plan Upgrade Request', ?, 'medium', 'open', NOW())");
        $stmt->bind_param('is', $customer['id'], $ticket_description);

        if (!$stmt->execute()) {
            $error_details = [
                'errno' => $stmt->errno,
                'error' => $stmt->error,
                'sqlstate' => $stmt->sqlstate
            ];
            logUpgradeError("Failed to create support ticket: " . json_encode($error_details), $user_id);
            throw new Exception("Failed to create support ticket: " . $stmt->error);
        }

        // Commit transaction
        $conn->commit();

        // Log successful upgrade request
        error_log(sprintf(
            "Upgrade Request Submitted Successfully (User ID: %d, Customer ID: %d, Plan ID: %d)", 
            $user_id, 
            $customer['id'], 
            $plan_id
        ));

        $_SESSION['success'] = "Your upgrade request has been submitted successfully. Our team will contact you soon.";
    } catch (Exception $e) {
        // Rollback transaction in case of error
        $conn->rollback();

        // Log the full exception details
        error_log("Full Exception Details: " . $e->getMessage() . "\n" . $e->getTraceAsString());

        logUpgradeError($e->getMessage(), $user_id);
        $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    } finally {
        header('Location: subscription.php');
        exit();
    }
} else {
    header('Location: subscription.php');
    exit();
}
?>
