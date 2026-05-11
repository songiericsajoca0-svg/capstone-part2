<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'completed_count' => 0, 'total_earnings' => 0]);
    exit;
}

$driver_id = $_SESSION['user_id'];

// Get completed trips count (COMPLETED status only)
$completed_result = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE driver_id = $driver_id AND status = 'COMPLETED'");
$completed = $completed_result->fetch_assoc();

// Get total earnings (COMPLETED status only)
$earnings_result = $conn->query("SELECT SUM(fare_amount) as total FROM bookings WHERE driver_id = $driver_id AND status = 'COMPLETED'");
$earnings = $earnings_result->fetch_assoc();

// Get active trips count (ACCEPTED or PASSENGER PICKED UP)
$active_result = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE driver_id = $driver_id AND status IN ('ACCEPTED', 'PASSENGER PICKED UP')");
$active = $active_result->fetch_assoc();

echo json_encode([
    'success' => true,
    'completed_count' => (int)($completed['total'] ?? 0),
    'total_earnings' => (float)($earnings['total'] ?? 0),
    'active_count' => (int)($active['total'] ?? 0)
]);
?>