<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is driver
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login as driver']);
    exit;
}

$driver_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['booking_id'])) {
    $action = $_POST['action'];
    $booking_id = (int)$_POST['booking_id'];
    
    // First, verify the booking exists and belongs to this driver
    $check_sql = "SELECT id, status FROM bookings WHERE id = $booking_id AND driver_id = $driver_id";
    $check_result = $conn->query($check_sql);
    
    if (!$check_result || $check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Booking not found or not assigned to you']);
        exit;
    }
    
    $booking = $check_result->fetch_assoc();
    $current_status = $booking['status'];
    
    switch($action) {
        case 'pickup':
            // ACCEPTED → PASSENGER PICKED UP
            if ($current_status !== 'ACCEPTED') {
                echo json_encode(['success' => false, 'message' => 'Cannot pickup: Trip status is ' . $current_status . ', expected ACCEPTED']);
                exit;
            }
            $sql = "UPDATE bookings SET status = 'PASSENGER PICKED UP', updated_at = NOW() WHERE id = $booking_id AND driver_id = $driver_id";
            $success_message = 'Passenger marked as picked up';
            break;
            
        case 'complete':
            // PASSENGER PICKED UP → COMPLETED
            if ($current_status !== 'PASSENGER PICKED UP') {
                echo json_encode(['success' => false, 'message' => 'Cannot complete: Trip status is ' . $current_status . ', expected PASSENGER PICKED UP']);
                exit;
            }
            $sql = "UPDATE bookings SET status = 'COMPLETED', updated_at = NOW() WHERE id = $booking_id AND driver_id = $driver_id";
            $success_message = 'Trip completed successfully';
            break;
            
        case 'cancel':
            // PENDING/ACCEPTED/PASSENGER PICKED UP → CANCELLED
            if (!in_array($current_status, ['PENDING', 'ACCEPTED', 'PASSENGER PICKED UP'])) {
                echo json_encode(['success' => false, 'message' => 'Cannot cancel: Trip already ' . $current_status]);
                exit;
            }
            $sql = "UPDATE bookings SET status = 'CANCELLED', driver_id = NULL, updated_at = NOW() WHERE id = $booking_id AND driver_id = $driver_id";
            $success_message = 'Trip cancelled';
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
    
    if ($conn->query($sql)) {
        if ($conn->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => $success_message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made. Status might already be updated.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method or missing parameters']);
?>