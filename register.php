<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    // Validate password match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Check if username or email already exists
        $sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Username or email already exists";
        } else {
            // Start transaction
            $conn->begin_transaction();

            try {
                // Create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'customer')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $username, $email, $hashed_password);
                $stmt->execute();
                $user_id = $conn->insert_id;

                // Create customer profile with email
                $sql = "INSERT INTO customers (user_id, full_name, email, phone, address) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issss", $user_id, $full_name, $email, $phone, $address);
                $stmt->execute();

                $conn->commit();
                $_SESSION['success'] = "Registration successful! You can now login with your credentials.";
                
                // Show temporary success message before redirect
                echo '<div class="alert alert-success text-center position-fixed top-0 start-50 translate-middle-x mt-3" style="z-index: 1050;">
                        Registration successful! Redirecting to login page...
                      </div>';
                
                // Redirect after a short delay
                echo '<script>
                        setTimeout(function() {
                            window.location.href = "login.php";
                        }, 2000);
                      </script>';
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Registration failed. Please try again.";
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
    <title>Register - JoJeTech Solutions</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
        }
        .register-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .register-header {
            background: #007bff;
            color: white;
            padding: 20px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .company-logo {
            max-width: 150px;
            margin-bottom: 15px;
        }
        .btn-register {
            background: #007bff;
            border: none;
            padding: 10px 20px;
            font-weight: 500;
        }
        .btn-register:hover {
            background: #0056b3;
        }
        .alert {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card register-card">
                    <div class="register-header">
                        <img src="assets/images/logo.png" alt="JoJeTech Solutions Logo" class="company-logo">
                        <h4 class="mb-0">Create Your Account</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                                <div class="invalid-feedback">Please choose a username.</div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="invalid-feedback">Please enter a password.</div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <div class="invalid-feedback">Passwords do not match.</div>
                            </div>

                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                                <div class="invalid-feedback">Please enter your full name.</div>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                                <div class="invalid-feedback">Please enter your phone number.</div>
                            </div>

                            <div class="mb-4">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                                <div class="invalid-feedback">Please enter your address.</div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-register">Register</button>
                            </div>
                        </form>

                        <div class="text-center mt-3">
                            <p class="mb-0">Already have an account? <a href="login.php" class="text-primary">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

                        // Custom password match validation
                        var password = form.querySelector('#password')
                        var confirm_password = form.querySelector('#confirm_password')
                        if (password.value !== confirm_password.value) {
                            confirm_password.setCustomValidity('Passwords do not match')
                            event.preventDefault()
                            event.stopPropagation()
                        } else {
                            confirm_password.setCustomValidity('')
                        }

                        form.classList.add('was-validated')
                    }, false)
                })
        })()

        // Auto-dismiss alerts after 5 seconds
        window.addEventListener('DOMContentLoaded', (event) => {
            const alerts = document.querySelectorAll('.alert:not(.position-fixed)');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Password match validation on input
        document.getElementById('confirm_password').addEventListener('input', function(e) {
            if (this.value !== document.getElementById('password').value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
