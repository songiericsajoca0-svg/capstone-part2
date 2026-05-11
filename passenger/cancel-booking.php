<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get booking ID from POST
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$passenger_id = $_SESSION['user_id'];

if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit;
}

// Check if booking exists and belongs to this passenger
$check_stmt = $conn->prepare("SELECT id, status, driver_id FROM bookings WHERE id = ? AND passenger_id = ?");
$check_stmt->bind_param("ii", $booking_id, $passenger_id);
$check_stmt->execute();
$booking = $check_stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

// Check if booking can be cancelled (only PENDING or ACCEPTED status)
if ($booking['status'] == 'COMPLETED') {
    echo json_encode(['success' => false, 'message' => 'Cannot cancel a completed trip']);
    exit;
}

if ($booking['status'] == 'PASSENGER PICKED UP') {
    echo json_encode(['success' => false, 'message' => 'Cannot cancel trip in progress. Please contact support.']);
    exit;
}

if ($booking['status'] == 'CANCELLED') {
    echo json_encode(['success' => false, 'message' => 'Booking is already cancelled']);
    exit;
}

// Update booking status to CANCELLED
$update_stmt = $conn->prepare("UPDATE bookings SET status = 'CANCELLED' WHERE id = ?");
$update_stmt->bind_param("i", $booking_id);
$update_stmt->execute();

if ($update_stmt->affected_rows > 0) {
    // If there was a driver assigned, we can also notify them (optional)
    // You can add notification logic here if needed
    
    echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to cancel booking']);
}
?>