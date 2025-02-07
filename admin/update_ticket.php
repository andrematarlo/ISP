<?php
session_start();
require_once '../config/database.php';
require_once '../includes/ticket_notifications.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $new_status = $_POST['status'];
    
    // Update ticket status
    $sql = "UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $new_status, $ticket_id);
    
    if ($stmt->execute()) {
        // Add system message for status change
        $message = "Ticket status changed to " . ucfirst(str_replace('_', ' ', $new_status));
        $sql = "INSERT INTO ticket_replies (ticket_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iis', $ticket_id, $_SESSION['user_id'], $message);
        $stmt->execute();
        
        // Send notification
        $notifications = new TicketNotifications($conn);
        $notifications->sendStatusUpdateNotification($ticket_id, $new_status);
        
        $_SESSION['success'] = "Ticket status updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating ticket status.";
    }
}

header('Location: view_ticket.php?id=' . $ticket_id);
exit();
?>
