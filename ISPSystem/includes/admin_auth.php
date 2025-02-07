<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Log unauthorized access attempt
    error_log("Unauthorized admin access attempt from IP: " . $_SERVER['REMOTE_ADDR']);
    
    // Set error message
    $_SESSION['error'] = "You must be logged in as an administrator to access this page.";
    
    // Redirect to login page
    header('Location: ../login.php');
    exit();
}

// Optional: Additional admin-specific authentication checks can be added here
// For example, checking admin status in the database, checking IP whitelist, etc.

// Log admin page access
function logAdminAccess($page) {
    error_log(sprintf(
        "Admin Access: User ID %d accessed %s from IP %s", 
        $_SESSION['user_id'], 
        $page, 
        $_SERVER['REMOTE_ADDR']
    ));
}

// Call log function with current page
logAdminAccess(basename($_SERVER['PHP_SELF']));
