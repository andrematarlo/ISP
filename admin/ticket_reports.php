<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get ticket statistics
$sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_count,
    SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) as medium_count,
    SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low_count,
    AVG(CASE 
        WHEN status IN ('resolved', 'closed') 
        THEN TIMESTAMPDIFF(HOUR, created_at, updated_at)
        ELSE NULL 
    END) as avg_resolution
FROM support_tickets
WHERE created_at BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get daily ticket counts
$sql = "SELECT 
            DATE(created_at) as date,
            COUNT(*) as new_tickets,
            SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_tickets
        FROM support_tickets
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$daily_stats = $stmt->get_result();

// Get response time statistics
$sql = "SELECT 
            t.id,
            t.subject,
            t.priority,
            t.status,
            t.created_at,
            MIN(r.created_at) as first_response,
            TIMESTAMPDIFF(HOUR, t.created_at, MIN(r.created_at)) as response_time
        FROM support_tickets t
        LEFT JOIN ticket_replies r ON t.id = r.ticket_id AND r.user_id IS NOT NULL
        WHERE t.created_at BETWEEN ? AND ?
        GROUP BY t.id
        HAVING first_response IS NOT NULL
        ORDER BY response_time DESC
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$response_times = $stmt->get_result();

// Get customer satisfaction
$sql = "SELECT 
            c.full_name,
            COUNT(t.id) as total_tickets,
            AVG(CASE 
                WHEN t.status IN ('resolved', 'closed') 
                THEN TIMESTAMPDIFF(HOUR, t.created_at, t.updated_at)
                ELSE NULL 
            END) as avg_resolution_time,
            SUM(CASE 
                WHEN t.status = 'open' AND EXISTS (
                    SELECT 1 FROM support_tickets t2 
                    WHERE t2.customer_id = t.customer_id 
                    AND t2.created_at < t.created_at
                    AND t2.status = 'closed'
                )
                THEN 1 ELSE 0 END
            ) as reopened_tickets
        FROM support_tickets t
        JOIN customers c ON t.customer_id = c.id
        WHERE t.created_at BETWEEN ? AND ?
        GROUP BY c.id
        HAVING total_tickets > 0
        ORDER BY reopened_tickets DESC, avg_resolution_time DESC
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$customer_stats = $stmt->get_result();

require_once '../includes/admin_header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0">Support Ticket Reports</h2>
        <form class="d-flex gap-2">
            <div class="input-group">
                <span class="input-group-text">From</span>
                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="input-group">
                <span class="input-group-text">To</span>
                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle text-muted mb-1">Total Tickets</h6>
                            <h2 class="card-title h4 mb-0"><?php echo number_format($stats['total']); ?></h2>
                        </div>
                        <div class="fs-1 text-primary">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle text-muted mb-1">Open Tickets</h6>
                            <h2 class="card-title h4 mb-0"><?php echo number_format($stats['open_count'] + $stats['in_progress']); ?></h2>
                        </div>
                        <div class="fs-1 text-warning">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle text-muted mb-1">Resolved Tickets</h6>
                            <h2 class="card-title h4 mb-0"><?php echo number_format($stats['resolved'] + $stats['closed']); ?></h2>
                        </div>
                        <div class="fs-1 text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle text-muted mb-1">Avg. Resolution Time</h6>
                            <h2 class="card-title h4 mb-0"><?php echo round($stats['avg_resolution'], 1); ?> hrs</h2>
                        </div>
                        <div class="fs-1 text-info">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Activity -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Daily Activity</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>New Tickets</th>
                            <th>Resolved</th>
                            <th>Resolution Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($day = $daily_stats->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($day['date'])); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $day['new_tickets']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $day['resolved_tickets']; ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $rate = $day['new_tickets'] > 0 ? 
                                        ($day['resolved_tickets'] / $day['new_tickets']) * 100 : 
                                        0;
                                    ?>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-success" 
                                             role="progressbar" 
                                             style="width: <?php echo min(100, $rate); ?>%"
                                             aria-valuenow="<?php echo $rate; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?php echo round($rate); ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Response Time Analysis -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Response Time Analysis</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ticket #</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Response Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ticket = $response_times->fetch_assoc()): ?>
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
                                    <?php 
                                    $hours = $ticket['response_time'];
                                    if ($hours < 24) {
                                        echo $hours . ' hours';
                                    } else {
                                        echo round($hours / 24, 1) . ' days';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $ticket['status'] === 'closed' ? 'success' : 
                                            ($ticket['status'] === 'open' ? 'danger' : 
                                            ($ticket['status'] === 'in_progress' ? 'warning' : 'info')); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Customer Analysis -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Customer Analysis</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Total Tickets</th>
                            <th>Avg. Resolution Time</th>
                            <th>Reopened Tickets</th>
                            <th>Customer Satisfaction</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($customer = $customer_stats->fetch_assoc()): 
                            // Calculate satisfaction score (inverse of reopened ratio)
                            $satisfaction = $customer['total_tickets'] > 0 ? 
                                (1 - ($customer['reopened_tickets'] / $customer['total_tickets'])) * 100 : 
                                100;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                <td><?php echo $customer['total_tickets']; ?></td>
                                <td>
                                    <?php 
                                    $hours = round($customer['avg_resolution_time'], 1);
                                    if ($hours < 24) {
                                        echo $hours . ' hours';
                                    } else {
                                        echo round($hours / 24, 1) . ' days';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($customer['reopened_tickets'] > 0): ?>
                                        <span class="badge bg-warning">
                                            <?php echo $customer['reopened_tickets']; ?> reopened
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?php 
                                            echo $satisfaction >= 80 ? 'bg-success' : 
                                                ($satisfaction >= 60 ? 'bg-warning' : 'bg-danger'); 
                                        ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $satisfaction; ?>%"
                                             aria-valuenow="<?php echo $satisfaction; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?php echo round($satisfaction); ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
