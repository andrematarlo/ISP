<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to JoJeTech Solutions</title>
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
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }
        .welcome-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }
        .company-logo {
            max-width: 200px;
            margin-bottom: 20px;
        }
        .btn-custom {
            background: #007bff;
            border: none;
            padding: 10px 20px;
            font-weight: 500;
            color: white;
        }
        .btn-custom:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <?php if(!isset($_SESSION['user_id'])): ?>
    <div class="welcome-card">
        <img src="assets/images/logo.png" alt="JoJeTech Solutions" class="company-logo">
        <h1>Welcome to JoJeTech Solutions</h1>
        <p>Your trusted partner in ISP management solutions.</p>
        <div class="d-flex justify-content-center mt-4">
            <a href="login.php" class="btn btn-custom me-3">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </a>
            <a href="register.php" class="btn btn-custom">
                <i class="fas fa-user-plus me-2"></i>Register
            </a>
        </div>
    </div>
    <?php else: ?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="index.php">ISP System</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <?php if($_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="admin/dashboard.php">Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="admin/customers.php">Customers</a></li>
                            <li class="nav-item"><a class="nav-link" href="admin/plans.php">Plans</a></li>
                            <li class="nav-item"><a class="nav-link" href="admin/bills.php">Bills</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="customer/dashboard.php">Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="customer/subscription.php">My Subscription</a></li>
                            <li class="nav-item"><a class="nav-link" href="customer/bills.php">My Bills</a></li>
                            <li class="nav-item"><a class="nav-link" href="customer/support.php">Support</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <main class="container mt-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1>Welcome to Our ISP Services</h1>
                    <p class="lead">Experience lightning-fast internet with our reliable broadband services.</p>
                    <a href="register.php" class="btn btn-primary btn-lg">Get Started</a>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Our Plans</h5>
                            <?php
                            $sql = "SELECT * FROM plans WHERE status = 'active' LIMIT 3";
                            $result = $conn->query($sql);
                            while($plan = $result->fetch_assoc()):
                            ?>
                            <div class="mb-3">
                                <h6><?php echo $plan['name']; ?></h6>
                                <p><?php echo $plan['description']; ?><br>
                                Speed: <?php echo $plan['speed']; ?><br>
                                Price: $<?php echo $plan['price']; ?>/month</p>
                            </div>
                            <?php endwhile; ?>
                            <a href="plans.php" class="btn btn-outline-primary">View All Plans</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    <?php endif; ?>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
