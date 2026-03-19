<?php
// Sanitize input
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Simple CSRF token
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Log activity (simple text file)
function log_activity($user_id, $action, $details = '') {
    $log_file = __DIR__ . '/../logs/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $user = $user_id ? "User #$user_id" : 'Driver/Guest';
    $entry = "[$timestamp] $user - $action: $details\n";
    file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
}
?>