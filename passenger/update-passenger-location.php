<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['lat']) || !isset($data['lng'])) {
    echo json_encode(['success' => false, 'error' => 'Missing coordinates']);
    exit;
}

$lat = floatval($data['lat']);
$lng = floatval($data['lng']);
$passenger_id = $_SESSION['user_id'];

// Update passenger location in users table
$stmt = $conn->prepare("
    UPDATE users 
    SET lat = ?, lng = ?, last_location_update = NOW() 
    WHERE id = ? AND role = 'passenger'
");
$stmt->bind_param("ddi", $lat, $lng, $passenger_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}
?>