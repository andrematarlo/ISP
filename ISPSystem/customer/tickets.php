<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../login.php');
    exit();
}

// Get customer ID
$customer_query = "SELECT id FROM customers WHERE user_id = ?";
$customer_stmt = $conn->prepare($customer_query);
$customer_stmt->bind_param('i', $_SESSION['user_id']);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer = $customer_result->fetch_assoc();

if (!$customer) {
    // Handle case where no customer found
    $tickets = [];
    $stats = [
        'total' => 0,
        'open_count' => 0,
        'in_progress' => 0,
        'resolved' => 0,
        'closed' => 0
    ];
} else {
    // Get customer's tickets
    $sql = "SELECT t.*, 
                   COUNT(r.id) as reply_count,
                   MAX(r.created_at) as last_reply
            FROM support_tickets t
            LEFT JOIN ticket_replies r ON t.id = r.ticket_id
            WHERE t.customer_id = ?
            GROUP BY t.id
            ORDER BY t.status = 'open' DESC, t.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $customer['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    // Get ticket statistics
    $stats_sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
    FROM support_tickets
    WHERE customer_id = ?";

    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param('i', $customer['id']);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
}

require_once '../includes/customer_header.php';
?>

<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Support Tickets</h2>
        <a href="create_ticket.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Ticket
        </a>
    </div>

    <!-- Ticket Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Tickets</h5>
                    <h3 class="mb-0"><?php echo $stats['total']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Open Tickets</h5>
                    <h3 class="mb-0"><?php echo $stats['open_count'] + $stats['in_progress']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Resolved</h5>
                    <h3 class="mb-0"><?php echo $stats['resolved']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Closed</h5>
                    <h3 class="mb-0"><?php echo $stats['closed']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Tickets Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">My Tickets</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="ticketsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Last Reply</th>
                            <th>Replies</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($result)) { while ($ticket = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $ticket['id']; ?></td>
                                <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $ticket['priority'] === 'high' ? 'danger' : 
                                            ($ticket['priority'] === 'medium' ? 'warning' : 'info'); 
                                    ?>">
                                        <?php echo ucfirst($ticket['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $ticket['status'] === 'open' ? 'danger' : 
                                            ($ticket['status'] === 'in_progress' ? 'warning' : 
                                            ($ticket['status'] === 'resolved' ? 'success' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></td>
                                <td>
                                    <?php 
                                    if ($ticket['last_reply']) {
                                        echo date('M j, Y g:i A', strtotime($ticket['last_reply']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $ticket['reply_count']; ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($ticket['status'] === 'resolved'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-warning reopen-ticket"
                                                    data-ticket-id="<?php echo $ticket['id']; ?>">
                                                <i class="fas fa-redo"></i> Reopen
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Add DataTables CSS -->
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<!-- Add DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#ticketsTable').DataTable({
            order: [[0, 'desc']]
        });

        // Handle ticket reopening
        $('.reopen-ticket').click(function() {
            const ticketId = $(this).data('ticket-id');
            if (confirm('Are you sure you want to reopen this ticket?')) {
                $.post('reopen_ticket.php', { ticket_id: ticketId })
                    .done(function() {
                        location.reload();
                    })
                    .fail(function() {
                        alert('Error reopening ticket');
                    });
            }
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>
