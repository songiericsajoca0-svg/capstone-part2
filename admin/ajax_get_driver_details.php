<?php
// SIMPLIFIED VERSION - sure na gagana
error_reporting(0);
ini_set('display_errors', 0);

require_once '../includes/config.php';
session_start();

// Function para sa JSON response
function send_json($success, $message, $data = []) {
    $response = ['success' => $success, 'message' => $message];
    foreach ($data as $key => $value) {
        $response[$key] = $value;
    }
    echo json_encode($response);
    exit;
}

// Check login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    send_json(false, 'Unauthorized access');
}

$driver_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($driver_id <= 0) {
    send_json(false, 'Invalid driver ID');
}

// Check database
if (!$conn) {
    send_json(false, 'Database connection failed');
}

// Get admin's TODA
$admin_id = (int)$_SESSION['user_id'];
$toda_result = mysqli_query($conn, "SELECT id, toda_name FROM todas WHERE user_id = $admin_id AND role = 'admin' LIMIT 1");

if (!$toda_result) {
    send_json(false, 'Database error: ' . mysqli_error($conn));
}

$toda = mysqli_fetch_assoc($toda_result);
$toda_id = $toda ? (int)$toda['id'] : 0;
$toda_name = $toda ? $toda['toda_name'] : '';

// Verify driver belongs to TODA (simplified)
if ($toda_id > 0) {
    $verify = mysqli_query($conn, "SELECT 1 FROM toda_drivers WHERE toda_id = $toda_id AND driver_id = $driver_id LIMIT 1");
    if (!$verify || mysqli_num_rows($verify) == 0) {
        send_json(false, 'Driver not found in your TODA');
    }
}

// Get driver details - simplified query without subqueries muna for testing
$query = "SELECT id, name, email, contact, profile, role, created_at, 
          body_number, plate_number, toda_color 
          FROM users 
          WHERE id = $driver_id AND role = 'driver' 
          LIMIT 1";

$result = mysqli_query($conn, $query);
if (!$result) {
    send_json(false, 'Query error: ' . mysqli_error($conn));
}

$driver = mysqli_fetch_assoc($result);
if (!$driver) {
    send_json(false, 'Driver not found');
}

// Get trip counts separately (para iwas error sa subquery)
$trips_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE driver_id = $driver_id AND status = 'COMPLETED'");
$total_trips = $trips_result ? (int)mysqli_fetch_assoc($trips_result)['total'] : 0;

$earnings_result = mysqli_query($conn, "SELECT COALESCE(SUM(fare_amount), 0) as total FROM bookings WHERE driver_id = $driver_id AND status = 'COMPLETED'");
$total_earnings = $earnings_result ? (float)mysqli_fetch_assoc($earnings_result)['total'] : 0;

// Prepare response
send_json(true, 'Success', [
    'driver' => [
        'id' => (int)$driver['id'],
        'name' => $driver['name'],
        'email' => $driver['email'],
        'contact' => $driver['contact'] ?? '',
        'profile' => $driver['profile'] ?? 'default.png',
        'role' => $driver['role'],
        'body_number' => $driver['body_number'] ?? '',
        'plate_number' => $driver['plate_number'] ?? '',
        'toda_color' => $driver['toda_color'] ?? '',
        'created_at' => $driver['created_at'],
        'total_trips' => $total_trips,
        'total_earnings' => $total_earnings
    ],
    'toda_name' => $toda_name
]);
?>