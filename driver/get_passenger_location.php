<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_GET['passenger_id']) || !isset($_GET['booking_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$passenger_id = (int)$_GET['passenger_id'];
$booking_id = (int)$_GET['booking_id'];

// Verify that the driver is assigned to this booking
$driver_id = $_SESSION['user_id'] ?? 0;
$verify_query = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND driver_id = ?");
$verify_query->bind_param("ii", $booking_id, $driver_id);
$verify_query->execute();
if ($verify_query->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get passenger location
$query = $conn->prepare("SELECT lat, lng, last_location_update FROM users WHERE id = ? AND role = 'passenger'");
$query->bind_param("i", $passenger_id);
$query->execute();
$result = $query->get_result()->fetch_assoc();

if ($result && !empty($result['lat']) && !empty($result['lng'])) {
    echo json_encode([
        'success' => true,
        'lat' => $result['lat'],
        'lng' => $result['lng'],
        'last_location_update' => $result['last_location_update']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Passenger location not available']);
}
?>