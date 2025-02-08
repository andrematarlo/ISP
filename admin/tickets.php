<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle ticket status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $ticket_id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    $new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $admin_notes = filter_input(INPUT_POST, 'admin_notes', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

    if ($ticket_id && $new_status) {
        $stmt = $conn->prepare("UPDATE support_tickets 
            SET status = ?, 
                message = CONCAT(message, '\n\nAdmin Update: ', ?), 
                updated_at = NOW() 
            WHERE id = ?");
        $stmt->bind_param('ssi', $new_status, $admin_notes, $ticket_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Ticket status updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating ticket status: " . $stmt->error;
        }
        
        header('Location: tickets.php');
        exit();
    }
}

// Get ticket statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_count,
    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_count,
    SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) as medium_count,
    SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low_count
FROM support_tickets";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Map the simplified column names to the expected names
$ticket_stats = [
    'total_tickets' => $stats['total'],
    'open_tickets' => $stats['open_count'],
    'in_progress_tickets' => $stats['in_progress'],
    'resolved_tickets' => $stats['resolved'],
    'closed_tickets' => $stats['closed'],
    'urgent_priority' => $stats['urgent_count'],
    'high_priority' => $stats['high_count'],
    'medium_priority' => $stats['medium_count'],
    'low_priority' => $stats['low_count']
];

// Get all tickets with customer information
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : '';

$sql = "SELECT t.*, c.full_name, u.email 
        FROM support_tickets t 
        JOIN customers c ON t.customer_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE 1=1";

if (!empty($filter_status)) {
    $sql .= " AND t.status = '" . $conn->real_escape_string($filter_status) . "'";
}
if (!empty($filter_priority)) {
    $sql .= " AND t.priority = '" . $conn->real_escape_string($filter_priority) . "'";
}

$sql .= " ORDER BY t.created_at DESC";
$result = $conn->query($sql);

// Include header
require_once '../includes/admin_header.php';
?>

<main class="container">
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Tickets</h5>
                    <h3 class="mb-0"><?php echo $ticket_stats['total_tickets']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Open Tickets</h5>
                    <h3 class="mb-0"><?php echo $ticket_stats['open_tickets'] + $ticket_stats['in_progress_tickets']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Resolved Tickets</h5>
                    <h3 class="mb-0"><?php echo $ticket_stats['resolved_tickets']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Closed Tickets</h5>
                    <h3 class="mb-0"><?php echo $ticket_stats['closed_tickets']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Support Tickets</h5>
            <div class="btn-group">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" 
                   class="btn btn-outline-primary <?php echo empty($filter_status) ? 'active' : ''; ?>">All</a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'open'])); ?>" 
                   class="btn btn-outline-primary <?php echo $filter_status === 'open' ? 'active' : ''; ?>">Open</a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'in_progress'])); ?>" 
                   class="btn btn-outline-primary <?php echo $filter_status === 'in_progress' ? 'active' : ''; ?>">In Progress</a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'resolved'])); ?>" 
                   class="btn btn-outline-primary <?php echo $filter_status === 'resolved' ? 'active' : ''; ?>">Resolved</a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'closed'])); ?>" 
                   class="btn btn-outline-primary <?php echo $filter_status === 'closed' ? 'active' : ''; ?>">Closed</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="ticketsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ticket = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $ticket['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($ticket['full_name']); ?><br>
                                    <small class="text-muted"><?php echo $ticket['email']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $ticket['priority'] === 'urgent' ? 'danger' : 
                                            ($ticket['priority'] === 'high' ? 'warning' : 
                                            ($ticket['priority'] === 'medium' ? 'info' : 'secondary')); 
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
                                <td><?php echo date('M j, Y g:i A', strtotime($ticket['updated_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_ticket.php?ticket_id=<?php echo $ticket['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-success update-status" 
                                                data-ticket-id="<?php echo $ticket['id']; ?>"
                                                data-current-status="<?php echo $ticket['status']; ?>">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Status Update Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Ticket Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="updateStatusForm" method="POST">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="ticket_id" id="ticketId">
                    <div class="mb-3">
                        <label for="status" class="form-label">New Status</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes (Optional)</label>
                        <textarea class="form-control" name="admin_notes" id="admin_notes" rows="3"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add DataTables CSS -->
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<!-- Add DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    if (window.DataTable) {
        $('#ticketsTable').DataTable({
            order: [[0, 'desc']]
        });
    }

    // Status update modal handling
    const updateStatusButtons = document.querySelectorAll('.update-status');
    const updateStatusModal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
    const ticketIdInput = document.getElementById('ticketId');
    const statusSelect = document.getElementById('status');
    const adminNotesTextarea = document.getElementById('admin_notes');
    const updateStatusForm = document.getElementById('updateStatusForm');

    updateStatusButtons.forEach(button => {
        button.addEventListener('click', function() {
            const ticketId = this.getAttribute('data-ticket-id');
            const currentStatus = this.getAttribute('data-current-status');

            // Set current ticket ID and status
            ticketIdInput.value = ticketId;
            
            // Pre-select current status
            Array.from(statusSelect.options).forEach(option => {
                option.selected = (option.value === currentStatus);
            });

            // Clear previous notes
            adminNotesTextarea.value = '';

            // Show modal
            updateStatusModal.show();
        });
    });

    // Form submission handling
    updateStatusForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // Basic validation
        if (!statusSelect.value) {
            alert('Please select a status');
            return;
        }

        // Create FormData object
        const formData = new FormData(updateStatusForm);

        // Send AJAX request
        fetch('tickets.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            // Reload the page to reflect changes
            window.location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating ticket status: ' + error.message);
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
