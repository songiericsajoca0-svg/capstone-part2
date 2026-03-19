<?php
// driver/api/update-status.php
require_once '../../includes/config.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $status = $_POST['status'];
    $user_id = $_SESSION['user_id'];

    $valid = ['online', 'busy', 'offline'];
    if (!in_array($status, $valid)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    // I-update ang 'status' column sa 'users' table
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB update failed']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}