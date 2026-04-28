<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/functions.php';

if ($_SESSION['role'] !== 'admin' || !isset($_GET['id']) || !isset($_GET['status'])) {
    header("Location: /dashboard.php");
    exit;
}

$bid = (int)$_GET['id'];
$new_status = $_GET['status'];

$allowed_transitions = [
    'PASSENGER PICKED UP' => 'IN TRANSIT'
];

$stmt = $conn->prepare("SELECT status FROM bookings WHERE id = ?");
$stmt->bind_param("i", $bid);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc()['status'];

if (isset($allowed_transitions[$current]) && $allowed_transitions[$current] === $new_status) {
    $time_field = $new_status === 'IN TRANSIT' ? 'pickup_time' : 'dropoff_time'; // adjust if needed
    $up = $conn->prepare("UPDATE bookings SET status = ?, $time_field = NOW() WHERE id = ?");
    $up->bind_param("si", $new_status, $bid);
    
    if ($up->execute()) {
        log_activity($_SESSION['user_id'], 'STATUS_UPDATE', "Booking #$bid set to $new_status");
        header("Location: /dashboard.php?msg=Status updated to $new_status");
    } else {
        header("Location: /dashboard.php?error=Update failed");
    }
} else {
    header("Location: /dashboard.php?error=Invalid status transition");
}
exit;
?>