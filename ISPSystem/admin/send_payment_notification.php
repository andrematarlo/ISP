<?php
require_once '../config/database.php';

function sendEmail($to, $subject, $message) {
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/plain;charset=UTF-8" . "\r\n";
    $headers .= 'From: ISP Billing <billing@ispprovider.com>' . "\r\n";
    
    // Send email
    return mail($to, $subject, $message, $headers);
}

function sendPaymentNotification($bill_id, $status = 'completed') {
    global $conn;

    // Fetch bill and payment details
    $sql = "SELECT b.*, c.full_name, u.email, py.amount, py.payment_method, py.reference_number, py.payment_date
            FROM bills b
            JOIN subscriptions s ON b.subscription_id = s.id
            JOIN customers c ON s.customer_id = c.id
            JOIN users u ON c.user_id = u.id
            JOIN payments py ON py.bill_id = b.id
            WHERE b.id = ?
            ORDER BY py.payment_date DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $bill_id);
    $stmt->execute();
    $payment_details = $stmt->get_result()->fetch_assoc();

    // Prepare email content based on status
    $subject = $status === 'pending' 
        ? 'Payment Received - Pending Review' 
        : 'Payment Processed Successfully';

    $message = $status === 'pending' 
        ? "Dear {$payment_details['full_name']},\n\n" .
          "We have received your payment for Bill #{$bill_id}.\n" .
          "Payment is currently under review by our admin team.\n\n" .
          "Payment Details:\n" .
          "Amount: ₱" . number_format($payment_details['amount'], 2) . "\n" .
          "Method: " . ucfirst($payment_details['payment_method']) . "\n" .
          "Reference: {$payment_details['reference_number']}\n\n" .
          "We will notify you once the payment is processed.\n"
        : "Dear {$payment_details['full_name']},\n\n" .
          "Your payment for Bill #{$bill_id} has been processed successfully.\n\n" .
          "Payment Details:\n" .
          "Amount: ₱" . number_format($payment_details['amount'], 2) . "\n" .
          "Method: " . ucfirst($payment_details['payment_method']) . "\n" .
          "Reference: {$payment_details['reference_number']}\n";

    // Send email
    return sendEmail($payment_details['email'], $subject, $message);
}
?>
