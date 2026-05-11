<?php
require_once 'config.php';

// 1. Check if user is logged in using improved function with Remember Me support
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$role = $_SESSION['role'] ?? '';
$current_script = $_SERVER['SCRIPT_NAME'];
$is_admin_folder = (strpos($current_script, '/admin/') !== false);

// 2. Role-based Access Control
if ($role === 'passenger') {
    // PASSENGER: Cannot access admin folder unless it's the scanner
    if ($is_admin_folder && basename($_SERVER['PHP_SELF']) !== 'scanner.php') {
        header("Location: ../passenger/dashboard.php");
        exit;
    }
} elseif ($role === 'driver') {
    // DRIVER: Cannot access admin folder
    if ($is_admin_folder) {
        header("Location: ../driver/driver_dashboard.php");
        exit;
    }
}
?>