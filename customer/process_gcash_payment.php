<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to submit a payment request.";
    header('Location: ../login.php');
    exit();
}

// Validate form submission
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header('Location: bills.php');
    exit();
}

// Validate input fields
$bill_id = isset($_POST['bill_id']) ? (int)$_POST['bill_id'] : 0;
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$gcash_number = isset($_POST['gcash_number']) ? trim($_POST['gcash_number']) : '';
$reference_number = isset($_POST['gcash_reference']) ? trim($_POST['gcash_reference']) : '';
$payment_date = isset($_POST['payment_date']) ? $_POST['payment_date'] : '';

// Validate inputs
if (!$bill_id || !$amount || !$gcash_number || !$reference_number || !$payment_date) {
    $_SESSION['error'] = "All fields are required.";
    header("Location: view_bill.php?id=$bill_id");
    exit();
}

// Validate phone number format
if (!preg_match('/^(09|\+639)\d{9}$/', $gcash_number)) {
    $_SESSION['error'] = "Invalid GCash number format.";
    header("Location: view_bill.php?id=$bill_id");
    exit();
}

// Verify bill belongs to the current user
$sql = "SELECT b.id, b.status 
        FROM bills b 
        JOIN subscriptions s ON b.subscription_id = s.id 
        JOIN customers c ON s.customer_id = c.id 
        JOIN users u ON c.user_id = u.id 
        WHERE b.id = ? AND u.id = ? AND b.status = 'unpaid'";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $bill_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Invalid bill or bill is already paid.";
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
?>
