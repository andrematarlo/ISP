<?php
require_once '../config/database.php';

// Drop existing database
$conn->query("DROP DATABASE IF EXISTS isp_db");

// Read and execute SQL file
$sql = file_get_contents('isp_db.sql');

if ($conn->multi_query($sql)) {
    do {
        // Consume results to allow next query to execute
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());

    echo "Database reset successfully!<br>";
    echo "<a href='create_admin.php'>Create Admin Account</a>";
} else {
    echo "Error resetting database: " . $conn->error;
}

$conn->close();
?>
