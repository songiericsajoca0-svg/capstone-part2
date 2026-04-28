<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$driver_id = $_POST['driver_id'] ?? $_SESSION['user_id'];
$today_date = date('Y-m-d');

// Get today's completed trips
$today_query = $conn->prepare("
    SELECT COUNT(*) as today_trips, SUM(fare_amount) as today_earnings
    FROM bookings 
    WHERE driver_id = ? 
    AND status = 'COMPLETED' 
    AND DATE(created_at) = ?
");
$today_query->bind_param("is", $driver_id, $today_date);
$today_query->execute();
$today_stats = $today_query->get_result()->fetch_assoc();

$today_trips = $today_stats['today_trips'] ?? 0;
$today_earnings = $today_stats['today_earnings'] ?? 0;
$avg_per_trip = $today_trips > 0 ? $today_earnings / $today_trips : 0;

// Get lifetime stats
$lifetime_query = $conn->prepare("
    SELECT COUNT(*) as lifetime_trips, SUM(fare_amount) as lifetime_earnings
    FROM bookings 
    WHERE driver_id = ? AND status = 'COMPLETED'
");
$lifetime_query->bind_param("i", $driver_id);
$lifetime_query->execute();
$lifetime_stats = $lifetime_query->get_result()->fetch_assoc();

// Check for new bookings
$new_booking_query = $conn->prepare("
    SELECT COUNT(*) as new_bookings
    FROM bookings 
    WHERE driver_id = ? 
    AND status IN ('ASSIGNED', 'PASSENGER PICKED UP', 'IN TRANSIT')
    AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
");
$new_booking_query->bind_param("i", $driver_id);
$new_booking_query->execute();
$new_booking_result = $new_booking_query->get_result()->fetch_assoc();

echo json_encode([
    'success' => true,
    'today_trips' => $today_trips,
    'today_earnings' => $today_earnings,
    'avg_per_trip' => $avg_per_trip,
    'lifetime_trips' => $lifetime_stats['lifetime_trips'] ?? 0,
    'lifetime_earnings' => $lifetime_stats['lifetime_earnings'] ?? 0,
    'has_new_booking' => ($new_booking_result['new_bookings'] ?? 0) > 0
]);
?>