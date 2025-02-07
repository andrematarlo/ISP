<?php
require_once '../config/database.php';
require_once '../includes/mail_helper.php';

// Test sending email
try {
    $sent = sendBillEmail(
        'tamarloandre@gmail.com', // sending to yourself as a test
        'Test User',
        1000.00,
        date('Y-m-d', strtotime('+15 days')),
        date('Y-m')
    );
    
    if ($sent) {
        echo "Test email sent successfully! Please check your inbox.";
    } else {
        echo "Failed to send test email. Check the error log for details.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Display current settings
echo "<h3>Current SMTP Settings:</h3>";
$settings_sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('smtp_username', 'company_email', 'enable_email_notifications')";
$settings_result = $conn->query($settings_sql);

while ($setting = $settings_result->fetch_assoc()) {
    echo "{$setting['setting_key']}: {$setting['setting_value']}<br>";
}
?>
