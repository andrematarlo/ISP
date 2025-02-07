<?php
require_once '../config/database.php';

// Admin credentials
$username = 'admin';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$email = 'admin@ispsystem.com';
$role = 'admin';

// Check if admin already exists
$check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param('ss', $username, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "Admin account already exists!";
} else {
    // Create admin user
    $sql = "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $username, $password, $email, $role);
    
    if ($stmt->execute()) {
        echo "Admin account created successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
        echo "<a href='../login.php'>Go to Login Page</a>";
    } else {
        echo "Error creating admin account: " . $conn->error;
    }
}

$conn->close();
?>
