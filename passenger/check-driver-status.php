<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !isset($_SESSION['user_id'])) {
    echo json_encode(['has_driver' => false]);
    exit;
}

$booking_id = (int)$_GET['id'];
$passenger_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT driver_id, status FROM bookings WHERE id = ? AND passenger_id = ?");
$stmt->bind_param("ii", $booking_id, $passenger_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if ($booking && !empty($booking['driver_id'])) {
    // KUNG MAY DRIVER AT PENDING ANG STATUS, I-UPDATE SA ACCEPTED
    if ($booking['status'] == 'PENDING') {
        $update_stmt = $conn->prepare("UPDATE bookings SET status = 'ACCEPTED' WHERE id = ?");
        $update_stmt->bind_param("i", $booking_id);
        $update_stmt->execute();
    }
    echo json_encode(['has_driver' => true]);
} else {
    echo json_encode(['has_driver' => false]);
}
?>