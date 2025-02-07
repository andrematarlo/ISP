<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get all plans with subscription counts
$sql = "SELECT p.*, 
               COUNT(s.id) as total_subscriptions,
               SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_subscriptions
        FROM plans p
        LEFT JOIN subscriptions s ON p.id = s.plan_id
        GROUP BY p.id
        ORDER BY p.price ASC";
$result = $conn->query($sql);

require_once '../includes/admin_header.php';
?>

<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Internet Plans</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
            <i class="fas fa-plus"></i> Add New Plan
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="plansTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Speed</th>
                            <th>Price</th>
                            <th>Subscriptions</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($plan = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $plan['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($plan['name']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($plan['description']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($plan['speed']); ?></td>
                                <td>₱<?php echo number_format($plan['price'], 2); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $plan['total_subscriptions']; ?> Total</span>
                                    <span class="badge bg-success"><?php echo $plan['active_subscriptions']; ?> Active</span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $plan['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($plan['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" 
                                                class="btn btn-sm btn-primary edit-plan"
                                                data-plan='<?php echo json_encode([
                                                    'id' => $plan['id'],
                                                    'name' => $plan['name'],
                                                    'speed' => $plan['speed'],
                                                    'price' => $plan['price'],
                                                    'description' => $plan['description'],
                                                    'status' => $plan['status']
                                                ]); ?>'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($plan['status'] === 'active'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger deactivate-plan"
                                                    data-id="<?php echo $plan['id']; ?>">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-success activate-plan"
                                                    data-id="<?php echo $plan['id']; ?>">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Add Plan Modal -->
<div class="modal fade" id="addPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addPlanForm">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="name" class="form-label">Plan Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="speed" class="form-label">Speed</label>
                        <input type="text" class="form-control" id="speed" name="speed" required>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Price</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" class="form-control" id="price" name="price" step="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Plan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="editPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editPlanForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="edit_plan_id" name="plan_id">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Plan Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_speed" class="form-label">Speed</label>
                        <input type="text" class="form-control" id="edit_speed" name="speed" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_price" class="form-label">Price</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" class="form-control" id="edit_price" name="price" step="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Plan</button>
                </form>
            </div>
        </div>
    </div>
</div>

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
                $('#plansTable').DataTable({
                    order: [[3, 'asc']]
                });
            } else {
                console.error('DataTables not loaded');
                alert('DataTables library failed to load. Please refresh the page.');
            }

            // Handle plan edit button
            $('.edit-plan').click(function() {
                const planData = $(this).data('plan');
                $('#edit_plan_id').val(planData.id);
                $('#edit_name').val(planData.name);
                $('#edit_speed').val(planData.speed);
                $('#edit_price').val(planData.price);
                $('#edit_description').val(planData.description);
                $('#editPlanModal').modal('show');
            });

            // Handle add plan form submission
            $('#addPlanForm').submit(function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                
                $.ajax({
                    url: 'process_plan.php',
                    method: 'POST',
                    dataType: 'json',
                    data: formData,
                    success: function(response) {
                        alert('Plan added successfully');
                        location.reload();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error adding plan:', xhr.responseText);
                        
                        let errorMessage = 'Unknown error occurred';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            errorMessage = response.error || errorMessage;
                        } catch (e) {
                            errorMessage = xhr.responseText || errorMessage;
                        }
                        
                        alert('Error adding plan: ' + errorMessage);
                    }
                });
            });

            // Handle edit plan form submission
            $('#editPlanForm').submit(function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                
                $.ajax({
                    url: 'process_plan.php',
                    method: 'POST',
                    dataType: 'json',
                    data: formData,
                    success: function(response) {
                        alert('Plan updated successfully');
                        location.reload();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating plan:', xhr.responseText);
                        
                        let errorMessage = 'Unknown error occurred';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            errorMessage = response.error || errorMessage;
                        } catch (e) {
                            errorMessage = xhr.responseText || errorMessage;
                        }
                        
                        alert('Error updating plan: ' + errorMessage);
                    }
                });
            });

            // Handle plan activation/deactivation
            $('.activate-plan, .deactivate-plan').click(function() {
                const planId = $(this).data('id');
                const confirmMessage = $(this).hasClass('activate-plan') 
                    ? 'Are you sure you want to activate this plan?' 
                    : 'Are you sure you want to deactivate this plan?';
                
                if (confirm(confirmMessage)) {
                    $.ajax({
                        url: 'process_plan.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'change_status',
                            plan_id: planId
                        },
                        success: function(response) {
                            alert('Plan status updated successfully');
                            location.reload();
                        },
                        error: function(xhr, status, error) {
                            console.error('Error changing plan status:', xhr.responseText);
                            
                            let errorMessage = 'Unknown error occurred';
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMessage = response.error || errorMessage;
                            } catch (e) {
                                errorMessage = xhr.responseText || errorMessage;
                            }
                            
                            alert('Error changing plan status: ' + errorMessage);
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
