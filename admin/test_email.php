<?php
require_once '../config/database.php';
require_once '../includes/mail_helper.php';

// Test email settings
$settings = [];
$sql = "SELECT setting_key, setting_value FROM settings";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Test customer email
$sql = "SELECT email FROM customers LIMIT 1";
$result = $conn->query($sql);
if (!$result) {
    die("Error: Email column not found in customers table");
}

$customer = $result->fetch_assoc();
if (!$customer || empty($customer['email'])) {
    die("Error: No customer email found");
}

// Test sending email
try {
    $sent = sendBillEmail(
        $customer['email'],
        'Test Customer',
        1000.00,
        date('Y-m-d', strtotime('+15 days')),
        date('Y-m')
    );
    
    if ($sent) {
        echo "Test email sent successfully to " . $customer['email'];
    } else {
        echo "Failed to send test email";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
