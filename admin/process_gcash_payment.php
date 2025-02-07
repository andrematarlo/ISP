<?php
session_start();
require_once '../config/database.php';
require_once 'send_payment_notification.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bill_id = isset($_POST['bill_id']) ? (int)$_POST['bill_id'] : 0;
    $gcash_reference = $conn->real_escape_string($_POST['gcash_reference']);
    $amount = floatval($_POST['amount']);
    $payment_date = $conn->real_escape_string($_POST['payment_date']);
    $gcash_number = $conn->real_escape_string($_POST['gcash_number']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Verify bill exists and is unpaid
        $sql = "SELECT b.*, c.full_name 
                FROM bills b 
                JOIN subscriptions s ON b.subscription_id = s.id 
                JOIN customers c ON s.customer_id = c.id 
                WHERE b.id = ? AND b.status = 'unpaid'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $bill_id);
        $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc();

        if (!$bill) {
            throw new Exception("Invalid bill or bill is already paid.");
        }

        // Verify payment amount matches bill amount
        if ($amount != $bill['amount']) {
            throw new Exception("Payment amount does not match bill amount.");
        }

        // Insert payment record
        $sql = "INSERT INTO payments (bill_id, amount, payment_method, reference_number, gcash_number, payment_date, status, created_at) 
                VALUES (?, ?, 'gcash', ?, ?, NOW(), 'pending', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('idss', $bill_id, $amount, $gcash_reference, $gcash_number);
        
        if (!$stmt->execute()) {
            throw new Exception("Error recording payment.");
        }

        // Log payment to specific GCash number
        error_log(sprintf(
            "GCash Payment Received - Bill ID: %d, Amount: %.2f, Reference: %s, Sent to: 09195700051", 
            $bill_id, 
            $amount, 
            $gcash_reference
        ));

        // Remove automatic bill status update
        // Instead, leave bill status as is until admin confirms
        // $sql = "UPDATE bills SET status = 'paid', paid_at = NOW() WHERE id = ?";
        // $stmt = $conn->prepare($sql);
        // $stmt->bind_param('i', $bill_id);
        
        // if (!$stmt->execute()) {
        //     throw new Exception("Error updating bill status.");
        // }

        // Commit transaction
        $conn->commit();

        // Send email notification about pending payment
        sendPaymentNotification($bill_id, 'pending');

        $_SESSION['success'] = "Payment submitted for review. Reference number: " . $gcash_reference;
        
        // Redirect based on user role
        if ($_SESSION['role'] === 'admin') {
            header('Location: view_bill.php?id=' . $bill_id);
        } else {
            header('Location: ../customer/view_bill.php?id=' . $bill_id);
        }
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        
        // Redirect based on user role
        if ($_SESSION['role'] === 'admin') {
            header('Location: view_bill.php?id=' . $bill_id);
        } else {
            header('Location: ../customer/view_bill.php?id=' . $bill_id);
        }
        exit();
    }
}
?>
