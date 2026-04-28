<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';
require_once '../includes/auth-check.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_driver_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : 'view';

// GET: Fetch available bookings
if ($action === 'view') {
    // Get driver's toda_id from toda_drivers
    $toda_query = "SELECT toda_id FROM toda_drivers WHERE driver_id = ?";
    $toda_stmt = $conn->prepare($toda_query);
    $toda_stmt->bind_param("i", $current_driver_id);
    $toda_stmt->execute();
    $toda_result = $toda_stmt->get_result();
    
    if ($toda_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No TODA assigned', 'bookings' => []]);
        exit;
    }
    
    $driver_toda = $toda_result->fetch_assoc();
    $driver_toda_id = $driver_toda['toda_id'];
    
    // Get pending bookings for this TODA
    $sql = "SELECT b.*, u.name as passenger_name 
            FROM bookings b
            LEFT JOIN users u ON b.passenger_id = u.id
            WHERE b.toda_id = ? 
            AND b.status = 'PENDING' 
            AND (b.driver_id IS NULL OR b.driver_id = 0)
            ORDER BY b.created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $driver_toda_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($bookings),
        'bookings' => $bookings
    ]);
    
    $stmt->close();
    $toda_stmt->close();
}
// POST: Accept a booking
else if ($action === 'accept') {
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    
    if (!$booking_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if booking is still available
        $check_sql = "SELECT id, toda_id FROM bookings WHERE id = ? AND status = 'PENDING' AND (driver_id IS NULL OR driver_id = 0)";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $booking_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            throw new Exception('Booking no longer available');
        }
        
        $booking = $check_result->fetch_assoc();
        $booking_toda_id = $booking['toda_id'];
        
        // Verify driver belongs to this TODA
        $verify_sql = "SELECT id FROM toda_drivers WHERE driver_id = ? AND toda_id = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("ii", $current_driver_id, $booking_toda_id);
        $verify_stmt->execute();
        
        if ($verify_stmt->get_result()->num_rows === 0) {
            throw new Exception('You are not authorized for this booking');
        }
        
        // Assign booking to driver
        $update_sql = "UPDATE bookings SET driver_id = ?, status = 'ACCEPTED', assigned_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $current_driver_id, $booking_id);
        $update_stmt->execute();
        
        if ($update_stmt->affected_rows === 0) {
            throw new Exception('Failed to accept booking');
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Booking accepted successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    $conn->close();
}
?>