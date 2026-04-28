<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_GET['driver_id']) || !isset($_GET['booking_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$driver_id = (int)$_GET['driver_id'];
$booking_id = (int)$_GET['booking_id'];

// Verify that this booking belongs to the current passenger
$pid = $_SESSION['user_id'] ?? 0;
$verify_stmt = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND passenger_id = ? AND driver_id = ?");
$verify_stmt->bind_param("iii", $booking_id, $pid, $driver_id);
$verify_stmt->execute();
$result = $verify_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get driver's latest location from users table
$stmt = $conn->prepare("
    SELECT lat, lng, last_location_update as updated_at 
    FROM users 
    WHERE id = ? AND role = 'driver' AND lat IS NOT NULL AND lng IS NOT NULL
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$location = $stmt->get_result()->fetch_assoc();

if ($location && $location['lat'] && $location['lng']) {
    echo json_encode([
        'success' => true,
        'lat' => floatval($location['lat']),
        'lng' => floatval($location['lng']),
        'last_updated' => date('h:i:s A', strtotime($location['updated_at'] ?? 'now'))
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Driver location not available yet']);
}
?>