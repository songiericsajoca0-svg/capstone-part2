<?php
require_once 'includes/config.php';

// Clear remember me cookie and database token
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    // Delete token from database
    $stmt = $conn->prepare("DELETE FROM user_tokens WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    
    // Clear cookie
    setcookie('remember_token', '', time() - 3600, "/");
}

// Unset all session variables
session_unset();

// Destroy the session
session_destroy();

// Redirect to home page
header("Location: /index.php");
exit;
?>