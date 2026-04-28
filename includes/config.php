<?php
if (session_status() === PHP_SESSION_NONE) {
    // Extend session lifetime to 30 days for Remember Me
    ini_set('session.cookie_lifetime', 86400 * 30); // 30 days
    ini_set('session.gc_maxlifetime', 86400 * 30); // 30 days
    session_start();
}

// ==================== DATABASE CONNECTION ====================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "tricycle_booking";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ==================== TEXTBEE CONFIG ====================
define('TEXTBEE_API_KEY', '4cc00d28-8605-481e-91c7-fd0c564cd8d9');
define('TEXTBEE_DEVICE_ID', '69d4c87cb5cd3ce4c728e9d2');

// ==================== TIMEZONE SETTING ====================
date_default_timezone_set('Asia/Manila');

// ==================== CHECK IF USER TABLES EXIST AND CREATE USER_TOKENS TABLE ====================
$check_table = $conn->query("SHOW TABLES LIKE 'user_tokens'");
if ($check_table->num_rows == 0) {
    $create_table = "CREATE TABLE IF NOT EXISTS `user_tokens` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `token` varchar(255) NOT NULL,
        `expires_at` datetime NOT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `token_unique` (`token`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($create_table);
}

// ==================== FUNCTION: Check if user is logged in (with Remember Me) ====================
function isLoggedIn() {
    global $conn;
    
    // Check if session exists
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    
    // Check for remember token in cookie
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        
        // Verify token in database
        $stmt = $conn->prepare("SELECT user_id, token, expires_at FROM user_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Restore session
            $_SESSION['user_id'] = $row['user_id'];
            
            // Get user data
            $userStmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
            $userStmt->bind_param("i", $row['user_id']);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            $user = $userResult->fetch_assoc();
            
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                return true;
            }
        }
    }
    
    return false;
}

// ==================== SEND SMS FUNCTION (Textbee) ====================
/**
 * Send SMS using Textbee.dev
 */
function sendSMS($phone, $message) {
    $url = "https://api.textbee.dev/api/v1/gateway/devices/" . TEXTBEE_DEVICE_ID . "/send-sms";

    $data = [
        "recipients" => [$phone],
        "message"    => $message
    ];

    $ch = curl_init($url);
    
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . TEXTBEE_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($http_code >= 200 && $http_code < 300);
}
?>