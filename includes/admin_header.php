<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Include database connection
require_once dirname(__DIR__) . '/config/database.php';

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Define admin navigation items
$admin_nav_items = [
    ['url' => '/ISPSystem/admin/index.php', 'icon' => 'tachometer-alt', 'text' => 'Dashboard'],
    ['url' => '/ISPSystem/admin/customers.php', 'icon' => 'users', 'text' => 'Customers'],
    ['url' => '/ISPSystem/admin/plans.php', 'icon' => 'wifi', 'text' => 'Plans'],
    ['url' => '/ISPSystem/admin/bills.php', 'icon' => 'file-invoice-dollar', 'text' => 'Bills'],
    ['url' => '/ISPSystem/admin/tickets.php', 'icon' => 'ticket-alt', 'text' => 'Support Tickets'],
    ['url' => '/ISPSystem/admin/ticket_reports.php', 'icon' => 'chart-bar', 'text' => 'Reports']
];

// Count pending upgrade requests
$upgrade_requests_query = "SELECT COUNT(*) as pending_count FROM upgrade_requests WHERE status = 'pending'";
$upgrade_requests_result = $conn->query($upgrade_requests_query);
$pending_upgrade_requests = $upgrade_requests_result ? $upgrade_requests_result->fetch_assoc()['pending_count'] : 0;

// Add upgrade requests count to admin navigation
$admin_nav_items[] = [
    'url' => '/ISPSystem/admin/upgrade_requests.php',
    'icon' => 'arrow-up',
    'text' => 'Upgrade Requests' . ($pending_upgrade_requests > 0 ? ' <span class="badge bg-danger">' . $pending_upgrade_requests . '</span>' : '')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - JoJeTech Solutions Inc.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .admin-nav {
            background: #343a40;
            color: white;
        }
        .admin-nav .nav-link {
            color: rgba(255, 255, 255, 0.75) !important;
            padding: 0.5rem 1rem !important;
            border-radius: 4px;
        }
        .admin-nav .nav-link:hover {
            color: #fff !important;
            background: rgba(255, 255, 255, 0.1);
        }
        .admin-nav .nav-link.active {
            color: #fff !important;
            background: rgba(255, 255, 255, 0.2);
        }
        .admin-nav .navbar-brand {
            color: #fff !important;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg admin-nav">
        <div class="container-fluid">
            <a class="navbar-brand" href="/ISPSystem/admin/index.php">
                <i class="fas fa-shield-alt me-2"></i>JoJeTech Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php foreach ($admin_nav_items as $item): 
                        $is_active = strpos($_SERVER['PHP_SELF'], basename($item['url'])) !== false;
                    ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $is_active ? 'active' : ''; ?>" 
                               href="<?php echo $item['url']; ?>">
                                <i class="fas fa-<?php echo $item['icon']; ?> me-1"></i>
                                <?php echo strip_tags($item['text']); ?>
                                <?php 
                                // Extract badge from text if exists
                                $badge_match = [];
                                if (preg_match('/<span class="badge.*?>(.*?)<\/span>/', $item['text'], $badge_match)) {
                                    echo '<span class="badge bg-danger ms-2">' . $badge_match[1] . '</span>';
                                }
                                ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                            <li>
                                <a class="dropdown-item" href="/ISPSystem/admin/profile.php">
                                    <i class="fas fa-user-cog me-2"></i>Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/ISPSystem/admin/settings.php">
                                    <i class="fas fa-cogs me-2"></i>Settings
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

            var dropdownToggle = document.getElementById('adminDropdown');
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