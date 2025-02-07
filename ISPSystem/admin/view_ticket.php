<?php
session_start();
require_once '../config/database.php';
require_once '../includes/ticket_notifications.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$ticket_id = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

if (!$ticket_id) {
    header('Location: tickets.php');
    exit();
}

// Handle new reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $reply = trim($_POST['reply']);
    $user_id = $_SESSION['user_id'];
    
    if (!empty($reply)) {
        $sql = "INSERT INTO ticket_replies (ticket_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iis', $ticket_id, $user_id, $reply);
        
        if ($stmt->execute()) {
            $reply_id = $conn->insert_id;
            
            // Send notification
            $notifications = new TicketNotifications($conn);
            $notifications->sendReplyNotification($reply_id);
            
            // Update ticket status to in_progress if it's open
            $sql = "UPDATE support_tickets SET 
                    status = CASE WHEN status = 'open' THEN 'in_progress' ELSE status END,
                    updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $ticket_id);
            $stmt->execute();
            
            $_SESSION['success'] = "Reply added successfully!";
        } else {
            $_SESSION['error'] = "Error adding reply.";
        }
    }
    header('Location: view_ticket.php?ticket_id=' . $ticket_id);
    exit();
}

// Handle admin response
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debugging: Log all POST data
    error_log("===== ADMIN REPLY DEBUG START =====");
    error_log("Full POST Data: " . print_r($_POST, true));
    
    // Explicitly check for admin reply submission
    if (isset($_POST['submit_admin_reply'])) {
        // Get the response message
        $response_message = isset($_POST['admin_reply']) ? trim($_POST['admin_reply']) : '';
        
        // Log detailed response information
        error_log("Raw admin_reply value: " . ($_POST['admin_reply'] ?? 'NOT SET'));
        error_log("Trimmed response message: '$response_message'");
        error_log("Response message length: " . strlen($response_message));
        
        // Validate response message
        if ($response_message === '') {
            error_log("EMPTY RESPONSE DETECTED");
            $_SESSION['error'] = "Response message cannot be empty.";
            header("Location: view_ticket.php?ticket_id=$ticket_id");
            exit();
        }
        
        // Proceed with adding the response
        try {
            // Get admin details with error handling
            $admin_id = $_SESSION['user_id'];
            $admin_name = 'System Admin'; // Fallback name
            
            try {
                $get_admin_sql = "SELECT full_name FROM admin_users WHERE id = ?";
                $admin_stmt = $conn->prepare($get_admin_sql);
                $admin_stmt->bind_param('i', $admin_id);
                $admin_stmt->execute();
                $admin_result = $admin_stmt->get_result();
                
                if ($admin_result && $admin = $admin_result->fetch_assoc()) {
                    $admin_name = $admin['full_name'];
                }
            } catch (Exception $e) {
                error_log("Error retrieving admin details: " . $e->getMessage());
                // Continue with fallback admin name
            }
            
            // Insert response into ticket_responses table
            $insert_response_sql = "INSERT INTO ticket_responses (ticket_id, author_id, author_type, message) 
                                    VALUES (?, ?, 'admin', ?)";
            $response_stmt = $conn->prepare($insert_response_sql);
            $response_stmt->bind_param('iis', $ticket_id, $admin_id, $response_message);
            
            $insert_result = $response_stmt->execute();
            
            if ($insert_result) {
                // Update ticket status to in_progress if it's open
                $update_ticket_sql = "UPDATE support_tickets SET status = 'in_progress', updated_at = NOW() 
                                      WHERE id = ? AND status = 'open'";
                $update_stmt = $conn->prepare($update_ticket_sql);
                $update_stmt->bind_param('i', $ticket_id);
                $update_stmt->execute();
                
                // Send notification to customer
                require_once '../includes/ticket_notifications.php';
                $notifications = new TicketNotifications($conn);
                try {
                    $notifications->sendTicketResponseNotification($ticket_id);
                } catch (Exception $e) {
                    error_log("Failed to send ticket response notification: " . $e->getMessage());
                    // Continue without sending notification
                }
                
                $_SESSION['success'] = "Response added successfully.";
                header("Location: view_ticket.php?ticket_id=$ticket_id");
                exit();
            } else {
                $_SESSION['error'] = "Failed to add response. Please try again.";
                header("Location: view_ticket.php?ticket_id=$ticket_id");
                exit();
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
            error_log("Ticket response error: " . $e->getMessage());
            header("Location: view_ticket.php?ticket_id=$ticket_id");
            exit();
        }
    }
    
    error_log("===== ADMIN REPLY DEBUG END =====");
}

