<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success = $error = '';

// Get plan ID from URL
$plan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$plan_id) {
    header('Location: plans.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = $conn->real_escape_string($_POST['name']);
    $speed = $conn->real_escape_string($_POST['speed']);
    $price = floatval($_POST['price']);
    $description = $conn->real_escape_string($_POST['description']);
    $status = $_POST['status'];

    // Validate price
    if ($price <= 0) {
        $error = 'Price must be greater than 0';
    }

    if (!$error) {
        // Update plan
        $sql = "UPDATE plans SET name = ?, speed = ?, price = ?, description = ?, status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssdssi', $name, $speed, $price, $description, $status, $plan_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Plan updated successfully!";
            header('Location: plans.php');
            exit();
        } else {
            $error = 'Error updating plan: ' . $conn->error;
        }
    }
}

// Get plan details
$sql = "SELECT * FROM plans WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $plan_id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {
    header('Location: plans.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Plan - ISP Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">ISP System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="customers.php">Customers</a></li>
                    <li class="nav-item"><a class="nav-link active" href="plans.php">Plans</a></li>
                    <li class="nav-item"><a class="nav-link" href="bills.php">Bills</a></li>
                    <li class="nav-item"><a class="nav-link" href="tickets.php">Support Tickets</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Edit Plan</h4>
                        <a href="plans.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="name" class="form-label">Plan Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($plan['name']); ?>" required>
                                <div class="invalid-feedback">Please enter a plan name.</div>
                            </div>

                            <div class="mb-3">
                                <label for="speed" class="form-label">Internet Speed</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="speed" name="speed" 
                                           value="<?php echo htmlspecialchars($plan['speed']); ?>" required>
                                    <span class="input-group-text">Mbps</span>
                                </div>
                                <div class="invalid-feedback">Please enter the internet speed.</div>
                            </div>

                            <div class="mb-3">
                                <label for="price" class="form-label">Monthly Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           value="<?php echo number_format($plan['price'], 2, '.', ''); ?>"
                                           step="0.01" min="0" required>
                                </div>
                                <div class="invalid-feedback">Please enter a valid price.</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3" required><?php echo htmlspecialchars($plan['description']); ?></textarea>
                                <div class="invalid-feedback">Please enter a description.</div>
                            </div>

                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo $plan['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $plan['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <div class="invalid-feedback">Please select a status.</div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="view_plan.php?id=<?php echo $plan_id; ?>" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Plan</button>
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

        // Format speed input to always append Mbps
        document.getElementById('speed').addEventListener('blur', function(e) {
            let value = e.target.value.replace(/[^\d]/g, '');
            if (value) {
                if (!value.toLowerCase().includes('mbps')) {
                    value += ' Mbps';
                }
                e.target.value = value;
            }
        });

        // Format price input to show two decimal places
        document.getElementById('price').addEventListener('blur', function(e) {
            let value = parseFloat(e.target.value);
            if (!isNaN(value)) {
                e.target.value = value.toFixed(2);
            }
        });
    </script>
</body>
</html>
