<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once dirname(__DIR__) . '/config/database.php';

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Define navigation items
$nav_items = $is_admin ? [
    ['url' => '/ISPSystem/admin/index.php', 'icon' => 'tachometer-alt', 'text' => 'Dashboard'],
    ['url' => '/ISPSystem/admin/customers.php', 'icon' => 'users', 'text' => 'Customers'],
    ['url' => '/ISPSystem/admin/plans.php', 'icon' => 'wifi', 'text' => 'Plans'],
    ['url' => '/ISPSystem/admin/bills.php', 'icon' => 'file-invoice-dollar', 'text' => 'Bills'],
    ['url' => '/ISPSystem/admin/tickets.php', 'icon' => 'ticket-alt', 'text' => 'Support Tickets'],
    ['url' => '/ISPSystem/admin/ticket_reports.php', 'icon' => 'chart-bar', 'text' => 'Reports']
] : [
    ['url' => '/ISPSystem/customer/dashboard.php', 'icon' => 'tachometer-alt', 'text' => 'Dashboard'],
    ['url' => '/ISPSystem/customer/subscription.php', 'icon' => 'wifi', 'text' => 'My Subscription'],
    ['url' => '/ISPSystem/customer/view_bill.php', 'icon' => 'file-invoice-dollar', 'text' => 'My Bills'],
    ['url' => '/ISPSystem/customer/tickets.php', 'icon' => 'ticket-alt', 'text' => 'Support Tickets']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JoJeTech Solutions Inc.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom Admin CSS -->
    <link rel="stylesheet" href="/ISPSystem/assets/css/admin.css">
    
    <style>
        .top-nav {
            background: #0d6efd;
            padding: 0.5rem 0;
        }
        .top-nav .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            padding: 0.5rem 1rem !important;
            border-radius: 4px;
        }
        .top-nav .nav-link:hover {
            color: #fff !important;
            background: rgba(255, 255, 255, 0.1);
        }
        .top-nav .nav-link.active {
            color: #fff !important;
            background: rgba(255, 255, 255, 0.2);
        }
        .top-nav .navbar-brand {
            color: #fff !important;
            font-weight: 600;
        }
        .dropdown-item i {
            width: 1.5rem;
        }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <a class="navbar-brand me-4" href="/ISPSystem/<?php echo $is_admin ? 'admin' : 'customer'; ?>/index.php">
                        JoJeTech Solutions Inc.
                    </a>
                    <div class="nav">
                        <?php foreach ($nav_items as $item): 
                            $is_active = strpos($_SERVER['PHP_SELF'], basename($item['url'])) !== false;
                        ?>
                            <a class="nav-link <?php echo $is_active ? 'active' : ''; ?>" 
                               href="<?php echo $item['url']; ?>">
                                <i class="fas fa-<?php echo $item['icon']; ?> me-1"></i>
                                <?php echo $item['text']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php
                        if ($is_admin) {
                            echo htmlspecialchars($_SESSION['name'] ?? 'Admin');
                        } else {
                            $sql = "SELECT full_name FROM customers WHERE user_id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param('i', $_SESSION['user_id']);
                            $stmt->execute();
                            $customer = $stmt->get_result()->fetch_assoc();
                            echo htmlspecialchars($customer['full_name'] ?? 'Customer');
                        }
                        ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($is_admin): ?>
                            <li>
                                <a class="dropdown-item" href="/ISPSystem/admin/profile.php">
                                    <i class="fas fa-user-cog"></i> Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/ISPSystem/admin/settings.php">
                                    <i class="fas fa-cogs"></i> Settings
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item" href="/ISPSystem/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
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
</body>
</html>
