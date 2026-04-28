<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$driver_id = $_SESSION['user_id'];
$item_name = $_POST['item_name'] ?? '';
$points_required = intval($_POST['points'] ?? 0);

if (empty($item_name) || $points_required <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Get current points
$points_query = $conn->prepare("SELECT total_points FROM driver_points WHERE driver_id = ?");
$points_query->bind_param("i", $driver_id);
$points_query->execute();
$current_points = $points_query->get_result()->fetch_assoc()['total_points'] ?? 0;

if ($current_points < $points_required) {
    echo json_encode(['success' => false, 'message' => 'Insufficient points']);
    exit;
}

// Deduct points
$update_points = $conn->prepare("
    UPDATE driver_points 
    SET total_points = total_points - ?, claimed_points = claimed_points + ? 
    WHERE driver_id = ?
");
$update_points->bind_param("iii", $points_required, $points_required, $driver_id);
$update_points->execute();

// Add to history
$add_history = $conn->prepare("
    INSERT INTO points_history (driver_id, points, type, item_name, reference_type) 
    VALUES (?, ?, 'CLAIMED', ?, 'REWARD_REDEMPTION')
");
$add_history->bind_param("iis", $driver_id, $points_required, $item_name);
$add_history->execute();

// Get remaining points
$remaining_query = $conn->prepare("SELECT total_points FROM driver_points WHERE driver_id = ?");
$remaining_query->bind_param("i", $driver_id);
$remaining_query->execute();
$remaining_points = $remaining_query->get_result()->fetch_assoc()['total_points'] ?? 0;

echo json_encode([
    'success' => true,
    'message' => 'Reward claimed successfully',
    'remaining_points' => $remaining_points,
    'item_name' => $item_name
]);
?>