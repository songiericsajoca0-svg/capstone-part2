<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$last_check = isset($_POST['last_check']) ? (int)$_POST['last_check'] : 0;
$last_check_date = date('Y-m-d H:i:s', $last_check / 1000);

// Check for new pending bookings (status = 'PENDING' and no driver assigned yet)
$query = $conn->prepare("
    SELECT COUNT(*) as new_count, 
           (SELECT COUNT(*) FROM bookings WHERE status = 'PENDING' AND driver_id IS NULL) as total_pending
    FROM bookings 
    WHERE status = 'PENDING' 
    AND driver_id IS NULL
    AND created_at > ?
");
$query->bind_param("s", $last_check_date);
$query->execute();
$result = $query->get_result();
$data = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'new_bookings' => (int)$data['new_count'],
    'total_pending' => (int)$data['total_pending'],
    'current_time' => time() * 1000
]);
?>