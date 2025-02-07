<?php
// Production database settings
define('DB_HOST', 'localhost');  // Change this to your hosting provider's database host
define('DB_USER', 'your_db_username');  // Change this to your hosting provider's database username
define('DB_PASS', 'your_db_password');  // Change this to your hosting provider's database password
define('DB_NAME', '4586414_jojetech');  // Your production database name

// Connect to the database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
