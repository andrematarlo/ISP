<?php
require_once '../config/database.php';

class TicketNotifications {
    private $conn;
    private $company_name = "ISP Provider";
    private $support_email = "support@ispprovider.com";

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Send notification for new ticket
    public function sendNewTicketNotification($ticket_id) {
        $sql = "SELECT t.*, c.full_name, u.email 
                FROM support_tickets t 
                JOIN customers c ON t.customer_id = c.id 
                JOIN users u ON c.user_id = u.id
                WHERE t.id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $ticket_id);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();

        if (!$ticket) return false;

        // Email to admin
        $admin_subject = "New Support Ticket #{$ticket_id} - {$ticket['subject']}";
        $admin_message = $this->getAdminNewTicketTemplate($ticket);
        
        // Email to customer
        $customer_subject = "Your Support Ticket #{$ticket_id} Has Been Created";
        $customer_message = $this->getCustomerNewTicketTemplate($ticket);

        // Send emails
        $this->sendEmail($this->support_email, $admin_subject, $admin_message);
        $this->sendEmail($ticket['email'], $customer_subject, $customer_message);

        return true;
    }

    // Send notification for new reply
    public function sendReplyNotification($reply_id) {
        $sql = "SELECT r.*, t.subject, t.customer_id,
                c.full_name, u.email as customer_email,
                a.email as admin_email
                FROM ticket_replies r 
                JOIN support_tickets t ON r.ticket_id = t.id
                JOIN customers c ON t.customer_id = c.id
                JOIN users u ON c.user_id = u.id
                LEFT JOIN users a ON r.user_id = a.id
                WHERE r.id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $reply_id);
        $stmt->execute();
        $reply = $stmt->get_result()->fetch_assoc();

        if (!$reply) return false;

        $recipient_email = $reply['user_id'] ? $reply['customer_email'] : $this->support_email;
        $subject = "New Reply to Ticket #{$reply['ticket_id']}";
        $message = $this->getReplyNotificationTemplate($reply);

        return $this->sendEmail($recipient_email, $subject, $message);
    }

    // Send status update notification
    public function sendStatusUpdateNotification($ticket_id, $new_status) {
        $sql = "SELECT t.*, c.full_name, u.email 
                FROM support_tickets t 
                JOIN customers c ON t.customer_id = c.id 
                JOIN users u ON c.user_id = u.id
                WHERE t.id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $ticket_id);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();

        if (!$ticket) return false;

        $subject = "Ticket #{$ticket_id} Status Updated";
        $message = $this->getStatusUpdateTemplate($ticket, $new_status);

        return $this->sendEmail($ticket['email'], $subject, $message);
    }

    // Send notification for ticket response
    public function sendTicketResponseNotification($ticket_id) {
        // Fetch ticket and response details
        $sql = "SELECT t.id, t.subject, t.customer_id, 
                       c.full_name, u.email as customer_email, 
                       tr.message as response_message, 
                       tr.created_at as response_time
                FROM support_tickets t 
                JOIN customers c ON t.customer_id = c.id 
                JOIN users u ON c.user_id = u.id
                JOIN ticket_responses tr ON tr.ticket_id = t.id
                WHERE t.id = ?
                ORDER BY tr.created_at DESC
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $ticket_id);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();

        if (!$ticket) return false;

        // Email to customer
        $customer_subject = "Response to Support Ticket #{$ticket_id} - {$ticket['subject']}";
        $customer_message = $this->getCustomerTicketResponseTemplate($ticket);

        // Send email
        $this->sendEmail($ticket['customer_email'], $customer_subject, $customer_message);

        return true;
    }

    // Send notification for ticket resolution
    public function sendTicketResolvedNotification($ticket_id) {
        // Fetch ticket details
        $sql = "SELECT t.id, t.subject, t.customer_id, 
                       c.full_name, u.email as customer_email
                FROM support_tickets t 
                JOIN customers c ON t.customer_id = c.id 
                JOIN users u ON c.user_id = u.id
                WHERE t.id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $ticket_id);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();

        if (!$ticket) return false;

        // Email to customer
        $customer_subject = "Support Ticket #{$ticket_id} Has Been Resolved";
        $customer_message = $this->getCustomerTicketResolvedTemplate($ticket);

        // Send email
        $this->sendEmail($ticket['customer_email'], $customer_subject, $customer_message);

        return true;
    }

    private function getAdminNewTicketTemplate($ticket) {
        return "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2>New Support Ticket #{$ticket['id']}</h2>
                <p><strong>Customer:</strong> {$ticket['full_name']}</p>
                <p><strong>Subject:</strong> {$ticket['subject']}</p>
                <p><strong>Priority:</strong> " . ucfirst($ticket['priority']) . "</p>
                <p><strong>Message:</strong></p>
                <div style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>
                    " . nl2br(htmlspecialchars($ticket['message'])) . "
                </div>
                <p>
                    <a href='http://localhost/ISPSystem/admin/view_ticket.php?id={$ticket['id']}' 
                       style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        View Ticket
                    </a>
                </p>
            </div>
        </body>
        </html>";
    }

    private function getCustomerNewTicketTemplate($ticket) {
        return "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2>Your Support Ticket Has Been Created</h2>
                <p>Dear {$ticket['full_name']},</p>
                <p>Your support ticket has been created successfully. Our team will review it shortly.</p>
                <div style='background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>Ticket Number:</strong> #{$ticket['id']}</p>
                    <p><strong>Subject:</strong> {$ticket['subject']}</p>
                    <p><strong>Priority:</strong> " . ucfirst($ticket['priority']) . "</p>
                    <p><strong>Status:</strong> " . ucfirst($ticket['status']) . "</p>
                </div>
                <p>We'll notify you when our support team responds to your ticket.</p>
                <p>
                    <a href='http://localhost/ISPSystem/customer/view_ticket.php?id={$ticket['id']}'
                       style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        View Ticket
                    </a>
                </p>
                <p>Thank you for contacting us.</p>
            </div>
        </body>
        </html>";
    }

    private function getReplyNotificationTemplate($reply) {
        return "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2>New Reply to Ticket #{$reply['ticket_id']}</h2>
                <p>A new reply has been added to your ticket regarding: {$reply['subject']}</p>
                <div style='background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    " . nl2br(htmlspecialchars($reply['message'])) . "
                </div>
                <p>
                    <a href='http://localhost/ISPSystem/" . 
                    ($reply['user_id'] ? 'customer' : 'admin') . 
                    "/view_ticket.php?id={$reply['ticket_id']}'
                       style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        View Ticket
                    </a>
                </p>
            </div>
        </body>
        </html>";
    }

    private function getStatusUpdateTemplate($ticket, $new_status) {
        return "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2>Ticket #{$ticket['id']} Status Updated</h2>
                <p>Dear {$ticket['full_name']},</p>
                <p>The status of your ticket has been updated to: " . ucfirst(str_replace('_', ' ', $new_status)) . "</p>
                <div style='background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>Subject:</strong> {$ticket['subject']}</p>
                    <p><strong>New Status:</strong> " . ucfirst(str_replace('_', ' ', $new_status)) . "</p>
                </div>
                <p>
                    <a href='http://localhost/ISPSystem/customer/view_ticket.php?id={$ticket['id']}'
                       style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        View Ticket
                    </a>
                </p>
            </div>
        </body>
        </html>";
    }

    private function getCustomerTicketResponseTemplate($ticket) {
        $response_time = date('M j, Y g:i A', strtotime($ticket['response_time']));
        
        return "Dear {$ticket['full_name']},

We have responded to your support ticket #{$ticket['id']} regarding '{$ticket['subject']}'.

Response Details:
Date: {$response_time}
Message: {$ticket['response_message']}

Please log in to our support portal to view the full conversation.

Best regards,
{$this->company_name} Support Team";
    }

    private function getCustomerTicketResolvedTemplate($ticket) {
        return "Dear {$ticket['full_name']},

We are writing to inform you that your support ticket #{$ticket['id']} regarding '{$ticket['subject']}' has been resolved.

If you have any further questions or concerns, please don't hesitate to contact our support team.

Best regards,
{$this->company_name} Support Team";
    }

    private function sendEmail($to, $subject, $message) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->company_name . ' <' . $this->support_email . '>',
            'Reply-To: ' . $this->support_email,
            'X-Mailer: PHP/' . phpversion()
        ];

        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
}
?>
