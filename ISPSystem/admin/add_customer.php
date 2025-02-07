<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $address = $conn->real_escape_string($_POST['address']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $status = $_POST['status'];

    // Validate phone number format
    $phone = str_replace(['-', ' ', '(', ')'], '', $phone);
    if (substr($phone, 0, 2) === '09') {
        // Convert 09 format to +63 format
        $phone = '+63' . substr($phone, 1);
    } elseif (substr($phone, 0, 3) !== '+63') {
        $error = 'Phone number must start with either 09 or +63';
    }

    // Additional phone number validation
    if (strlen($phone) !== 13) { // +63 + 10 digits
        $error = 'Invalid phone number length. Must be 11 digits for 09 format or 12 digits for +63 format';
    }

    if (!$error) {
        // Check if username or email already exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Username or email already exists';
        } else {
            $conn->begin_transaction();
            try {
                // Insert user
                $user_sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'customer')";
                $stmt = $conn->prepare($user_sql);
                $stmt->bind_param('sss', $username, $email, $password);
                $stmt->execute();
                $user_id = $conn->insert_id;

                // Insert customer details
                $customer_sql = "INSERT INTO customers (user_id, full_name, address, phone, status) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($customer_sql);
                $stmt->bind_param('issss', $user_id, $full_name, $address, $phone, $status);
                $stmt->execute();

                $conn->commit();
                $success = 'Customer added successfully!';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Error adding customer: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer - ISP Management System</title>
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
                    <li class="nav-item"><a class="nav-link active" href="customers.php">Customers</a></li>
                    <li class="nav-item"><a class="nav-link" href="plans.php">Plans</a></li>
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
                    <div class="card-header">
                        <h4 class="mb-0">Add New Customer</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="row">
                                <!-- Account Information -->
                                <div class="col-md-6">
                                    <h5 class="mb-3">Account Information</h5>
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                        <div class="invalid-feedback">Please enter a username.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                        <div class="invalid-feedback">Please enter a valid email.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <div class="invalid-feedback">Please enter a password.</div>
                                    </div>
                                </div>

                                <!-- Personal Information -->
                                <div class="col-md-6">
                                    <h5 class="mb-3">Personal Information</h5>
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                                        <div class="invalid-feedback">Please enter the full name.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               placeholder="+63 XXX XXX XXXX or 09XX XXX XXXX"
                                               pattern="^(\+63|09)[0-9]{9}$"
                                               required>
                                        <div class="invalid-feedback">Please enter a valid Philippine phone number (09XX XXX XXXX or +63 XXX XXX XXXX).</div>
                                        <small class="form-text text-muted">Format: +63 XXX XXX XXXX or 09XX XXX XXXX</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                        <div class="invalid-feedback">Please select a status.</div>
                                    </div>
                                </div>

                                <!-- Address -->
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                                        <div class="invalid-feedback">Please enter an address.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="customers.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Add Customer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
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

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // Handle +63 format
            if (value.startsWith('63')) {
                value = '+' + value;
            } 
            // Handle 09 format
            else if (value.startsWith('9')) {
                value = '0' + value;
            }
            
            e.target.value = value;
        });

        // Additional phone validation before form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value.replace(/[^0-9+]/g, '');
            
            if (!phone.match(/^(\+63|09)\d{9}$/)) {
                e.preventDefault();
                alert('Please enter a valid Philippine phone number starting with +63 or 09');
                return false;
            }
        });
    </script>
</body>
</html>
