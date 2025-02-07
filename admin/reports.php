<?php
session_start();
require_once '../config/database.php';
require_once '../includes/admin_header.php';

// Initialize filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'all';

// Fetch Billing Reports
$billing_sql = "SELECT b.*, c.full_name, c.phone, p.name as plan_name, p.price, p.speed
                FROM bills b
                JOIN subscriptions s ON b.subscription_id = s.id
                JOIN customers c ON s.customer_id = c.id
                JOIN plans p ON s.plan_id = p.id
                WHERE b.bill_date BETWEEN ? AND ?
                ORDER BY b.bill_date DESC";

$stmt = $conn->prepare($billing_sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$billing_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate billing statistics
$total_billed = array_sum(array_column($billing_reports, 'amount'));
$total_paid = array_sum(array_map(function($bill) {
    return $bill['status'] === 'Paid' ? $bill['amount'] : 0;
}, $billing_reports));
$total_unpaid = $total_billed - $total_paid;

// Fetch Support Ticket Reports
$ticket_sql = "SELECT t.*, c.full_name, c.phone
               FROM support_tickets t
               JOIN customers c ON t.customer_id = c.id
               WHERE t.created_at BETWEEN ? AND ?
               ORDER BY t.created_at DESC";

$stmt = $conn->prepare($ticket_sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$ticket_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate ticket statistics
$total_tickets = count($ticket_reports);
$open_tickets = count(array_filter($ticket_reports, function($ticket) {
    return $ticket['status'] === 'Open';
}));
$resolved_tickets = count(array_filter($ticket_reports, function($ticket) {
    return $ticket['status'] === 'Resolved';
}));
?>

<main class="container-fluid mt-4">
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select name="report_type" class="form-select">
                        <option value="all" <?php echo $report_type === 'all' ? 'selected' : ''; ?>>All Reports</option>
                        <option value="billing" <?php echo $report_type === 'billing' ? 'selected' : ''; ?>>Billing Only</option>
                        <option value="tickets" <?php echo $report_type === 'tickets' ? 'selected' : ''; ?>>Tickets Only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <?php if ($report_type !== 'tickets'): ?>
        <!-- Billing Statistics -->
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Billed</h5>
                    <h3>₱<?php echo number_format($total_billed, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Paid</h5>
                    <h3>₱<?php echo number_format($total_paid, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Unpaid</h5>
                    <h3>₱<?php echo number_format($total_unpaid, 2); ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($report_type !== 'billing'): ?>
        <!-- Ticket Statistics -->
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Tickets</h5>
                    <h3><?php echo $total_tickets; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Open Tickets</h5>
                    <h3><?php echo $open_tickets; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Resolved Tickets</h5>
                    <h3><?php echo $resolved_tickets; ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($report_type !== 'tickets'): ?>
    <!-- Billing Reports Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Billing Reports</h4>
            <button class="btn btn-success" onclick="exportToExcel('billing-table', 'billing_report')">
                <i class="fas fa-file-excel me-2"></i>Export to Excel
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="billing-table">
                    <thead>
                        <tr>
                            <th>Bill Date</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Plan</th>
                            <th>Speed</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($billing_reports as $bill): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($bill['bill_date'])); ?></td>
                            <td><?php echo htmlspecialchars($bill['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($bill['phone']); ?></td>
                            <td><?php echo htmlspecialchars($bill['plan_name']); ?></td>
                            <td><?php echo htmlspecialchars($bill['speed']); ?></td>
                            <td>₱<?php echo number_format($bill['amount'], 2); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $bill['status'] === 'Paid' ? 'success' : 'danger'; ?>">
                                    <?php echo $bill['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($bill['due_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($report_type !== 'billing'): ?>
    <!-- Support Ticket Reports Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Support Ticket Reports</h4>
            <button class="btn btn-success" onclick="exportToExcel('ticket-table', 'ticket_report')">
                <i class="fas fa-file-excel me-2"></i>Export to Excel
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="ticket-table">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Created</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ticket_reports as $ticket): ?>
                        <tr>
                            <td>#<?php echo $ticket['id']; ?></td>
                            <td><?php echo htmlspecialchars($ticket['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['phone']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $ticket['status'] === 'Open' ? 'warning' : 
                                         ($ticket['status'] === 'Resolved' ? 'success' : 'secondary'); 
                                ?>">
                                    <?php echo $ticket['status']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $ticket['priority'] === 'High' ? 'danger' : 
                                         ($ticket['priority'] === 'Medium' ? 'warning' : 'info'); 
                                ?>">
                                    <?php echo $ticket['priority']; ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($ticket['updated_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Add SheetJS for Excel export -->
<script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
<script>
function exportToExcel(tableId, reportName) {
    const table = document.getElementById(tableId);
    const wb = XLSX.utils.table_to_book(table, {sheet: "Report"});
    const fileName = reportName + '_<?php echo date('Y-m-d'); ?>.xlsx';
    XLSX.writeFile(wb, fileName);
}
</script>

<?php require_once '../includes/admin_footer.php'; ?>