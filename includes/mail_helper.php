<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

function sendBillEmail($to_email, $customer_name, $bill_amount, $due_date, $bill_month) {
    // Get settings
    global $conn;
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

    // Validate email
    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: " . $to_email);
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Enable debugging
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
        };

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
        $mail->addAddress($to_email, $customer_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Your Bill for " . date('F Y', strtotime($bill_month));

        // Email template
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: #007bff; color: white; padding: 20px; text-align: center;'>
                <h2 style='margin: 0;'>{$settings['company_name']}</h2>
            </div>
            
            <div style='padding: 20px; background-color: #f8f9fa;'>
                <p>Dear {$customer_name},</p>
                
                <p>Your bill for " . date('F Y', strtotime($bill_month)) . " has been generated.</p>
                
                <div style='background-color: white; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p style='margin: 5px 0;'><strong>Amount Due:</strong> ₱" . number_format($bill_amount, 2) . "</p>
                    <p style='margin: 5px 0;'><strong>Due Date:</strong> " . date('F j, Y', strtotime($due_date)) . "</p>
                </div>
                
                <p>Please ensure to make the payment before the due date to avoid any late fees.</p>
                
                <p>For any questions or concerns, please contact us:</p>
                <ul style='list-style: none; padding: 0;'>
                    <li>📞 {$settings['company_phone']}</li>
                    <li>✉️ {$settings['company_email']}</li>
                    <li>📍 {$settings['company_address']}</li>
                </ul>
            </div>
            
            <div style='background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px; color: #6c757d;'>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>";

        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $body));

        $mail->send();
        error_log("Email sent successfully to: " . $to_email);
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed. Mailer Error: {$mail->ErrorInfo}");
        error_log("Error details: " . $e->getMessage());
        return false;
    }
}
