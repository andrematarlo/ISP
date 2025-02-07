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

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$ticket_id) {
    header('Location: tickets.php');
    exit();
}

// Verify ticket belongs to customer
$sql = "SELECT * FROM support_tickets WHERE id = ? AND customer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $ticket_id, $customer['id']);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    header('Location: tickets.php');
    exit();
}

// Handle new reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $reply = trim($_POST['reply']);
    
    if (!empty($reply)) {
        $sql = "INSERT INTO ticket_replies (ticket_id, customer_id, message, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iis', $ticket_id, $customer['id'], $reply);
        
        if ($stmt->execute()) {
            $reply_id = $conn->insert_id;
            
            // Send notification
            $notifications = new TicketNotifications($conn);
            $notifications->sendReplyNotification($reply_id);
            
            // Update ticket updated_at timestamp
            $sql = "UPDATE support_tickets SET updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $ticket_id);
            $stmt->execute();
            
            $_SESSION['success'] = "Reply added successfully!";
        } else {
            $_SESSION['error'] = "Error adding reply.";
        }
    }
    header('Location: view_ticket.php?id=' . $ticket_id);
    exit();
}

// Get ticket replies
$sql = "SELECT r.*, 
        CASE 
            WHEN r.user_id IS NOT NULL THEN u.username 
            ELSE c.full_name 
        END as author_name,
        CASE 
            WHEN r.user_id IS NOT NULL THEN 'admin' 
            ELSE 'customer' 
        END as author_type
        FROM ticket_replies r 
        LEFT JOIN users u ON r.user_id = u.id 
        LEFT JOIN customers c ON r.customer_id = c.id 
        WHERE r.ticket_id = ? 
        ORDER BY r.created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$replies = $stmt->get_result();

// Get success/error messages
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<?php require_once '../includes/customer_header.php'; ?>

<main class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Ticket #<?php echo $ticket_id; ?></h2>
        <a href="tickets.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to My Tickets
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Ticket Details -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Ticket Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?php echo [
                                'open' => 'danger',
                                'in_progress' => 'warning',
                                'resolved' => 'success',
                                'closed' => 'secondary'
                            ][$ticket['status']] ?? 'secondary'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                            </span>
                        </dd>

                        <dt class="col-sm-4">Priority</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?php echo [
                                'high' => 'danger',
                                'medium' => 'warning',
                                'low' => 'info'
                            ][$ticket['priority']] ?? 'secondary'; ?>">
                                <?php echo ucfirst($ticket['priority']); ?>
                            </span>
                        </dd>

                        <dt class="col-sm-4">Created</dt>
                        <dd class="col-sm-8"><?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></dd>

                        <dt class="col-sm-4">Updated</dt>
                        <dd class="col-sm-8"><?php echo date('M j, Y g:i A', strtotime($ticket['updated_at'])); ?></dd>
                    </dl>
                </div>
            </div>

            <?php if ($ticket['status'] === 'resolved'): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Ticket Resolved</h5>
                    <p>If your issue has been resolved, you can close this ticket. If you're still experiencing issues, please add a reply below.</p>
                    <form method="POST" action="close_ticket.php">
                        <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                        <button type="submit" name="close_ticket" class="btn btn-success">
                            <i class="fas fa-check-circle"></i> Close Ticket
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Ticket Content and Replies -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($ticket['subject']); ?></h5>
                </div>
                <div class="card-body">
                    <div class="ticket-reply reply-customer">
                        <div class="reply-meta mb-2">
                            You · <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                        </div>
                        <div class="reply-content">
                            <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
                        </div>
                    </div>

                    <?php while ($reply = $replies->fetch_assoc()): ?>
                        <div class="ticket-reply reply-<?php echo $reply['author_type']; ?>">
                            <div class="reply-meta mb-2">
                                <?php echo $reply['author_type'] === 'admin' ? 
                                      htmlspecialchars($reply['author_name']) . ' (Support)' : 
                                      'You'; ?> · 
                                <?php echo date('M j, Y g:i A', strtotime($reply['created_at'])); ?>
                            </div>
                            <div class="reply-content">
                                <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>

                    <?php if ($ticket['status'] !== 'closed'): ?>
                        <form method="POST" class="mt-4">
                            <div class="mb-3">
                                <label for="reply" class="form-label">Add Reply</label>
                                <textarea class="form-control" id="reply" name="reply" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Reply
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-secondary mt-4">
                            This ticket is closed. If you need further assistance, please create a new ticket.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/your-font-awesome-kit.js"></script>
</body>
</html>
