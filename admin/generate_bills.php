<?php
session_start();
require_once '../config/database.php';
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $conn->begin_transaction();

    try {
        // Get all active subscriptions
        $sql = "SELECT s.*, p.price, c.full_name 
                FROM subscriptions s 
                JOIN plans p ON s.plan_id = p.id 
                JOIN customers c ON s.customer_id = c.id 
                WHERE s.status = 'active'";
        $result = $conn->query($sql);

        $bills_generated = 0;
        $total_amount = 0;

        // Get the billing month (current month by default)
        $billing_month = isset($_POST['billing_month']) ? $_POST['billing_month'] : date('Y-m');
        $bill_date = $billing_month . '-01';
        $due_date = date('Y-m-d', strtotime($bill_date . ' +15 days')); // Due after 15 days

        // Check if bills already exist for this month
        $check_sql = "SELECT COUNT(*) as count FROM bills WHERE DATE_FORMAT(bill_date, '%Y-%m') = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('s', $billing_month);
        $check_stmt->execute();
        $existing_bills = $check_stmt->get_result()->fetch_assoc()['count'];

        if ($existing_bills > 0) {
            throw new Exception("Bills for {$billing_month} have already been generated!");
        }

        // Prepare the insert statement
        $insert_sql = "INSERT INTO bills (subscription_id, amount, bill_date, due_date, status, created_at) 
                      VALUES (?, ?, ?, ?, 'unpaid', NOW())";
        $stmt = $conn->prepare($insert_sql);

        while ($subscription = $result->fetch_assoc()) {
            // Insert bill
            $stmt->bind_param('idss', 
                $subscription['id'],
                $subscription['price'],
                $bill_date,
                $due_date
            );
            
            if ($stmt->execute()) {
                $bills_generated++;
                $total_amount += $subscription['price'];
            }
        }

        if ($bills_generated > 0) {
            $conn->commit();
            $_SESSION['success'] = "Successfully generated {$bills_generated} bills for {$billing_month} 
                                  totaling ₱" . number_format($total_amount, 2);
        } else {
            throw new Exception("No active subscriptions found to generate bills.");
        }

        header('Location: bills.php');
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

require_once '../includes/admin_header.php';

// Get list of months with bills
$months_sql = "SELECT DISTINCT DATE_FORMAT(bill_date, '%Y-%m') as month 
               FROM bills 
               ORDER BY month DESC";
$months_with_bills = $conn->query($months_sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Bills - ISP Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
   

    <main class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Generate Monthly Bills</h4>
                        <a href="bills.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Bills
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <!-- Months with existing bills -->
                        <?php if (!empty($months_with_bills)): ?>
                        <div class="alert alert-info">
                            <h5>Months with Generated Bills:</h5>
                            <ul class="list-inline mb-0">
                                <?php foreach ($months_with_bills as $month): ?>
                                    <li class="list-inline-item">
                                        <span class="badge bg-primary">
                                            <?php echo date('F Y', strtotime($month['month'] . '-01')); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="billing_month" class="form-label">Billing Month</label>
                                <input type="month" class="form-control" id="billing_month" name="billing_month"
                                       value="<?php echo date('Y-m'); ?>" required>
                                <div class="form-text">
                                    Bills will be generated for all active subscriptions for this month.
                                </div>
                                <div class="invalid-feedback">Please select a billing month.</div>
                            </div>

                            <div class="alert alert-warning">
                                <h5><i class="fas fa-exclamation-triangle"></i> Important Notes:</h5>
                                <ul class="mb-0">
                                    <li>This will generate bills for all active subscriptions.</li>
                                    <li>Bills will be due 15 days from the start of the billing month.</li>
                                    <li>You cannot generate bills for a month that already has bills.</li>
                                    <li>Make sure all subscription data is up to date before generating bills.</li>
                                </ul>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-file-invoice"></i> Generate Bills
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your-font-awesome-kit.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>
