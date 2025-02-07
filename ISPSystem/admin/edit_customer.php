<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success = $error = '';

// Get customer ID from URL
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$customer_id) {
    header('Location: customers.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = $conn->real_escape_string($_POST['email']);
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
        $conn->begin_transaction();
        try {
            // Update user email
            $sql = "UPDATE users u 
                    JOIN customers c ON u.id = c.user_id 
                    SET u.email = ? 
                    WHERE c.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $email, $customer_id);
            $stmt->execute();

            // Update customer details
            $sql = "UPDATE customers 
                    SET full_name = ?, address = ?, phone = ?, status = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssssi', $full_name, $address, $phone, $status, $customer_id);
            $stmt->execute();

            $conn->commit();
            $success = 'Customer updated successfully!';
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Error updating customer: ' . $e->getMessage();
        }
    }
}

// Get customer details
$sql = "SELECT c.*, u.username, u.email 
        FROM customers c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    header('Location: customers.php');
    exit();
}
?>

<?php require_once '../includes/admin_header.php'; ?>

    <main class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Edit Customer</h4>
                        <a href="customers.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="row">
                                <!-- Account Information -->
                                <div class="col-md-6">
                                    <h5 class="mb-3">Account Information</h5>
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($customer['username']); ?>" disabled>
                                        <small class="form-text text-muted">Username cannot be changed</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                                        <div class="invalid-feedback">Please enter a valid email.</div>
                                    </div>
                                </div>

                                <!-- Personal Information -->
                                <div class="col-md-6">
                                    <h5 class="mb-3">Personal Information</h5>
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($customer['full_name']); ?>" required>
                                        <div class="invalid-feedback">Please enter the full name.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($customer['phone']); ?>"
                                               placeholder="+63 XXX XXX XXXX or 09XX XXX XXXX"
                                               pattern="^(\+63|09)[0-9]{9}$"
                                               required>
                                        <div class="invalid-feedback">Please enter a valid Philippine phone number (09XX XXX XXXX or +63 XXX XXX XXXX).</div>
                                        <small class="form-text text-muted">Format: +63 XXX XXX XXXX or 09XX XXX XXXX</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active" <?php echo $customer['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $customer['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <div class="invalid-feedback">Please select a status.</div>
                                    </div>
                                </div>

                                <!-- Address -->
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($customer['address']); ?></textarea>
                                        <div class="invalid-feedback">Please enter an address.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="view_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Customer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once '../includes/admin_footer.php'; ?>
