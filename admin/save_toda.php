<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $toda_name = trim($_POST['toda_name'] ?? '');
    $admin_id = $_SESSION['user_id'];
    
    if (empty($toda_name)) {
        echo json_encode(['success' => false, 'message' => 'TODA name is required']);
        exit;
    }
    
    // Check if TODA already exists
    $check = $conn->prepare("SELECT id FROM todas WHERE user_id = ? AND toda_name = ?");
    $check->bind_param("is", $admin_id, $toda_name);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true, 
            'message' => 'TODA already exists', 
            'toda_id' => $row['id'],
            'is_existing' => true
        ]);
    } else {
        $stmt = $conn->prepare("INSERT INTO todas (user_id, role, toda_name) VALUES (?, 'admin', ?)");
        $stmt->bind_param("is", $admin_id, $toda_name);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'TODA created successfully', 
                'toda_id' => $stmt->insert_id,
                'is_existing' => false
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->close();
    }
    $check->close();
    
} elseif ($action === 'add_driver') {
    $toda_id = intval($_POST['toda_id'] ?? 0);
    $driver_id = intval($_POST['driver_id'] ?? 0);
    
    if (!$toda_id || !$driver_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid TODA or Driver ID']);
        exit;
    }
    
    // Get driver name
    $driver_query = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'driver'");
    $driver_query->bind_param("i", $driver_id);
    $driver_query->execute();
    $driver_result = $driver_query->get_result();
    $driver = $driver_result->fetch_assoc();
    
    if (!$driver) {
        echo json_encode(['success' => false, 'message' => 'Driver not found']);
        exit;
    }
    
    $driver_name = $driver['name'];
    
    // Get toda name
    $toda_query = $conn->prepare("SELECT toda_name FROM todas WHERE id = ?");
    $toda_query->bind_param("i", $toda_id);
    $toda_query->execute();
    $toda_result = $toda_query->get_result();
    $toda = $toda_result->fetch_assoc();
    
    if (!$toda) {
        echo json_encode(['success' => false, 'message' => 'TODA not found']);
        exit;
    }
    
    $toda_name = $toda['toda_name'];
    
    // Check if already assigned
    $check_driver = $conn->prepare("SELECT id FROM toda_drivers WHERE toda_id = ? AND driver_id = ?");
    $check_driver->bind_param("ii", $toda_id, $driver_id);
    $check_driver->execute();
    if ($check_driver->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Driver is already assigned to this TODA']);
        $check_driver->close();
        exit;
    }
    $check_driver->close();
    
    // Insert into toda_drivers
    $insert = $conn->prepare("INSERT INTO toda_drivers (toda_id, driver_id, driver_name) VALUES (?, ?, ?)");
    $insert->bind_param("iis", $toda_id, $driver_id, $driver_name);
    
    if (!$insert->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to add driver: ' . $conn->error]);
        $insert->close();
        exit;
    }
    $insert->close();
    
    // Update driver_name column in todas table
    $get_drivers = $conn->prepare("SELECT GROUP_CONCAT(driver_name SEPARATOR ', ') as driver_list FROM toda_drivers WHERE toda_id = ?");
    $get_drivers->bind_param("i", $toda_id);
    $get_drivers->execute();
    $drivers_list_result = $get_drivers->get_result();
    $drivers_row = $drivers_list_result->fetch_assoc();
    $all_drivers = $drivers_row['driver_list'];
    $get_drivers->close();
    
    $update_toda = $conn->prepare("UPDATE todas SET driver_name = ? WHERE id = ?");
    $update_toda->bind_param("si", $all_drivers, $toda_id);
    $update_toda->execute();
    $update_toda->close();
    
    // Update users table
    $update_user = $conn->prepare("UPDATE users SET driver_toda = ? WHERE id = ?");
    $update_user->bind_param("si", $toda_name, $driver_id);
    $update_user->execute();
    $update_user->close();
    
    echo json_encode(['success' => true, 'message' => 'Driver added successfully to ' . $toda_name]);
    
} elseif ($action === 'remove_driver') {
    $toda_id = intval($_POST['toda_id'] ?? 0);
    $driver_id = intval($_POST['driver_id'] ?? 0);
    
    if (!$toda_id || !$driver_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid TODA or Driver ID']);
        exit;
    }
    
    // Get toda name
    $toda_query = $conn->prepare("SELECT toda_name FROM todas WHERE id = ?");
    $toda_query->bind_param("i", $toda_id);
    $toda_query->execute();
    $toda_result = $toda_query->get_result();
    $toda = $toda_result->fetch_assoc();
    $toda_query->close();
    
    // Remove from toda_drivers
    $delete = $conn->prepare("DELETE FROM toda_drivers WHERE toda_id = ? AND driver_id = ?");
    $delete->bind_param("ii", $toda_id, $driver_id);
    $delete->execute();
    $delete->close();
    
    // Update driver_name column in todas table
    $get_drivers = $conn->prepare("SELECT GROUP_CONCAT(driver_name SEPARATOR ', ') as driver_list FROM toda_drivers WHERE toda_id = ?");
    $get_drivers->bind_param("i", $toda_id);
    $get_drivers->execute();
    $drivers_list_result = $get_drivers->get_result();
    $drivers_row = $drivers_list_result->fetch_assoc();
    $all_drivers = $drivers_row['driver_list'] ?? NULL;
    $get_drivers->close();
    
    $update_toda = $conn->prepare("UPDATE todas SET driver_name = ? WHERE id = ?");
    $update_toda->bind_param("si", $all_drivers, $toda_id);
    $update_toda->execute();
    $update_toda->close();
    
    // Check if driver has other TODAs
    $check_other = $conn->prepare("SELECT toda_id FROM toda_drivers WHERE driver_id = ?");
    $check_other->bind_param("i", $driver_id);
    $check_other->execute();
    $other_count = $check_other->get_result()->num_rows;
    $check_other->close();
    
    if ($other_count == 0) {
        $update_user = $conn->prepare("UPDATE users SET driver_toda = NULL WHERE id = ?");
        $update_user->bind_param("i", $driver_id);
        $update_user->execute();
        $update_user->close();
    }
    
    echo json_encode(['success' => true, 'message' => 'Driver removed successfully from ' . $toda['toda_name']]);
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>