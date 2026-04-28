<?php
// Get the requested path
$request_uri = $_SERVER['REQUEST_URI'];
$request_uri = strtok($request_uri, '?'); // Remove query string

// Map of files to try (in order)
$possible_files = [
    // Exact match
    __DIR__ . '/..' . $request_uri,
    // Handle root path
    __DIR__ . '/../index.php',
    __DIR__ . '/../login.php',
];

// If the requested path ends with .php and file exists, serve it directly
if (preg_match('/\.php$/', $request_uri)) {
    $php_file = __DIR__ . '/..' . $request_uri;
    if (file_exists($php_file)) {
        require $php_file;
        exit;
    }
}

// Handle static assets (CSS, JS, images)
$static_file = __DIR__ . '/..' . $request_uri;
if (file_exists($static_file) && !is_dir($static_file)) {
    // Get file extension
    $ext = pathinfo($static_file, PATHINFO_EXTENSION);
    
    // Set correct content type
    $mime_types = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'webp' => 'image/webp',
        'json' => 'application/json',
        'xml' => 'application/xml',
    ];
    
    if (isset($mime_types[$ext])) {
        header('Content-Type: ' . $mime_types[$ext]);
    }
    
    readfile($static_file);
    exit;
}

// Default: try login.php first, then index.php
$default_files = ['login.php', 'index.php', 'index.html'];
foreach ($default_files as $file) {
    $file_path = __DIR__ . '/../' . $file;
    if (file_exists($file_path)) {
        require $file_path;
        exit;
    }
}

// If nothing found, show 404
http_response_code(404);
echo "<h1>404 - Page Not Found</h1>";
echo "<p>The requested page could not be found.</p>";
echo "<p><a href='/login.php'>Go to Login</a></p>";
?>