<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'isp_db');

// First connect without database selected
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Create database if it doesn't exist
$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);

// Select the database
$conn->select_db(DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
