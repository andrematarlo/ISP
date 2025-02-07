<?php
require_once '../config/database.php';

// Check customer emails
$sql = "SELECT id, full_name, email FROM customers WHERE email IS NOT NULL AND email != ''";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<h3>Customers with Email Addresses:</h3>";
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, Name: {$row['full_name']}, Email: {$row['email']}<br>";
    }
} else {
    echo "No customers found with email addresses.";
}

// Check SMTP settings
echo "<h3>SMTP Settings:</h3>";
$settings_sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('smtp_username', 'smtp_password', 'enable_email_notifications')";
$settings_result = $conn->query($settings_sql);

while ($setting = $settings_result->fetch_assoc()) {
    $value = $setting['setting_key'] == 'smtp_password' ? '********' : $setting['setting_value'];
    echo "{$setting['setting_key']}: {$value}<br>";
}
?>
