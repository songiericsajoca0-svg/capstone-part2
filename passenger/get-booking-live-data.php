<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (!$booking_id || !$token) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Get booking to verify token
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo json_encode(['success' => false, 'error' => 'Booking not found']);
    exit;
}

$expected_token = md5($booking['booking_code'] . $booking['passenger_id']);
if ($token !== $expected_token) {
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

// Get driver location
$driver_lat = null;
$driver_lng = null;
$has_driver = !empty($booking['driver_id']);

if ($has_driver && $booking['status'] != 'CANCELLED') {
    $driver_stmt = $conn->prepare("SELECT lat, lng FROM users WHERE id = ? AND role = 'driver'");
    $driver_stmt->bind_param("i", $booking['driver_id']);
    $driver_stmt->execute();
    $driver_loc = $driver_stmt->get_result()->fetch_assoc();
    if ($driver_loc && !empty($driver_loc['lat'])) {
        $driver_lat = floatval($driver_loc['lat']);
        $driver_lng = floatval($driver_loc['lng']);
    }
}

// Get passenger location
$passenger_lat = null;
$passenger_lng = null;
$passenger_stmt = $conn->prepare("SELECT lat, lng FROM users WHERE id = ?");
$passenger_stmt->bind_param("i", $booking['passenger_id']);
$passenger_stmt->execute();
$passenger_loc = $passenger_stmt->get_result()->fetch_assoc();
if ($passenger_loc && !empty($passenger_loc['lat'])) {
    $passenger_lat = floatval($passenger_loc['lat']);
    $passenger_lng = floatval($passenger_loc['lng']);
}

echo json_encode([
    'success' => true,
    'hasDriver' => $has_driver && $driver_lat !== null,
    'isPickedUp' => ($booking['status'] == 'PASSENGER PICKED UP'),
    'isCompleted' => ($booking['status'] == 'COMPLETED'),
    'isCancelled' => ($booking['status'] == 'CANCELLED'),
    'driverLat' => $driver_lat,
    'driverLng' => $driver_lng,
    'passengerLat' => $passenger_lat,
    'passengerLng' => $passenger_lng
]);
?>