<?php
// Detect if we're in production or local environment
$is_production = false;

// Check if we're on the production server
if (isset($_SERVER['SERVER_NAME'])) {
    $server_name = $_SERVER['SERVER_NAME'];
    if (strpos($server_name, 'localhost') === false && strpos($server_name, '127.0.0.1') === false) {
        $is_production = true;
    }
}

if ($is_production) {
    // Production database settings
    define('DB_HOST', 'localhost');  // Change this to your hosting provider's database host
    define('DB_USER', 'your_db_username');  // Change this to your hosting provider's database username
    define('DB_PASS', 'your_db_password');  // Change this to your hosting provider's database password
    define('DB_NAME', '4586414_jojetech');  // Your production database name
} else {
    // Local development settings
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'isp_database');
}

if (!$is_production) {
    // First connect without database selected (only in local development)
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    // Create database if it doesn't exist (only in local development)
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    
    // Select the database
    $conn->select_db(DB_NAME);
} else {
    // In production, connect directly to the database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
