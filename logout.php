<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session completely
session_destroy();

// Log the logout attempt
error_log("User logged out. Session ID: " . session_id());

// Redirect to login page with a success message
$_SESSION['logout_success'] = "You have been successfully logged out.";
header('Location: login.php');
exit();
?>
