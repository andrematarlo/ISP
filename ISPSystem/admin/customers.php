<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get all customers with their subscription details
$sql = "SELECT c.*, u.email, u.username,
               s.id as subscription_id, s.status as subscription_status,
               COUNT(DISTINCT s.id) as total_subscriptions,
               SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_subscriptions,
               SUM(CASE WHEN b.status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_bills
        FROM customers c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN subscriptions s ON c.id = s.customer_id AND s.status = 'active'
        LEFT JOIN bills b ON s.id = b.subscription_id
        GROUP BY c.id
        ORDER BY c.created_at DESC";
$result = $conn->query($sql);

require_once '../includes/admin_header.php';
?>

<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Customers</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
            <i class="fas fa-user-plus"></i> Add New Customer
        </button>
    </div>

    <div class="card">
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
                                    <?php if ($customer['subscription_id']): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger cancel-subscription"
                                                data-id="<?php echo $customer['subscription_id']; ?>">
                                            <i class="fas fa-trash-alt"></i>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addCustomerForm" action="process_customer.php" method="POST">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Customer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Subscription Modal -->
<div class="modal fade" id="cancelSubscriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Subscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this subscription?</p>
                <button type="button" class="btn btn-danger" id="cancel-subscription-btn">Cancel Subscription</button>
            </div>
        </div>
    </div>
</div>

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
            $('.cancel-subscription').click(function() {
                const subscriptionId = $(this).data('id');
                
                // Check if subscription ID is valid
                if (!subscriptionId) {
                    alert('No active subscription found for this customer.');
                    return;
                }

                // Show confirmation modal
                $('#cancelSubscriptionModal').modal('show');
                
                // Unbind previous click events to prevent multiple bindings
                $('#cancel-subscription-btn').off('click').on('click', function() {
                    // Disable button to prevent multiple submissions
                    $(this).prop('disabled', true).html('Cancelling...');

                    $.ajax({
                        url: 'cancel_subscription.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            subscription_id: subscriptionId,
                            cancel_reason: 'Admin Cancellation'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Subscription successfully cancelled.');
                                location.reload();
                            } else {
                                alert('Error: ' + response.message);
                                $('#cancel-subscription-btn').prop('disabled', false).html('Cancel Subscription');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Subscription cancellation error:', error);
                            
                            let errorMessage = 'An unexpected error occurred';
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMessage = response.message || errorMessage;
                            } catch (e) {
                                errorMessage = xhr.responseText || errorMessage;
                            }

                            alert('Error cancelling subscription: ' + errorMessage);
                            $('#cancel-subscription-btn').prop('disabled', false).html('Cancel Subscription');
                        }
                    });
                });
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
