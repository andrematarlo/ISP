<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bill_id = isset($_POST['bill_id']) ? (int)$_POST['bill_id'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $gcash_number = isset($_POST['gcash_number']) ? trim($_POST['gcash_number']) : '';
    $reference_number = isset($_POST['gcash_reference']) ? trim($_POST['gcash_reference']) : '';
    $payment_date = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : date('Y-m-d H:i:s');

    // Validate inputs
    if (!$bill_id || !$amount || !$gcash_number || !$reference_number) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: view_bill.php?id=$bill_id");
        exit();
    }

    // Insert payment request into payments table
    $sql = "INSERT INTO payments (
        bill_id, 
        amount, 
        payment_method, 
        gcash_number, 
        reference_number, 
        payment_date, 
        status
    ) VALUES (?, ?, 'gcash', ?, ?, ?, 'pending')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'idsss', 
        $bill_id, 
        $amount, 
        $gcash_number, 
        $reference_number, 
        $payment_date
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = "Payment request submitted successfully. Admin will review and confirm your payment.";
    } else {
        $_SESSION['error'] = "Failed to submit payment request. Please try again.";
    }

    header("Location: view_bill.php?id=$bill_id");
    exit();
} else {
    header('Location: bills.php');
    exit();
}
