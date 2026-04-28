<?php
// I-enable ang error reporting para makita ang mali sa logs
error_reporting(E_ALL);
ini_set('display_errors', 0); // Wag i-display sa screen para di masira ang JSON

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

$response = ['success' => false, 'message' => 'Unknown error'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $driver_id = $_SESSION['user_id'] ?? 0;

    if ($booking_id <= 0) {
        throw new Exception('Invalid booking ID');
    }
    
    if ($driver_id <= 0) {
        throw new Exception('Session expired. Please login again.');
    }
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
        throw new Exception('Not authorized as driver');
    }

    // Check if booking exists and is PENDING
    $check_sql = "SELECT id, status, driver_id FROM bookings WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $booking_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $booking = $result->fetch_assoc();

    if (!$booking) {
        throw new Exception('Booking not found');
    }

    if ($booking['status'] !== 'PENDING') {
        throw new Exception('Booking is no longer pending. Current status: ' . $booking['status']);
    }

    if (!empty($booking['driver_id']) && $booking['driver_id'] > 0) {
        throw new Exception('Booking already taken by another driver');
    }

    // UPDATE query - change status from PENDING to ACCEPTED
    $update_sql = "UPDATE bookings SET driver_id = ?, status = 'ACCEPTED', updated_at = NOW() WHERE id = ? AND status = 'PENDING'";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $driver_id, $booking_id);
    
    if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Booking accepted successfully';
        $response['booking_id'] = $booking_id;
    } else {
        throw new Exception('Failed to update booking. No changes made.');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Log error for debugging
if (!$response['success']) {
    error_log("Accept Booking Error: " . $response['message']);
}

echo json_encode($response);
?>