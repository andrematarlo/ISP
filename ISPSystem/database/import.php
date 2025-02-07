<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'isp_db';

// Create connection
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db($dbname);

// Read SQL file
$sql = file_get_contents('isp_db.sql');

// Execute SQL
if ($conn->multi_query($sql)) {
    echo "Database schema imported successfully<br>";
} else {
    echo "Error importing schema: " . $conn->error . "<br>";
}

$conn->close();

echo "Setup complete. You can now <a href='../index.php'>go to the homepage</a>.";
?>
