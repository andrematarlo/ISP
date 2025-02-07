<?php
session_start();
require_once '../config/database.php';
require_once '../includes/ticket_notifications.php';

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

// Handle ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get customer ID from session or database
    $customer_id_query = "SELECT id FROM customers WHERE user_id = ?";
    $customer_stmt = $conn->prepare($customer_id_query);
    $customer_stmt->bind_param('i', $_SESSION['user_id']);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();
    $customer = $customer_result->fetch_assoc();
    $customer_id = $customer['id'];

    // Prepare ticket insertion
    $insert_ticket_sql = "INSERT INTO support_tickets (customer_id, subject, message, priority, status) 
                          VALUES (?, ?, ?, ?, 'open')";
    $insert_stmt = $conn->prepare($insert_ticket_sql);
    $insert_stmt->bind_param('isss', 
        $customer_id, 
        $_POST['subject'], 
        $_POST['message'], 
        $_POST['priority']
    );

    try {
        $insert_result = $insert_stmt->execute();
        
        if ($insert_result) {
            $ticket_id = $conn->insert_id;
            
            // Send notifications
            $notifications = new TicketNotifications($conn);
            $notifications->sendNewTicketNotification($ticket_id);
            
            $_SESSION['success'] = "Support ticket created successfully!";
            header("Location: tickets.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to create support ticket. Please try again.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get success/error messages
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<?php require_once '../includes/customer_header.php'; ?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="text-center">Create Support Ticket</h3>
                </div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>

                        <div class="mb-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority" required>
                                <option value="">Select Priority</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="attachment" class="form-label">Attachment (Optional)</label>
                            <input type="file" class="form-control" id="attachment" name="attachment">
                            <small class="text-muted">Maximum file size: 5MB. Allowed types: jpg, jpeg, png, pdf, doc, docx</small>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Submit Ticket
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/customer_footer.php'; ?>
