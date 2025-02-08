<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle Add Customer Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    
    // Validate input
    $errors = [];
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password)) $errors[] = "Password is required";
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($phone)) $errors[] = "Phone is required";
    if (empty($address)) $errors[] = "Address is required";
    
    // Check if username or email already exists
    if (empty($errors)) {
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Username or email already exists";
        }
    }
    
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'customer')";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("sss", $username, $email, $hashed_password);
            $user_stmt->execute();
            $user_id = $conn->insert_id;
            
            // Create customer profile
            $customer_sql = "INSERT INTO customers (user_id, full_name, email, phone, address, status) 
                           VALUES (?, ?, ?, ?, ?, 'active')";
            $customer_stmt = $conn->prepare($customer_sql);
            $customer_stmt->bind_param("issss", $user_id, $full_name, $email, $phone, $address);
            $customer_stmt->execute();
            
            $conn->commit();
            $_SESSION['success'] = "Customer added successfully!";
            header('Location: customers.php');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Failed to add customer. Please try again.";
        }
    }
}

// Get all customers with their subscription details
$sql = "SELECT 
            c.*,
            u.email,
            u.username,
            GROUP_CONCAT(DISTINCT s.id) as subscription_ids,
            GROUP_CONCAT(DISTINCT s.status) as subscription_statuses,
            COUNT(DISTINCT s.id) as total_subscriptions,
            SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_subscriptions,
            SUM(CASE WHEN b.status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_bills,
            MAX(CASE WHEN s.status = 'active' THEN s.id ELSE NULL END) as active_subscription_id
        FROM customers c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN subscriptions s ON c.id = s.customer_id
        LEFT JOIN bills b ON s.id = b.subscription_id
        GROUP BY c.id, u.email, u.username
        ORDER BY c.created_at DESC";
$result = $conn->query($sql);

require_once '../includes/admin_header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users me-2"></i>Customers</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
            <i class="fas fa-user-plus me-2"></i>Add New Customer
        </button>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="customersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Subscriptions</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                            <th>Cancel Subscription</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($customer = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $customer['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($customer['full_name']); ?><br>
                                    <small class="text-muted"><?php echo $customer['email']; ?></small>
                                </td>
                                <td>
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($customer['phone']); ?><br>
                                    <small><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($customer['address']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $customer['total_subscriptions']; ?> Total</span>
                                    <span class="badge bg-success"><?php echo $customer['active_subscriptions']; ?> Active</span>
                                    <?php if ($customer['unpaid_bills'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $customer['unpaid_bills']; ?> Unpaid Bills</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $customer['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($customer['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_customer.php?id=<?php echo $customer['id']; ?>" 
                                           class="btn btn-sm btn-info text-white">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($customer['status'] === 'active'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger change-customer-status"
                                                    data-id="<?php echo $customer['id']; ?>"
                                                    data-status="inactive">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-success change-customer-status"
                                                    data-id="<?php echo $customer['id']; ?>"
                                                    data-status="active">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($customer['active_subscription_id']): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger cancel-subscription"
                                                data-id="<?php echo $customer['active_subscription_id']; ?>">
                                            <i class="fas fa-trash-alt"></i> Cancel
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">No Active Subscription</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="invalid-feedback">Please enter a username</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback">Please enter a valid email</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Please enter a password</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                            <div class="invalid-feedback">Please enter the full name</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                            <div class="invalid-feedback">Please enter a phone number</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="1" required></textarea>
                            <div class="invalid-feedback">Please enter an address</div>
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_customer" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Customer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    
    togglePassword.addEventListener('click', function() {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.querySelector('i').classList.toggle('fa-eye');
        this.querySelector('i').classList.toggle('fa-eye-slash');
    });

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>

<!-- Add DataTables CSS -->
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<script>
// Ensure jQuery is available
(function() {
    // Function to dynamically load a script
    function loadScript(url, callback) {
        var script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = url;
        
        script.onload = callback;
        script.onerror = function() {
            console.error('Script loading error: ' + url);
        };
        
        document.head.appendChild(script);
    }

    // Function to load multiple scripts sequentially
    function loadScripts(urls, callback) {
        function loadNext(index) {
            if (index < urls.length) {
                loadScript(urls[index], function() {
                    loadNext(index + 1);
                });
            } else if (callback) {
                callback();
            }
        }
        loadNext(0);
    }

    // Check and load jQuery if not present
    function initializePage() {
        // Ensure jQuery is available
        if (typeof jQuery === 'undefined') {
            loadScript('https://code.jquery.com/jquery-3.6.0.min.js', function() {
                // Ensure global availability
                window.jQuery = window.$ = jQuery.noConflict(true);
                
                // Load DataTables after jQuery
                loadScripts([
                    'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js',
                    'https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js'
                ], setupPage);
            });
        } else {
            // jQuery already loaded, just load DataTables
            loadScripts([
                'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js',
                'https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js'
            ], setupPage);
        }
    }

    // Main page setup function
    function setupPage() {
        $(document).ready(function() {
            // Check if DataTables is available
            if ($.fn.DataTable) {
                // Initialize DataTable
                $('#customersTable').DataTable({
                    order: [[5, 'desc']]
                });
            } else {
                console.error('DataTables not loaded');
                alert('DataTables library failed to load. Please refresh the page.');
            }

            // Handle customer status change
            $('.change-customer-status').click(function() {
                const customerId = $(this).data('id');
                const newStatus = $(this).data('status');
                
                console.log(`Attempting to change status for customer ${customerId} to ${newStatus}`);
                
                if (confirm('Are you sure you want to ' + (newStatus === 'active' ? 'activate' : 'deactivate') + ' this customer?')) {
                    $.ajax({
                        url: 'update_customer_status.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            customer_id: customerId,
                            status: newStatus
                        },
                        success: function(response) {
                            console.log('Status update successful:', response);
                            location.reload();
                        },
                        error: function(xhr, status, error) {
                            // Log full error details
                            console.error('Full AJAX error details:', {
                                status: status,
                                error: error,
                                responseText: xhr.responseText,
                                readyState: xhr.readyState,
                                responseType: xhr.responseType
                            });

                            // Try to parse error response
                            let errorMessage = 'Unknown error occurred';
                            try {
                                // First try to parse as JSON
                                const response = JSON.parse(xhr.responseText);
                                errorMessage = response.error || errorMessage;
                            } catch (parseError) {
                                // If not JSON, use the raw response text
                                errorMessage = xhr.responseText || errorMessage;
                            }

                            // Fallback error handling
                            switch (xhr.status) {
                                case 400:
                                    errorMessage = 'Bad Request: ' + errorMessage;
                                    break;
                                case 401:
                                    errorMessage = 'Unauthorized: Please log in again';
                                    break;
                                case 403:
                                    errorMessage = 'Forbidden: You do not have permission';
                                    break;
                                case 404:
                                    errorMessage = 'Not Found: The requested resource could not be found';
                                    break;
                                case 500:
                                    errorMessage = 'Server Error: ' + errorMessage;
                                    break;
                                default:
                                    errorMessage = `Error ${xhr.status}: ` + errorMessage;
                            }

                            // Display error to user
                            alert('Error updating customer status: ' + errorMessage);
                        }
                    });
                }
            });

            // Handle subscription cancellation
            $('.cancel-subscription').click(function(e) {
                e.preventDefault();
                console.log('Cancel subscription button clicked');
                
                const subscriptionId = $(this).data('id');
                console.log('Subscription ID:', subscriptionId);
                
                // Check if subscription ID is valid
                if (!subscriptionId) {
                    console.error('No subscription ID found');
                    alert('No active subscription found for this customer.');
                    return;
                }

                // Show confirmation dialog first
                if (confirm('Are you sure you want to cancel this subscription? This action cannot be undone.')) {
                    console.log('User confirmed cancellation');
                    
                    // Send AJAX request
                    $.ajax({
                        url: 'cancel_subscription.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            subscription_id: subscriptionId,
                            cancel_reason: 'Admin Cancellation'
                        },
                        beforeSend: function() {
                            console.log('Sending cancellation request...');
                        },
                        success: function(response) {
                            console.log('Server response:', response);
                            if (response.success) {
                                alert('Subscription successfully cancelled.');
                                location.reload();
                            } else {
                                console.error('Server returned error:', response.message);
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', {
                                status: status,
                                error: error,
                                response: xhr.responseText
                            });
                            
                            let errorMessage = 'An unexpected error occurred';
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMessage = response.message || errorMessage;
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                errorMessage = xhr.responseText || errorMessage;
                            }

                            alert('Error cancelling subscription: ' + errorMessage);
                        }
                    });
                }
            });
        });
    }

    // Start initialization when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePage);
    } else {
        initializePage();
    }
})();</script>

<?php require_once '../includes/footer.php'; ?>
