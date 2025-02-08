<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get bill ID from URL
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$bill_id) {
    header('Location: bills.php');
    exit();
}

// Get bill details
$sql = "SELECT b.*, c.full_name, c.phone, c.address, p.name as plan_name, p.speed,
        s.start_date as subscription_start
        FROM bills b 
        JOIN subscriptions s ON b.subscription_id = s.id 
        JOIN customers c ON s.customer_id = c.id 
        JOIN plans p ON s.plan_id = p.id 
        WHERE b.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $bill_id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();

if (!$bill) {
    header('Location: bills.php');
    exit();
}

// Get payment if paid
$payment = null;
if ($bill['status'] === 'paid') {
    $sql = "SELECT * FROM payments WHERE bill_id = ? ORDER BY payment_date DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $bill_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill #<?php echo $bill_id; ?> - ISP Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            @page {
                size: A4;
                margin: 1cm;
            }
            body {
                margin: 0;
                padding: 20px;
                font-size: 12pt;
            }
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            .container {
                width: 100% !important;
                max-width: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
        }
        .print-only {
            display: none;
        }
        .bill-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .bill-header img {
            max-width: 150px;
            margin-bottom: 10px;
        }
        .bill-info {
            margin-bottom: 30px;
        }
        .bill-table {
            margin-bottom: 30px;
        }
        .bill-total {
            text-align: right;
            margin-bottom: 30px;
        }
        .payment-info {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .qr-code {
            text-align: center;
            margin-bottom: 20px;
        }
        .qr-code img {
            max-width: 150px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Print Controls -->
        <div class="no-print mb-4">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Bill
            </button>
            <a href="<?php echo $_SESSION['role'] === 'admin' ? 'view_bill.php' : '../customer/view_bill.php'; ?>?id=<?php echo $bill_id; ?>" 
               class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <!-- Bill Content -->
        <div class="bill-header">
            <img src="../assets/images/jojetechlogo.png" alt="ISP Logo" class="mb-2">
            <h2>INTERNET SERVICE PROVIDER</h2>
            <p>Suba,Poblacion,Argao<br>
               Phone: 09195700051<br>
               Email: tamarloandre@gmail.com</p>
        </div>

        <div class="row bill-info">
            <div class="col-md-6">
                <h5>Bill To:</h5>
                <p>
                    <strong><?php echo htmlspecialchars($bill['full_name']); ?></strong><br>
                    <?php echo htmlspecialchars($bill['address']); ?><br>
                    Phone: <?php echo htmlspecialchars($bill['phone']); ?>
                </p>
            </div>
            <div class="col-md-6 text-end">
                <h5>Bill Details:</h5>
                <p>
                    Bill #: <?php echo $bill_id; ?><br>
                    Bill Date: <?php echo date('F j, Y', strtotime($bill['bill_date'])); ?><br>
                    Due Date: <?php echo date('F j, Y', strtotime($bill['due_date'])); ?>
                </p>
            </div>
        </div>

        <div class="bill-table">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($bill['plan_name']); ?> Plan<br>
                            <small class="text-muted"><?php echo htmlspecialchars($bill['speed']); ?></small><br>
                            Billing Period: <?php echo date('F Y', strtotime($bill['bill_date'])); ?>
                        </td>
                        <td class="text-end">₱<?php echo number_format($bill['amount'], 2); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th class="text-end">Total Amount:</th>
                        <th class="text-end">₱<?php echo number_format($bill['amount'], 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if ($bill['status'] === 'paid' && $payment): ?>
        <div class="payment-info bg-success text-white">
            <h5><i class="fas fa-check-circle"></i> Payment Received</h5>
            <p class="mb-0">
                Paid on: <?php echo isset($payment['payment_date']) ? date('F j, Y g:i A', strtotime($payment['payment_date'])) : 'N/A'; ?><br>
                Method: <?php echo isset($payment['payment_method']) ? ucfirst($payment['payment_method']) : 'N/A'; ?><br>
                Reference: <?php echo isset($payment['reference_number']) ? htmlspecialchars($payment['reference_number']) : 'N/A'; ?>
            </p>
        </div>
        <?php else: ?>
        <div class="payment-info">
            <h5>Payment Instructions</h5>
            <p class="mb-0">Please pay using any of these methods:</p>
            <div class="row mt-3">
                <div class="col-md-6">
                    <h6>GCash Payment:</h6>
                    <div class="qr-code">
                        <img src="../assets/images/gcash-qr.png" alt="GCash QR Code">
                    </div>
                    <p class="text-center">
                        GCash Number: 09123456789<br>
                        Amount: ₱<?php echo number_format($bill['amount'], 2); ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <h6>Important Notes:</h6>
                    <ul>
                        <li>Payment is due by: <?php echo date('F j, Y', strtotime($bill['due_date'])); ?></li>
                        <li>Please include your Bill # <?php echo $bill_id; ?> as reference</li>
                        <li>Late payments may result in service interruption</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>Thank you for your business!</p>
            <small>For billing inquiries, please contact our support team at support@ispprovider.com</small>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your-font-awesome-kit.js"></script>
</body>
</html>
