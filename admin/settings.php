<?php
session_start();
require_once '../config/database.php';
require_once '../includes/admin_header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = '';
    $error = '';

    try {
        // Update company information
        $company_name = $_POST['company_name'];
        $company_address = $_POST['company_address'];
        $company_phone = $_POST['company_phone'];
        $company_email = $_POST['company_email'];
        
        // Update billing settings
        $due_date_days = $_POST['due_date_days'];
        $late_fee_percentage = $_POST['late_fee_percentage'];
        
        // Update notification settings
        $enable_email_notifications = isset($_POST['enable_email_notifications']) ? 1 : 0;
        $enable_sms_notifications = isset($_POST['enable_sms_notifications']) ? 1 : 0;
        $notification_days_before = $_POST['notification_days_before'];

        // Start transaction
        $conn->begin_transaction();

        // Update or insert settings
        $settings = [
            'company_name' => $company_name,
            'company_address' => $company_address,
            'company_phone' => $company_phone,
            'company_email' => $company_email,
            'due_date_days' => $due_date_days,
            'late_fee_percentage' => $late_fee_percentage,
            'enable_email_notifications' => $enable_email_notifications,
            'enable_sms_notifications' => $enable_sms_notifications,
            'notification_days_before' => $notification_days_before
        ];

        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO settings (setting_key, setting_value) 
                   VALUES (?, ?) 
                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
        }

        $conn->commit();
        $success = "Settings updated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to update settings: " . $e->getMessage();
    }
}

// Fetch current settings
$settings = [];
$sql = "SELECT setting_key, setting_value FROM settings";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Set default values if not set
$default_settings = [
    'company_name' => 'JoJeTech Solutions',
    'company_address' => '',
    'company_phone' => '',
    'company_email' => '',
    'due_date_days' => '15',
    'late_fee_percentage' => '5',
    'enable_email_notifications' => '1',
    'enable_sms_notifications' => '0',
    'notification_days_before' => '3'
];

foreach ($default_settings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-cogs me-2"></i>System Settings</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <!-- Company Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-building me-2"></i>Company Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="company_name" class="form-label">Company Name</label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" 
                                               value="<?php echo htmlspecialchars($settings['company_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="company_email" class="form-label">Company Email</label>
                                        <input type="email" class="form-control" id="company_email" name="company_email" 
                                               value="<?php echo htmlspecialchars($settings['company_email']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="company_phone" class="form-label">Company Phone</label>
                                        <input type="tel" class="form-control" id="company_phone" name="company_phone" 
                                               value="<?php echo htmlspecialchars($settings['company_phone']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="company_address" class="form-label">Company Address</label>
                                        <textarea class="form-control" id="company_address" name="company_address" 
                                                  rows="1" required><?php echo htmlspecialchars($settings['company_address']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Billing Settings -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Billing Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="due_date_days" class="form-label">Days Until Due Date</label>
                                        <input type="number" class="form-control" id="due_date_days" name="due_date_days" 
                                               value="<?php echo htmlspecialchars($settings['due_date_days']); ?>" 
                                               min="1" max="31" required>
                                        <div class="form-text">Number of days after billing date until payment is due</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="late_fee_percentage" class="form-label">Late Fee Percentage</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="late_fee_percentage" 
                                                   name="late_fee_percentage" 
                                                   value="<?php echo htmlspecialchars($settings['late_fee_percentage']); ?>" 
                                                   min="0" max="100" step="0.01" required>
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <div class="form-text">Percentage charged for late payments</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Settings -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="enable_email_notifications" 
                                                   name="enable_email_notifications" 
                                                   <?php echo $settings['enable_email_notifications'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_email_notifications">
                                                Enable Email Notifications
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="enable_sms_notifications" 
                                                   name="enable_sms_notifications" 
                                                   <?php echo $settings['enable_sms_notifications'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_sms_notifications">
                                                Enable SMS Notifications
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="notification_days_before" class="form-label">
                                            Days Before Due Date to Send Notification
                                        </label>
                                        <input type="number" class="form-control" id="notification_days_before" 
                                               name="notification_days_before" 
                                               value="<?php echo htmlspecialchars($settings['notification_days_before']); ?>" 
                                               min="1" max="30" required>
                                        <div class="form-text">Number of days before due date to send payment reminders</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

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

// Auto-dismiss alerts after 5 seconds
window.addEventListener('DOMContentLoaded', (event) => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>
