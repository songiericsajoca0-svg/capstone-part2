<?php
require_once 'includes/config.php';
require_once 'includes/auth-check.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$bid = (int)$_POST['booking_id'];
$reason = sanitize($_POST['reason'] ?? 'No reason provided');
$role = $_SESSION['role'] ?? 'guest';

$stmt = $conn->prepare("SELECT status FROM bookings WHERE id = ?");
$stmt->bind_param("i", $bid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row || !in_array($row['status'], ['PENDING', 'ASSIGNED'])) {
    $msg = "Cannot cancel this booking (already in progress or completed).";
    $type = "error";
} else {
    $up = $conn->prepare("UPDATE bookings SET status = 'CANCELLED', notes = CONCAT(notes, '\nCancelled: ', ?) WHERE id = ?");
    $up->bind_param("si", $reason, $bid);
    
    if ($up->execute() && $up->affected_rows > 0) {
        log_activity($_SESSION['user_id'] ?? 0, 'CANCEL_BOOKING', "Booking #$bid cancelled by $role. Reason: $reason");
        $msg = "Booking cancelled successfully.";
        $type = "success";
    } else {
        $msg = "Failed to cancel booking.";
        $type = "error";
    }
}

// Redirect back kung saan galing
$redirect = $_POST['redirect'] ?? 'my-bookings.php';
header("Location: " . ($role === 'admin' ? 'admin/' : 'passenger/') . $redirect . "?msg=" . urlencode($msg) . "&type=$type");
exit;
?>