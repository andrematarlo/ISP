<?php
require_once '../config/database.php';
require_once '../includes/admin_header.php';

// Initialize date range filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month

// Prepare the base query for payment statistics
$stats_query = "SELECT 
    COUNT(*) as total_payments,
    SUM(amount) as total_amount,
    payment_method,
    status
FROM payments 
WHERE payment_date BETWEEN ? AND ?
GROUP BY payment_method, status";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("ss", $start_date, $end_date);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();

// Initialize statistics arrays
$payment_stats = [
    'total_amount' => 0,
    'completed_amount' => 0,
    'pending_amount' => 0,
    'payment_methods' => []
];

while ($row = $stats_result->fetch_assoc()) {
    if ($row['status'] === 'completed') {
        $payment_stats['completed_amount'] += $row['total_amount'];
    } elseif ($row['status'] === 'pending') {
        $payment_stats['pending_amount'] += $row['total_amount'];
    }
    $payment_stats['total_amount'] += $row['total_amount'];
    
    if (!isset($payment_stats['payment_methods'][$row['payment_method']])) {
        $payment_stats['payment_methods'][$row['payment_method']] = 0;
    }
    if ($row['status'] === 'completed') {
        $payment_stats['payment_methods'][$row['payment_method']] += $row['total_amount'];
    }
}

// Get recent payments
$recent_query = "SELECT p.*, b.id as bill_id 
    FROM payments p 
    JOIN bills b ON p.bill_id = b.id 
    WHERE p.payment_date BETWEEN ? AND ? 
    ORDER BY p.payment_date DESC 
    LIMIT 10";

$recent_stmt = $conn->prepare($recent_query);
$recent_stmt->bind_param("ss", $start_date, $end_date);
$recent_stmt->execute();
$recent_payments = $recent_stmt->get_result();
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Billing Reports</h2>
        <button onclick="exportToExcel()" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Export to Excel
        </button>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" 
                           value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Collections</h5>
                    <h3 class="mb-0">₱<?php echo number_format($payment_stats['total_amount'], 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Completed Payments</h5>
                    <h3 class="mb-0">₱<?php echo number_format($payment_stats['completed_amount'], 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Pending Payments</h5>
                    <h3 class="mb-0">₱<?php echo number_format($payment_stats['pending_amount'], 2); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Methods Chart -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Payment Methods Distribution</h5>
                    <canvas id="paymentMethodsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recent Payments</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Bill #</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $recent_payments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['bill_id']); ?></td>
                                    <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $payment['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Payment Methods Chart
const paymentMethodsData = <?php echo json_encode(array_values($payment_stats['payment_methods'])); ?>;
const paymentMethodsLabels = <?php echo json_encode(array_map('ucfirst', array_keys($payment_stats['payment_methods']))); ?>;

new Chart(document.getElementById('paymentMethodsChart'), {
    type: 'pie',
    data: {
        labels: paymentMethodsLabels,
        datasets: [{
            data: paymentMethodsData,
            backgroundColor: [
                '#4e73df',
                '#1cc88a',
                '#36b9cc'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Export to Excel function
function exportToExcel() {
    window.location.href = 'export_billing_report.php?start_date=' + 
        document.getElementById('start_date').value + 
        '&end_date=' + document.getElementById('end_date').value;
}
</script>

<?php require_once '../includes/footer.php'; ?>
