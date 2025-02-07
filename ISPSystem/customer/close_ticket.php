<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get customer ID for the logged-in user
$sql = "SELECT id FROM customers WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

if (!$customer) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    
    // Verify ticket belongs to customer and is resolved
    $sql = "SELECT * FROM support_tickets WHERE id = ? AND customer_id = ? AND status = 'resolved'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $ticket_id, $customer['id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Close the ticket
        $sql = "UPDATE support_tickets SET status = 'closed', updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $ticket_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Ticket has been closed successfully.";
        } else {
            $_SESSION['error'] = "Error closing ticket.";
        }
    } else {
        $_SESSION['error'] = "Invalid ticket or ticket cannot be closed.";
    }
}

header('Location: view_ticket.php?id=' . $ticket_id);
exit();
?>
