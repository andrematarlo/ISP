<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../login.php');
    exit();
}

// Include database connection
require_once dirname(__DIR__) . '/config/database.php';

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Define customer navigation items
$customer_nav_items = [
    ['url' => '/ISPSystem/customer/dashboard.php', 'icon' => 'tachometer-alt', 'text' => 'Dashboard'],
    ['url' => '/ISPSystem/customer/subscription.php', 'icon' => 'wifi', 'text' => 'My Subscription'],
    ['url' => '/ISPSystem/customer/view_bill.php', 'icon' => 'file-invoice-dollar', 'text' => 'My Bills'],
    ['url' => '/ISPSystem/customer/tickets.php', 'icon' => 'ticket-alt', 'text' => 'Support Tickets']
];

// Get customer name
$sql = "SELECT full_name FROM customers WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$customer_name = $customer['full_name'] ?? 'Customer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer - JoJeTech Solutions Inc.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .customer-nav {
            background: #28a745;
            color: white;
        }
        .customer-nav .nav-link {
            color: rgba(255, 255, 255, 0.75) !important;
            padding: 0.5rem 1rem !important;
            border-radius: 4px;
        }
        .customer-nav .nav-link:hover {
            color: #fff !important;
            background: rgba(255, 255, 255, 0.1);
        }
        .customer-nav .nav-link.active {
            color: #fff !important;
            background: rgba(255, 255, 255, 0.2);
        }
        .customer-nav .navbar-brand {
            color: #fff !important;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg customer-nav">
        <div class="container-fluid">
            <a class="navbar-brand" href="/ISPSystem/customer/dashboard.php">
                <i class="fas fa-home me-2"></i>JoJeTech Customer Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#customerNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="customerNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php foreach ($customer_nav_items as $item): 
                        $is_active = strpos($_SERVER['PHP_SELF'], basename($item['url'])) !== false;
                    ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $is_active ? 'active' : ''; ?>" 
                               href="<?php echo $item['url']; ?>">
                                <i class="fas fa-<?php echo $item['icon']; ?> me-1"></i>
                                <?php echo $item['text']; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="customerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($customer_name); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="customerDropdown">
                            <li>
                                <a class="dropdown-item" href="/ISPSystem/customer/profile.php">
                                    <i class="fas fa-user-cog me-2"></i>Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="/ISPSystem/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced Dropdown debugging
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dropdown debugging started');
            
            // Check Bootstrap availability
            if (typeof bootstrap === 'undefined') {
                console.error('Bootstrap not loaded correctly');
            }

            var dropdownToggle = document.getElementById('customerDropdown');
            var dropdownMenu = dropdownToggle ? dropdownToggle.nextElementSibling : null;

            if (dropdownToggle) {
                console.log('Dropdown toggle found');
                
                // Detailed event logging
                dropdownToggle.addEventListener('click', function(e) {
                    console.log('Dropdown clicked');
                    console.log('Toggle classes:', dropdownToggle.classList);
                    
                    if (dropdownMenu) {
                        console.log('Dropdown menu exists');
                        console.log('Menu classes:', dropdownMenu.classList);
                        
                        // Force show dropdown
                        dropdownMenu.classList.toggle('show');
                    } else {
                        console.error('Dropdown menu not found');
                    }
                });

                // Alternative method using Bootstrap's Dropdown
                try {
                    var dropdownInstance = new bootstrap.Dropdown(dropdownToggle);
                    console.log('Bootstrap Dropdown instance created');
                } catch (error) {
                    console.error('Failed to create Dropdown instance:', error);
                }
            } else {
                console.error('Dropdown toggle not found');
            }
        });
    </script>
</body>
</html>