// Handle ticket resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_ticket'])) {
    $resolve_sql = "UPDATE support_tickets SET status = 'resolved', updated_at = NOW() WHERE id = ?";
    $resolve_stmt = $conn->prepare($resolve_sql);
    $resolve_stmt->bind_param('i', $ticket_id);
    
    if ($resolve_stmt->execute()) {
        // Send ticket resolution notification
                require_once '../includes/ticket_notifications.php';
                $notifications = new TicketNotifications($conn);
                try {
                    $notifications->sendTicketResolvedNotification($ticket_id);
                } catch (Exception $e) {
                    error_log("Failed to send ticket resolved notification: " . $e->getMessage());
                    // Continue without sending notification
                }
        
        $_SESSION['success'] = "Ticket has been resolved.";
        header("Location: tickets.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to resolve ticket. Please try again.";
    }
}

// Get ticket details
$sql = "SELECT t.*, c.full_name, u.email, c.phone 
        FROM support_tickets t 
        JOIN customers c ON t.customer_id = c.id 
        JOIN users u ON c.user_id = u.id 
        WHERE t.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    header('Location: tickets.php');
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

// Get ticket responses
try {
    $responses_sql = "SELECT tr.*, 
                      CASE 
                          WHEN tr.author_type = 'admin' THEN au.full_name 
                          ELSE c.full_name 
                      END AS responder_name
                      FROM ticket_responses tr
                      LEFT JOIN admin_users au ON tr.author_type = 'admin' AND tr.author_id = au.id
                      LEFT JOIN customers c ON tr.author_type = 'customer' AND tr.author_id = c.id
                      WHERE tr.ticket_id = ?
                      ORDER BY tr.created_at ASC";
    $responses_stmt = $conn->prepare($responses_sql);
    $responses_stmt->bind_param('i', $ticket_id);
    $responses_stmt->execute();
    $responses = $responses_stmt->get_result();
} catch (mysqli_sql_exception $e) {
    // If table doesn't exist, use existing replies
    $responses = $replies;
    error_log("Ticket responses table error: " . $e->getMessage());
}

// Get success/error messages
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<?php require_once '../includes/admin_header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Support Ticket Details</h3>
                    <div class="btn-group" role="group">
                        <a href="tickets.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Tickets
                        </a>
                        <?php if ($ticket['status'] !== 'resolved' && $ticket['status'] !== 'closed'): ?>
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#resolveTicketModal">
                                <i class="fas fa-check"></i> Resolve Ticket
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="mb-3">Ticket Information</h4>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tr>
                                        <th>Ticket ID</th>
                                        <td><?php echo htmlspecialchars($ticket['id']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Subject</th>
                                        <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Priority</th>
                                        <td>
                                            <span class="badge <?php 
                                                echo $ticket['priority'] === 'high' ? 'bg-danger' : 
                                                     ($ticket['priority'] === 'medium' ? 'bg-warning' : 'bg-secondary'); 
                                            ?>">
                                                <?php echo ucfirst(htmlspecialchars($ticket['priority'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <span class="badge <?php 
                                                echo $ticket['status'] === 'open' ? 'bg-success' : 
                                                     ($ticket['status'] === 'in_progress' ? 'bg-primary' : 'bg-secondary'); 
                                            ?>">
                                                <?php echo str_replace('_', ' ', ucfirst(htmlspecialchars($ticket['status']))); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Created At</th>
                                        <td><?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></td>
                                    </tr>
                                </table>
                            </div>

                            <h4 class="mt-4 mb-3">Message</h4>
                            <div class="card">
                                <div class="card-body">
                                    <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <h4 class="mb-3">Customer Details</h4>
                            <div class="card">
                                <div class="card-body">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($ticket['full_name']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($ticket['email']); ?></p>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($ticket['phone']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ticket Responses Section -->
                    <h4 class="mt-4 mb-3">Ticket Responses</h4>
                    <?php if ($responses->num_rows > 0): ?>
                        <div class="list-group">
                            <?php while ($response = $responses->fetch_assoc()): ?>
                                <div class="list-group-item list-group-item-action <?php 
                                    echo $response['author_type'] === 'admin' ? 'list-group-item-primary' : 'list-group-item-light'; 
                                ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1">
                                            <?php 
                                            echo htmlspecialchars(
                                                $response['author_type'] === 'admin' 
                                                    ? 'Admin: ' . $response['responder_name'] 
                                                    : 'Customer: ' . $response['responder_name']
                                            ); 
                                            ?>
                                        </h5>
                                        <small><?php echo date('M j, Y g:i A', strtotime($response['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($response['message'])); ?></p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No responses yet.</p>
                    <?php endif; ?>

                    <!-- Add Response Form -->
                    <?php if ($ticket['status'] !== 'resolved' && $ticket['status'] !== 'closed'): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h4 class="mb-0">Add Response</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <textarea class="form-control" name="admin_reply" rows="4" placeholder="Type your response here..." required></textarea>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="submit_admin_reply" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i>Send Response
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary mt-4">
                            This ticket is closed. No further replies can be added.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resolve Ticket Modal -->
<div class="modal fade" id="resolveTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Resolve Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to resolve this ticket?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="" class="d-inline">
                    <button type="submit" name="resolve_ticket" class="btn btn-success">Resolve Ticket</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>
