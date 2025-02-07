<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

function sendAdminPaymentNotification($payment_details) {
    global $conn;
    
    // Get settings
    $settings = [];
    $sql = "SELECT setting_key, setting_value FROM settings";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    // Check if email notifications are enabled
    if (!isset($settings['enable_email_notifications']) || $settings['enable_email_notifications'] != '1') {
        error_log("Email notifications are disabled in settings");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_username'] ?? 'your-email@gmail.com';
        $mail->Password = $settings['smtp_password'] ?? 'your-app-password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom($settings['company_email'], $settings['company_name']);
        $mail->addAddress($settings['company_email']); // Send to admin email

        // Content
        $mail->isHTML(true);
        $mail->Subject = "New Payment Confirmation - Bill #{$payment_details['bill_id']}";

        // Email template
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: #007bff; color: white; padding: 20px; text-align: center;'>
                <h2 style='margin: 0;'>New Payment Confirmation</h2>
            </div>
            
            <div style='padding: 20px; background-color: #f8f9fa;'>
                <h3>Payment Details:</h3>
                <ul style='list-style: none; padding: 0;'>
                    <li><strong>Bill ID:</strong> #{$payment_details['bill_id']}</li>
                    <li><strong>Customer:</strong> {$payment_details['customer_name']}</li>
                    <li><strong>Amount:</strong> ₱" . number_format($payment_details['amount'], 2) . "</li>
                    <li><strong>Payment Date:</strong> " . date('F j, Y g:i A', strtotime($payment_details['payment_date'])) . "</li>
                    <li><strong>Payment Method:</strong> {$payment_details['payment_method']}</li>
                    <li><strong>Reference Number:</strong> {$payment_details['reference_number']}</li>
                </ul>
                
                <div style='margin-top: 20px; padding: 15px; background-color: #e9ecef; border-radius: 5px;'>
                    <p style='margin: 0;'><strong>Customer Contact:</strong></p>
                    <p style='margin: 5px 0;'>📞 {$payment_details['customer_phone']}</p>
                    <p style='margin: 5px 0;'>✉️ {$payment_details['customer_email']}</p>
                </div>
            </div>
            
            <div style='background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px; color: #6c757d;'>
                <p>This is an automated notification from your ISP Management System.</p>
            </div>
        </div>";

        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $body));

        $mail->send();
        error_log("Payment notification email sent to admin successfully");
        return true;
    } catch (Exception $e) {
        error_log("Failed to send payment notification email: {$mail->ErrorInfo}");
        return false;
    }
}
