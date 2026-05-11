<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

// Check if driver is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['lat']) || !isset($data['lng'])) {
    echo json_encode(['success' => false, 'message' => 'Missing coordinates']);
    exit;
}

$driver_id = $_SESSION['user_id'];
$lat = floatval($data['lat']);
$lng = floatval($data['lng']);
$booking_id = isset($data['booking_id']) ? intval($data['booking_id']) : null;
$status = isset($data['status']) ? $data['status'] : null;

// Update driver's location in users table
$update_query = $conn->prepare("UPDATE users SET lat = ?, lng = ?, last_location_update = NOW() WHERE id = ? AND role = 'driver'");
$update_query->bind_param("ddi", $lat, $lng, $driver_id);

if ($update_query->execute()) {
    // Also log location for tracking history (optional)
    if ($booking_id) {
        $log_query = $conn->prepare("INSERT INTO driver_location_log (driver_id, booking_id, lat, lng, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $log_query->bind_param("iidss", $driver_id, $booking_id, $lat, $lng, $status);
        $log_query->execute();
    }
    
    echo json_encode(['success' => true, 'message' => 'Location updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>