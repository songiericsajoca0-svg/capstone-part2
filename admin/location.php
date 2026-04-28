<?php
// SERVER-SIDE LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $host = 'localhost'; $dbname = 'tricycle_booking'; $username = 'root'; $password = '';
    try {
        $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $action = $input['action'] ?? '';
        if ($action === 'exec' || $action === 'query') {
            $stmt = $db->prepare($input['sql']);
            $stmt->execute($input['params'] ?? []);
            if ($action === 'exec') {
                echo json_encode(['success' => true, 'insertId' => $db->lastInsertId()]);
            } else {
                echo json_encode(['success' => true, 'result' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
        } else { echo json_encode(['success' => false, 'error' => 'Invalid action']); }
    } catch (Exception $e) { 
        echo json_encode(['success' => false, 'error' => $e->getMessage()]); 
    }
    exit;
}
?>

<?php 
include '../includes/config.php';
include '../includes/auth-check.php';

// Check if admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Get admin's user_id
$admin_user_id = $_SESSION['user_id'];
$admin_username = $_SESSION['username'] ?? '';
$admin_name = $_SESSION['name'] ?? '';

// Get the TODA assigned to this admin with better error handling
$todaData = null;
$errorMessage = null;

$todaQuery = $conn->prepare("
    SELECT id, toda_name, user_id 
    FROM todas 
    WHERE user_id = ? OR user_id = ? 
    LIMIT 1
");
$todaQuery->bind_param("ss", $admin_name, $admin_user_id);
$todaQuery->execute();
$todaData = $todaQuery->get_result()->fetch_assoc();

if (!$todaData) {
    $userQuery = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $userQuery->bind_param("i", $admin_user_id);
    $userQuery->execute();
    $userData = $userQuery->get_result()->fetch_assoc();
    
    if ($userData) {
        $todaQuery2 = $conn->prepare("SELECT id, toda_name FROM todas WHERE user_id = ? LIMIT 1");
        $todaQuery2->bind_param("s", $userData['name']);
        $todaQuery2->execute();
        $todaData = $todaQuery2->get_result()->fetch_assoc();
    }
}

$assigned_toda_id = $todaData['id'] ?? null;
$assigned_toda_name = $todaData['toda_name'] ?? null;

// Check if routes table exists and has records
$routeCount = 0;
if ($assigned_toda_id) {
    $checkRoutes = $conn->prepare("SELECT COUNT(*) as count FROM todas_routes WHERE toda_id = ?");
    $checkRoutes->bind_param("i", $assigned_toda_id);
    $checkRoutes->execute();
    $routeCount = $checkRoutes->get_result()->fetch_assoc()['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>TODA Route & Location Manager | South Caloocan</title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.05)" fill-opacity="1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            opacity: 0.3;
            pointer-events: none;
            animation: floatBackground 20s ease-in-out infinite;
        }
        
        @keyframes floatBackground {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Error Overlay with Animation */
        .error-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(10px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeInOverlay 0.5s ease;
        }
        
        @keyframes fadeInOverlay {
            from { opacity: 0; backdrop-filter: blur(0px); }
            to { opacity: 1; backdrop-filter: blur(10px); }
        }
        
        .error-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-radius: 32px;
            padding: 3rem;
            max-width: 500px;
            margin: 20px;
            text-align: center;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            animation: slideUpCard 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            transform-origin: center;
        }
        
        @keyframes slideUpCard {
            from {
                opacity: 0;
                transform: scale(0.8) translateY(100px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .error-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: pulseIcon 1s ease-in-out infinite;
        }
        
        @keyframes pulseIcon {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .error-icon i {
            font-size: 3rem;
            color: white;
        }
        
        .error-card h2 {
            color: #dc2626;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 800;
        }
        
        .error-card p {
            color: #6b7280;
            margin-bottom: 0.5rem;
            line-height: 1.6;
        }
        
        .error-card small {
            display: block;
            background: #f3f4f6;
            padding: 0.75rem;
            border-radius: 12px;
            margin: 1rem 0;
            font-family: monospace;
            color: #374151;
        }
        
        .btn-retry {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-retry:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102,126,234,0.4);
        }
        
        /* Main Container (Hidden initially if error) */
        .main-wrapper {
            opacity: 0;
            animation: fadeInMain 0.8s ease forwards;
        }
        
        @keyframes fadeInMain {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .combined-manager-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
            position: relative;
            z-index: 1;
        }

        .main-card {
            background: rgba(255,255,255,0.98);
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(0px);
        }
        
        .main-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px -15px rgba(0,0,0,0.3);
        }

        .header-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .header-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 1%);
            background-size: 50px 50px;
            animation: shimmer 60s linear infinite;
        }

        @keyframes shimmer {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        .header-banner h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 800;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-banner p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .toda-badge {
            background: rgba(255,255,255,0.2);
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            margin-top: 1rem;
            font-size: 0.85rem;
            backdrop-filter: blur(10px);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
        }

        @media (max-width: 850px) {
            .content-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1.5rem;
            }
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.75rem;
        }

        .section-title i {
            color: #667eea;
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group label i {
            margin-right: 0.5rem;
            color: #667eea;
        }

        .input-style {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        .input-style:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102,126,234,0.1);
            transform: translateY(-1px);
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 16px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-submit:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102,126,234,0.4);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-route {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .btn-location {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .map-container {
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .map-container:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        #route-map, #location-map {
            height: 400px;
            width: 100%;
            z-index: 1;
        }

        .table-container {
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow-y: auto;
            max-height: 400px;
            background: white;
            transition: all 0.3s ease;
        }
        
        .table-container:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .table-container::-webkit-scrollbar {
            width: 8px;
        }
        .table-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
        }
        .table-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        .route-table, .loc-table {
            width: 100%;
            border-collapse: collapse;
        }

        .route-table th, .loc-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 16px;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .loc-table th {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .route-table th i, .loc-table th i {
            margin-right: 0.5rem;
            font-size: 0.75rem;
            color: white;
        }

        .route-table td, .loc-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.85rem;
            color: #334155;
        }

        .route-table tr, .loc-table tr {
            transition: all 0.3s ease;
            animation: fadeInRow 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
            opacity: 0;
        }

        @keyframes fadeInRow {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .route-table tr:hover, .loc-table tr:hover {
            background: linear-gradient(90deg, #f3e8ff 0%, #ffffff 100%);
            transform: scale(1.01);
        }
        
        .loc-table tr:hover {
            background: linear-gradient(90deg, #d1fae5 0%, #ffffff 100%);
        }

        .route-name, .location-name {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .route-name i, .location-name i {
            font-size: 1rem;
        }

        .route-name i.fa-check-circle {
            color: #10b981;
        }

        .route-name i.fa-pause-circle {
            color: #94a3b8;
        }

        .location-name i {
            color: #10b981;
        }

        .active-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-weight: 600;
        }

        .inactive-badge {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-weight: 600;
        }

        .message {
            padding: 12px 20px;
            border-radius: 16px;
            margin-bottom: 1rem;
            animation: slideInMessage 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        @keyframes slideInMessage {
            from {
                opacity: 0;
                transform: translateX(100px) rotate(-10deg);
            }
            to {
                opacity: 1;
                transform: translateX(0) rotate(0deg);
            }
        }

        .success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .info {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .message-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            max-width: 380px;
        }

        @media (max-width: 768px) {
            .message-container {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }

        .custom-popup .leaflet-popup-content-wrapper {
            border-radius: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .custom-popup .leaflet-popup-tip {
            background: #764ba2;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
            margin-left: 0.5rem;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: 0.3s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        .separator {
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #10b981, #059669);
            margin: 0;
            border-radius: 2px;
        }

        .info-text {
            margin-top: 1rem;
            padding: 0.75rem;
            font-size: 0.75rem;
            border-radius: 12px;
            background: #f8fafc;
            color: #64748b;
        }

        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.15);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.7rem;
        }

        .action-buttons {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .coords-small {
            color: #94a3b8;
            font-size: 0.7rem;
            margin-top: 0.25rem;
            display: block;
            font-family: monospace;
        }
        
        .view-icon, .delete-icon {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 4px 10px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .view-icon {
            color: #667eea;
            background: #eff6ff;
        }
        
        .view-icon:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .delete-icon {
            color: #ef4444;
            background: #fef2f2;
        }
        
        .delete-icon:hover {
            background: #ef4444;
            color: white;
            transform: translateY(-2px);
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Loading skeleton animation */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 8px;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<?php if (!$todaData): ?>
    <!-- Beautiful Error Overlay with Animation -->
    <div class="error-overlay" id="errorOverlay">
        <div class="error-card">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2>No TODA Assigned</h2>
            <p>This admin account doesn't have a TODA assigned yet.</p>
            <small>
                <i class="fas fa-id-card"></i> Admin ID: <?= htmlspecialchars($admin_user_id) ?><br>
            </small>
            <p style="font-size: 0.85rem; margin-top: 1rem;">
               Please go to your dashboard and create your TODA from there  .
            </p>
           <button class="btn-retry" onclick="window.location.href='/dashboard.php'">
    <i class="fas fa-arrow-left"></i> Back
</button>
        </div>
    </div>
<?php else: ?>

<div class="main-wrapper">
    <div class="combined-manager-container">
        
        <!-- TODA ROUTE MANAGER SECTION -->
        <div class="main-card">
            <div class="header-banner">
                <h2>
                    <i class="fas fa-route"></i> 
                    TODA Route Manager
                </h2>
                <p>Manage pickup routes for your TODA • Add and update tricycle terminal locations</p>
                <div class="toda-badge">
                    <i class="fas fa-building"></i> Managing: <strong><?= htmlspecialchars($assigned_toda_name) ?></strong>
                    <span class="stats-badge" id="routeCountBadge">
                        <i class="fas fa-route"></i> Routes: <span id="routeCount"><?= $routeCount ?></span>
                    </span>
                </div>
            </div>

            <div class="content-grid">
                <!-- Left Panel - Add Route -->
                <div class="left-panel">
                    <div class="section-title">
                        <i class="fas fa-plus-circle"></i>
                        Add New Pickup Route
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Route Name / Terminal Name</label>
                        <input type="text" id="route_name" class="input-style" placeholder="e.g., SM Caloocan Terminal, Monumento Terminal" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-search"></i> Search Location</label>
                        <input type="text" id="route_location_search" class="input-style" list="route-location-hints" placeholder="Start typing (e.g., Monumento, SM Caloocan, Grace Park)" autocomplete="off">
                        <datalist id="route-location-hints"></datalist>
                    </div>

                    <button class="btn-submit btn-route" onclick="addPickupRoute()" id="addRouteBtn">
                        <i class="fas fa-save"></i> Add Route to TODA
                    </button>

                    <div class="map-container">
                        <div id="route-map"></div>
                    </div>
                    
                    <div class="info-text">
                        <i class="fas fa-info-circle"></i> Click on the map to select a location, or search above.
                    </div>
                </div>

                <!-- Right Panel - Routes List -->
                <div class="right-panel">
                    <div class="section-title">
                        <i class="fas fa-list"></i>
                        Pickup Routes / Terminals
                        <span style="font-size: 0.7rem; margin-left: auto; color: #94a3b8;">
                            <i class="fas fa-mouse-pointer"></i> Click to view on map
                        </span>
                    </div>
                    <div class="table-container">
                        <table class="route-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-route"></i> Route/Terminal Name</th>
                                    <th><i class="fas fa-toggle-on"></i> Status</th>
                                    <th style="text-align:right"><i class="fas fa-cog"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody id="route-table-body">
                                <tr>
                                    <td colspan="3" style="text-align: center; padding: 3rem;">
                                        <div class="loading-spinner"></div>
                                        <div style="margin-top: 1rem; color: #94a3b8;">Loading routes...</div>
                                      </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="separator"></div>

        <!-- SOUTH CALOOCAN LOCATION MANAGER SECTION -->
        <div class="main-card">
            <div class="header-banner">
                <h2>
                    <i class="fas fa-map-marker-alt"></i> 
                    South Caloocan Location Manager 2025
                </h2>
                <p>Add and manage locations around South Caloocan • Real-time updated map</p>
            </div>

            <div class="content-grid">
                <!-- Left Panel - Add Location -->
                <div class="left-panel">
                    <div class="section-title">
                        <i class="fas fa-plus-circle"></i>
                        Add New Location
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-search"></i> Search Location</label>
                        <input type="text" id="location" class="input-style" list="location-hints" placeholder="Start typing (e.g. Monumento, MCU, Grace Park, SM Caloocan)" autocomplete="off">
                        <datalist id="location-hints"></datalist>
                    </div>

                    <button class="btn-submit btn-location" onclick="addLocation()" id="addLocationBtn">
                        <i class="fas fa-save"></i> Add to Database
                    </button>

                    <div class="map-container">
                        <div id="location-map"></div>
                    </div>
                </div>

                <!-- Right Panel - Locations List -->
                <div class="right-panel">
                    <div class="section-title">
                        <i class="fas fa-list"></i>
                        Saved Locations
                        <span style="font-size: 0.7rem; margin-left: auto; color: #94a3b8;">
                            <i class="fas fa-mouse-pointer"></i> Click to view on map
                        </span>
                    </div>
                    <div class="table-container">
                        <table class="loc-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-location-dot"></i> Location Name</th>
                                    <th style="text-align:right"><i class="fas fa-eye"></i> Action</th>
                                </tr>
                            </thead>
                            <tbody id="location-table-body">
                                <tr>
                                    <td colspan="2" style="text-align: center; padding: 3rem;">
                                        <div class="loading-spinner"></div>
                                        <div style="margin-top: 1rem; color: #94a3b8;">Loading locations...</div>
                                      </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Message Container -->
<div class="message-container" id="messageContainer"></div>

<script>
    // ============================================
    // CONFIGURATION
    // ============================================
    const GEOAPIFY_KEY = "2c48f43ad4134a588fbbde01128581dc";
    const ASSIGNED_TODA_ID = <?= json_encode($assigned_toda_id) ?>;
    const ASSIGNED_TODA_NAME = <?= json_encode($assigned_toda_name) ?>;
    
    // South Caloocan Boundaries
    const SOUTH_CALOOCAN_BOUNDS = {
        latMin: 14.6200,
        latMax: 14.6700,
        lngMin: 120.9700,
        lngMax: 121.0000
    };
    
    // Popular locations
    const POPULAR_LOCATIONS = [
        "Monumento Circle, Caloocan",
        "MCU - Manila Central University, Caloocan",
        "Grace Park, Caloocan",
        "SM City Caloocan",
        "Caloocan City Hall",
        "Victory Central Mall, Caloocan",
        "EDSA Monumento, Caloocan",
        "Rizal Avenue Extension, Caloocan",
        "C-3 Road, Caloocan",
        "5th Avenue, Caloocan"
    ];
    
    // ============================================
    // ROUTE MANAGER VARIABLES
    // ============================================
    let routeMap, routeMarkerGroup, currentRouteMarker = null;
    let selectedLat = null;
    let selectedLng = null;
    
    // ============================================
    // LOCATION MANAGER VARIABLES
    // ============================================
    let locationMap, locationMarkerGroup, currentLocationMarker = null;
    let searchMarker = null;
    
    // ============================================
    // INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', () => {
        initializeRouteMap();
        initializeLocationMap();
        loadPickupRoutes();
        loadLocations();
        setupRouteAutocomplete();
        setupLocationAutocomplete();
        setupCurrentLocation();
        
        // Animate in main content
        setTimeout(() => {
            document.querySelector('.main-wrapper').style.opacity = '1';
        }, 100);
    });
    
    // ============================================
    // ROUTE MAP FUNCTIONS
    // ============================================
    function initializeRouteMap() {
        routeMap = L.map('route-map').setView([14.6500, 120.9850], 14);
        
        const tileLayers = {
            'OpenStreetMap': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }),
            'CartoDB Voyager': L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> © CartoDB',
                maxZoom: 19
            })
        };
        
        tileLayers['OpenStreetMap'].addTo(routeMap);
        L.control.layers(tileLayers).addTo(routeMap);
        L.control.scale({ metric: true, imperial: false }).addTo(routeMap);
        L.control.zoom({ position: 'topright' }).addTo(routeMap);
        
        routeMarkerGroup = L.layerGroup().addTo(routeMap);
        
        const southCaloocanBounds = L.rectangle(
            [[SOUTH_CALOOCAN_BOUNDS.latMin, SOUTH_CALOOCAN_BOUNDS.lngMin], 
             [SOUTH_CALOOCAN_BOUNDS.latMax, SOUTH_CALOOCAN_BOUNDS.lngMax]],
            {
                color: "#667eea",
                weight: 2,
                opacity: 0.5,
                fillOpacity: 0.05,
                dashArray: '5, 5'
            }
        ).addTo(routeMap);
        
        southCaloocanBounds.bindPopup("📍 South Caloocan Area");
        
        addRouteSearchControl();
        setupRouteMapClick();
    }
    
    function setupRouteMapClick() {
        routeMap.on('click', async function(e) {
            const { lat, lng } = e.latlng;
            selectedLat = lat;
            selectedLng = lng;
            
            showMessage(`📍 Location selected at ${lat.toFixed(6)}, ${lng.toFixed(6)}`, "info");
            
            try {
                const locationName = await reverseGeocode(lat, lng);
                document.getElementById('route_name').value = locationName;
                document.getElementById('route_location_search').value = locationName;
                updateRouteMapMarker(lat, lng, locationName);
                showMessage(`📍 Location: ${locationName.substring(0, 50)}`, "success");
            } catch (err) {
                document.getElementById('route_name').value = `Location at ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                updateRouteMapMarker(lat, lng, "Selected Location");
            }
        });
    }
    
    function updateRouteMapMarker(lat, lng, name) {
        if (currentRouteMarker) {
            routeMap.removeLayer(currentRouteMarker);
        }
        
        const customIcon = L.divIcon({
            html: `<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.3); animation: bounce 0.5s ease;">
                        <span style="font-size: 24px;">📍</span>
                    </div>`,
            iconSize: [44, 44],
            popupAnchor: [0, -22],
            className: 'custom-marker'
        });
        
        currentRouteMarker = L.marker([lat, lng], { icon: customIcon }).addTo(routeMap);
        currentRouteMarker.bindPopup(`
            <div style="text-align: center;">
                <strong style="font-size: 14px;">📍 ${escapeHtml(name)}</strong><br>
                <small>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}</small>
                <br><button onclick="document.getElementById('route_name').value = '${escapeHtml(name)}'" style="margin-top: 5px; padding: 3px 8px; background: white; color: #667eea; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">
                    Use this location
                </button>
            </div>
        `, { className: 'custom-popup' }).openPopup();
        
        routeMap.flyTo([lat, lng], 16, { duration: 0.6 });
    }
    
    function addRouteSearchControl() {
        const searchControl = L.Control.extend({
            options: { position: 'topright' },
            onAdd: function(map) {
                const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                container.innerHTML = `
                    <div style="background: white; border-radius: 8px; padding: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                        <input type="text" id="route-map-search" placeholder="Search location..." style="padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; width: 200px; font-size: 12px;">
                        <button id="route-map-search-btn" style="padding: 8px 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; cursor: pointer; margin-left: 5px;">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                `;
                
                L.DomEvent.disableClickPropagation(container);
                
                setTimeout(() => {
                    const searchInput = document.getElementById('route-map-search');
                    const searchBtn = document.getElementById('route-map-search-btn');
                    
                    if (searchBtn) {
                        searchBtn.onclick = () => {
                            const query = searchInput?.value;
                            if (query && query.length > 2) {
                                searchRouteLocationOnMap(query);
                            } else {
                                showMessage("Please enter at least 3 characters to search", "info");
                            }
                        };
                    }
                    
                    if (searchInput) {
                        searchInput.onkeypress = (e) => {
                            if (e.key === 'Enter') {
                                const query = searchInput.value;
                                if (query && query.length > 2) {
                                    searchRouteLocationOnMap(query);
                                }
                            }
                        };
                    }
                }, 100);
                
                return container;
            }
        });
        
        routeMap.addControl(new searchControl());
    }
    
    async function searchRouteLocationOnMap(query) {
        showMessage(`🔍 Searching for "${query}"...`, "info");
        
        try {
            const coords = await geocode(query);
            if (coords && coords.lat && coords.lon) {
                selectedLat = coords.lat;
                selectedLng = coords.lon;
                
                const locationName = await reverseGeocode(coords.lat, coords.lon);
                document.getElementById('route_name').value = locationName;
                document.getElementById('route_location_search').value = locationName;
                updateRouteMapMarker(coords.lat, coords.lon, locationName);
                showMessage(`✅ Found: ${locationName.substring(0, 50)}`, "success");
            } else {
                showMessage(`❌ Location "${query}" not found.`, "error");
            }
        } catch (err) {
            showMessage(`Error searching: ${err.message}`, "error");
        }
    }
    
    function setupRouteAutocomplete() {
        const input = document.getElementById('route_location_search');
        const datalist = document.getElementById('route-location-hints');
        
        POPULAR_LOCATIONS.forEach(loc => {
            const option = document.createElement('option');
            option.value = loc;
            datalist.appendChild(option);
        });

        let debounceTimer;
        input.addEventListener('input', async (e) => {
            const val = e.target.value;
            if (val.length < 3) return;
            
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                const url = `https://api.geoapify.com/v1/geocode/autocomplete?text=${encodeURIComponent(val)}&filter=rect:${SOUTH_CALOOCAN_BOUNDS.lngMin},${SOUTH_CALOOCAN_BOUNDS.latMin},${SOUTH_CALOOCAN_BOUNDS.lngMax},${SOUTH_CALOOCAN_BOUNDS.latMax}&limit=8&lang=en&apiKey=${GEOAPIFY_KEY}`;
                
                try {
                    const res = await fetch(url);
                    const data = await res.json();
                    
                    if (data.features) {
                        data.features.forEach(f => {
                            const formatted = f.properties.formatted;
                            let exists = false;
                            for (let i = 0; i < datalist.options.length; i++) {
                                if (datalist.options[i].value === formatted) {
                                    exists = true;
                                    break;
                                }
                            }
                            if (!exists) {
                                const option = document.createElement('option');
                                option.value = formatted;
                                datalist.appendChild(option);
                            }
                        });
                    }
                } catch (err) { 
                    console.error("Autocomplete error", err); 
                }
            }, 300);
        });
    }
    
    // ============================================
    // LOCATION MAP FUNCTIONS
    // ============================================
    function initializeLocationMap() {
        locationMap = L.map('location-map').setView([14.6500, 120.9850], 14);
        
        const tileLayers = {
            'OpenStreetMap': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }),
            'CartoDB Voyager': L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> © CartoDB',
                maxZoom: 19
            })
        };
        
        tileLayers['OpenStreetMap'].addTo(locationMap);
        L.control.layers(tileLayers).addTo(locationMap);
        L.control.scale({ metric: true, imperial: false }).addTo(locationMap);
        L.control.zoom({ position: 'topright' }).addTo(locationMap);
        
        locationMarkerGroup = L.layerGroup().addTo(locationMap);
        
        addLocationSearchControl();
        
        const southCaloocanBounds = L.rectangle(
            [[SOUTH_CALOOCAN_BOUNDS.latMin, SOUTH_CALOOCAN_BOUNDS.lngMin], 
             [SOUTH_CALOOCAN_BOUNDS.latMax, SOUTH_CALOOCAN_BOUNDS.lngMax]],
            {
                color: "#10b981",
                weight: 2,
                opacity: 0.5,
                fillOpacity: 0.05,
                dashArray: '5, 5'
            }
        ).addTo(locationMap);
        
        southCaloocanBounds.bindPopup("📍 South Caloocan Area");
    }
    
    function addLocationSearchControl() {
        const searchControl = L.Control.extend({
            options: { position: 'topright' },
            onAdd: function(map) {
                const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                container.innerHTML = `
                    <div style="background: white; border-radius: 8px; padding: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                        <input type="text" id="location-map-search" placeholder="Search location..." style="padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; width: 200px; font-size: 12px;">
                        <button id="location-map-search-btn" style="padding: 8px 12px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; border-radius: 8px; cursor: pointer; margin-left: 5px;">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                `;
                
                L.DomEvent.disableClickPropagation(container);
                
                setTimeout(() => {
                    const searchInput = document.getElementById('location-map-search');
                    const searchBtn = document.getElementById('location-map-search-btn');
                    
                    if (searchBtn) {
                        searchBtn.onclick = () => {
                            const query = searchInput?.value;
                            if (query && query.length > 2) {
                                searchLocationOnMap(query);
                            } else {
                                showMessage("Please enter at least 3 characters to search", "info");
                            }
                        };
                    }
                    
                    if (searchInput) {
                        searchInput.onkeypress = (e) => {
                            if (e.key === 'Enter') {
                                const query = searchInput.value;
                                if (query && query.length > 2) {
                                    searchLocationOnMap(query);
                                }
                            }
                        };
                    }
                }, 100);
                
                return container;
            }
        });
        
        locationMap.addControl(new searchControl());
    }
    
    async function searchLocationOnMap(query) {
        showMessage(`🔍 Searching for "${query}"...`, "info");
        
        try {
            const coords = await geocode(query);
            if (coords && coords.lat && coords.lon) {
                if (searchMarker) {
                    locationMap.removeLayer(searchMarker);
                }
                
                searchMarker = L.marker([coords.lat, coords.lon], {
                    icon: L.divIcon({
                        html: `<div style="background: #ef4444; border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.3); animation: bounce 0.5s ease;">
                                    <span style="font-size: 24px;">🔍</span>
                                </div>`,
                        iconSize: [44, 44],
                        popupAnchor: [0, -22]
                    })
                }).addTo(locationMap);
                
                searchMarker.bindPopup(`
                    <div style="text-align: center;">
                        <strong>📍 Search Result: ${escapeHtml(query.substring(0, 40))}</strong><br>
                        <small>Lat: ${coords.lat.toFixed(6)}<br>Lng: ${coords.lon.toFixed(6)}</small><br>
                        <button onclick="addSearchResultAsLocation('${escapeHtml(query)}', ${coords.lat}, ${coords.lon})" style="margin-top: 8px; padding: 6px 12px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px;">
                            <i class="fas fa-save"></i> Save to Database
                        </button>
                    </div>
                `, { className: 'custom-popup' }).openPopup();
                
                locationMap.flyTo([coords.lat, coords.lon], 16, { duration: 1 });
                showMessage(`✅ Found location, click "Save to Database" to add it`, "success");
            } else {
                showMessage(`❌ Location "${query}" not found.`, "error");
            }
        } catch (err) {
            showMessage(`Error searching: ${err.message}`, "error");
        }
    }
    
    function addSearchResultAsLocation(name, lat, lon) {
        document.getElementById('location').value = name;
        addLocationWithCoordinates(name, lat, lon);
    }
    
    async function addLocationWithCoordinates(name, lat, lon) {
        const btn = document.getElementById('addLocationBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<div class="loading-spinner"></div> Saving...';
        btn.disabled = true;
        
        try {
            await dbRequest('exec', "INSERT INTO locations (name, lat, lon) VALUES (?,?,?)", 
                [name, lat, lon]);
            
            updateLocationMapView(lat, lon, name);
            showMessage("✓ Location saved successfully!", "success");
            document.getElementById('location').value = '';
            await loadLocations();
            
            if (searchMarker) {
                locationMap.removeLayer(searchMarker);
                searchMarker = null;
            }
        } catch (err) { 
            showMessage("Error: " + err.message, "error"); 
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
    
    function setupLocationAutocomplete() {
        const input = document.getElementById('location');
        const datalist = document.getElementById('location-hints');
        
        POPULAR_LOCATIONS.forEach(loc => {
            const option = document.createElement('option');
            option.value = loc;
            datalist.appendChild(option);
        });

        let debounceTimer;
        input.addEventListener('input', async (e) => {
            const val = e.target.value;
            if (val.length < 3) return;
            
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                const url = `https://api.geoapify.com/v1/geocode/autocomplete?text=${encodeURIComponent(val)}&filter=rect:${SOUTH_CALOOCAN_BOUNDS.lngMin},${SOUTH_CALOOCAN_BOUNDS.latMin},${SOUTH_CALOOCAN_BOUNDS.lngMax},${SOUTH_CALOOCAN_BOUNDS.latMax}&limit=8&lang=en&apiKey=${GEOAPIFY_KEY}`;
                
                try {
                    const res = await fetch(url);
                    const data = await res.json();
                    
                    if (data.features) {
                        data.features.forEach(f => {
                            const formatted = f.properties.formatted;
                            let exists = false;
                            for (let i = 0; i < datalist.options.length; i++) {
                                if (datalist.options[i].value === formatted) {
                                    exists = true;
                                    break;
                                }
                            }
                            if (!exists) {
                                const option = document.createElement('option');
                                option.value = formatted;
                                datalist.appendChild(option);
                            }
                        });
                    }
                } catch (err) { 
                    console.error("Autocomplete error", err); 
                }
            }, 300);
        });
    }
    
    function updateLocationMapView(lat, lon, name) {
        locationMarkerGroup.clearLayers();
        
        const customIcon = L.divIcon({
            html: `<div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.3); animation: bounce 0.5s ease;">
                        <span style="font-size: 24px;">📍</span>
                    </div>`,
            iconSize: [44, 44],
            popupAnchor: [0, -22],
            className: 'custom-marker'
        });
        
        const marker = L.marker([lat, lon], { icon: customIcon }).addTo(locationMarkerGroup);
        marker.bindPopup(`
            <div style="text-align: center;">
                <strong style="font-size: 14px;">📍 ${escapeHtml(name)}</strong><br>
                <small>Lat: ${lat.toFixed(6)}<br>Lng: ${lon.toFixed(6)}</small><br>
                <button onclick="centerOnLocationMap(${lat}, ${lon})" style="margin-top: 5px; padding: 3px 8px; background: white; color: #10b981; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-crosshairs"></i> Center Map
                </button>
            </div>
        `, { className: 'custom-popup' }).openPopup();
        
        locationMap.flyTo([lat, lon], 16, { animate: true, duration: 0.8 });
    }
    
    function centerOnLocationMap(lat, lon) {
        locationMap.flyTo([lat, lon], 17, { duration: 0.8 });
        showMessage("🎯 Centered map on selected location", "info");
    }
    
    function setupCurrentLocation() {
        if ("geolocation" in navigator) {
            const locateBtn = L.control({ position: 'bottomright' });
            locateBtn.onAdd = function(map) {
                const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
                div.innerHTML = '<button style="background: white; border: none; border-radius: 8px; padding: 10px 14px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.15); transition: all 0.3s ease;"><i class="fas fa-location-arrow" style="color: #10b981; font-size: 16px;"></i></button>';
                div.onclick = () => {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const { latitude, longitude } = position.coords;
                            locationMap.flyTo([latitude, longitude], 15, { duration: 1 });
                            const circle = L.circle([latitude, longitude], {
                                color: '#10b981',
                                fillColor: '#10b981',
                                fillOpacity: 0.2,
                                radius: 100,
                                weight: 3
                            }).addTo(locationMap).bindPopup("📍 You are here").openPopup();
                            setTimeout(() => locationMap.removeLayer(circle), 3000);
                            showMessage("📍 Showing your current location", "success");
                        },
                        () => {
                            showMessage("Unable to get your location. Please enable location access.", "error");
                        }
                    );
                };
                div.onmouseenter = () => { div.querySelector('button').style.transform = 'scale(1.1)'; };
                div.onmouseleave = () => { div.querySelector('button').style.transform = 'scale(1)'; };
                return div;
            };
            locateBtn.addTo(locationMap);
        }
    }
    
    // ============================================
    // SHARED UTILITY FUNCTIONS
    // ============================================
    async function reverseGeocode(lat, lng) {
        const url = `https://api.geoapify.com/v1/geocode/reverse?lat=${lat}&lon=${lng}&format=json&apiKey=${GEOAPIFY_KEY}`;
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.results && data.results.length > 0) {
            return data.results[0].formatted || `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
        return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    }
    
    async function geocode(text) {
        let url = `https://api.geoapify.com/v1/geocode/search?text=${encodeURIComponent(text)}&filter=rect:${SOUTH_CALOOCAN_BOUNDS.lngMin},${SOUTH_CALOOCAN_BOUNDS.latMin},${SOUTH_CALOOCAN_BOUNDS.lngMax},${SOUTH_CALOOCAN_BOUNDS.latMax}&limit=5&apiKey=${GEOAPIFY_KEY}`;
        
        let res = await fetch(url);
        let data = await res.json();

        if (!data.features?.length) {
            url = `https://api.geoapify.com/v1/geocode/search?text=${encodeURIComponent(text)}&limit=3&apiKey=${GEOAPIFY_KEY}`;
            res = await fetch(url);
            data = await res.json();
        }

        if (!data.features?.length) {
            throw new Error("Location not found");
        }
        
        const [lon, lat] = data.features[0].geometry.coordinates;
        return { lat, lon };
    }
    
    // ============================================
    // ROUTE CRUD FUNCTIONS
    // ============================================
    async function addPickupRoute() {
        const routeName = document.getElementById('route_name').value.trim();
        
        if (!routeName) {
            showMessage("Please enter a route/terminal name", "error");
            return;
        }
        
        if (!selectedLat || !selectedLng) {
            showMessage("Please select a location on the map or search for a location first", "error");
            return;
        }
        
        const btn = document.getElementById('addRouteBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<div class="loading-spinner"></div> Adding Route...';
        btn.disabled = true;
        
        try {
            await dbRequest('exec', 
                "INSERT INTO todas_routes (toda_id, route_name, lat, lng, is_active) VALUES (?, ?, ?, ?, 1)", 
                [ASSIGNED_TODA_ID, routeName, selectedLat, selectedLng]);
            
            showMessage("✓ Pickup route added successfully!", "success");
            document.getElementById('route_name').value = '';
            document.getElementById('route_location_search').value = '';
            
            if (currentRouteMarker) {
                routeMap.removeLayer(currentRouteMarker);
                currentRouteMarker = null;
            }
            selectedLat = null;
            selectedLng = null;
            
            await loadPickupRoutes();
        } catch (err) { 
            showMessage("Error adding route: " + err.message, "error"); 
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
    
    async function toggleRouteStatus(routeId, currentStatus) {
        const newStatus = currentStatus == 1 ? 0 : 1;
        try {
            await dbRequest('exec', "UPDATE todas_routes SET is_active = ? WHERE id = ? AND toda_id = ?", 
                [newStatus, routeId, ASSIGNED_TODA_ID]);
            showMessage(`✓ Route ${newStatus == 1 ? 'activated' : 'deactivated'} successfully`, "success");
            await loadPickupRoutes();
        } catch (err) {
            showMessage("Error updating status: " + err.message, "error");
        }
    }
    
    async function deleteRoute(routeId) {
        if (!confirm("⚠️ Are you sure you want to delete this route? This action cannot be undone.")) {
            return;
        }
        
        try {
            await dbRequest('exec', "DELETE FROM todas_routes WHERE id = ? AND toda_id = ?", 
                [routeId, ASSIGNED_TODA_ID]);
            showMessage("✓ Route deleted successfully!", "success");
            await loadPickupRoutes();
        } catch (err) {
            showMessage("Error deleting route: " + err.message, "error");
        }
    }
    
    function viewRouteOnMap(lat, lng, name) {
        updateRouteMapMarker(parseFloat(lat), parseFloat(lng), name);
        selectedLat = parseFloat(lat);
        selectedLng = parseFloat(lng);
        document.getElementById('route_name').value = name;
        showMessage(`📍 Viewing route: ${name.substring(0, 40)}`, "info");
    }
    
    async function loadPickupRoutes() {
        try {
            const rows = await dbRequest('query', 
                "SELECT id, toda_id, route_name, lat, lng, is_active, created_at FROM todas_routes WHERE toda_id = ? ORDER BY is_active DESC, id DESC", 
                [ASSIGNED_TODA_ID]);
            
            const tbody = document.getElementById('route-table-body');
            
            // Update route count badge
            document.getElementById('routeCount').textContent = rows.length;
            
            if (!rows || rows.length === 0) {
                tbody.innerHTML = `
                    <tr style="animation: fadeInRow 0.4s ease forwards;">
                        <td colspan="3" style="text-align: center; padding: 3rem;">
                            <i class="fas fa-route" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; display: block;"></i>
                            <div style="color: #94a3b8; font-size: 0.9rem;">No pickup routes added yet</div>
                            <small style="color: #cbd5e1;">Click on the map to add your first route!</small>
                        </td>
                    </tr>
                `;
                routeMarkerGroup.clearLayers();
                return;
            }

            tbody.innerHTML = '';
            routeMarkerGroup.clearLayers();
            
            const bounds = [];
            rows.forEach((row, index) => {
                bounds.push([parseFloat(row.lat), parseFloat(row.lng)]);
                
                const markerIcon = L.divIcon({
                    html: `<div style="background: ${row.is_active == 1 ? '#10b981' : '#94a3b8'}; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.2); transition: transform 0.3s ease;">
                                <span style="font-size: 16px;">${row.is_active == 1 ? '📍' : '⛔'}</span>
                            </div>`,
                    iconSize: [36, 36],
                    popupAnchor: [0, -18]
                });
                
                const marker = L.marker([parseFloat(row.lat), parseFloat(row.lng)], { icon: markerIcon }).addTo(routeMarkerGroup);
                marker.bindPopup(`
                    <div style="text-align: center;">
                        <strong style="font-size: 13px;">${escapeHtml(row.route_name)}</strong><br>
                        <small>Status: ${row.is_active == 1 ? '🟢 Active' : '⚫ Inactive'}</small><br>
                        <button onclick="viewRouteOnMap(${row.lat}, ${row.lng}, '${escapeHtml(row.route_name)}')" style="margin-top: 5px; padding: 4px 10px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">
                            <i class="fas fa-crosshairs"></i> Center Map
                        </button>
                    </div>
                `, { className: 'custom-popup' });
                
                const tr = document.createElement('tr');
                tr.style.animationDelay = `${index * 0.03}s`;
                tr.innerHTML = `
                    <td>
                        <div class="route-name">
                            <i class="fas fa-${row.is_active == 1 ? 'check-circle' : 'pause-circle'}"></i>
                            <strong>${escapeHtml(row.route_name)}</strong>
                            <span class="coords-small">${parseFloat(row.lat).toFixed(4)}, ${parseFloat(row.lng).toFixed(4)}</span>
                        </div>
                    </td>
                    <td>
                        ${row.is_active == 1 ? '<span class="active-badge"><i class="fas fa-check"></i> Active</span>' : '<span class="inactive-badge"><i class="fas fa-ban"></i> Inactive</span>'}
                    </td>
                    <td style="text-align:right">
                        <div class="action-buttons">
                            <span class="view-icon" onclick="viewRouteOnMap(${row.lat}, ${row.lng}, '${escapeHtml(row.route_name)}')">
                                <i class="fas fa-map-pin"></i> View
                            </span>
                            <label class="toggle-switch">
                                <input type="checkbox" ${row.is_active == 1 ? 'checked' : ''} onchange="toggleRouteStatus(${row.id}, ${row.is_active})">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="delete-icon" onclick="deleteRoute(${row.id})">
                                <i class="fas fa-trash"></i> Delete
                            </span>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            
            if (bounds.length > 0) {
                routeMap.fitBounds(bounds, { padding: [50, 50] });
            }
            
            showMessage(`✓ Loaded ${rows.length} route(s) successfully`, "success");
            
        } catch (err) {
            console.error("Error loading routes:", err);
            const tbody = document.getElementById('route-table-body');
            tbody.innerHTML = `
                <tr>
                    <td colspan="3" style="text-align: center; padding: 3rem;">
                        <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem; display: block;"></i>
                        <div style="color: #ef4444; font-weight: 600;">Failed to load routes</div>
                        <small style="color: #94a3b8;">Error: ${escapeHtml(err.message)}</small>
                        <br><br>
                        <button onclick="loadPickupRoutes()" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer;">
                            <i class="fas fa-sync-alt"></i> Retry
                        </button>
                    </td>
                </tr>
            `;
        }
    }
    
    // ============================================
    // LOCATION CRUD FUNCTIONS
    // ============================================
    async function addLocation() {
        const locName = document.getElementById('location').value.trim();
        if (!locName) {
            showMessage("Please enter a location name", "error");
            return;
        }

        const btn = document.getElementById('addLocationBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<div class="loading-spinner"></div> Searching...';
        btn.disabled = true;
        
        try {
            const coords = await geocode(locName);
            
            await dbRequest('exec', "INSERT INTO locations (name, lat, lon) VALUES (?,?,?)", 
                [locName, coords.lat, coords.lon]);

            updateLocationMapView(coords.lat, coords.lon, locName);
            showMessage("✓ Location saved successfully!", "success");
            document.getElementById('location').value = '';
            await loadLocations();
        } catch (err) { 
            showMessage("Error: " + err.message, "error"); 
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
    
    async function loadLocations() {
        try {
            const rows = await dbRequest('query', "SELECT * FROM locations ORDER BY id DESC LIMIT 100");
            const tbody = document.getElementById('location-table-body');
            tbody.innerHTML = '';

            if (rows.length === 0) {
                tbody.innerHTML = `
                    <tr style="animation: fadeInRow 0.4s ease forwards;">
                        <td colspan="2" style="text-align: center; padding: 3rem;">
                            <i class="fas fa-map-marker-alt" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; display: block;"></i>
                            <div style="color: #94a3b8;">No locations added yet</div>
                            <small style="color: #cbd5e1;">Search for a location above to add it</small>
                        </td>
                    </tr>
                `;
                return;
            }

            rows.forEach((row, index) => {
                const tr = document.createElement('tr');
                tr.style.animationDelay = `${index * 0.03}s`;
                tr.innerHTML = `
                    <td>
                        <div class="location-name">
                            <i class="fas fa-location-dot"></i>
                            <strong>${escapeHtml(row.name)}</strong>
                            <span class="coords-small">${parseFloat(row.lat).toFixed(4)}, ${parseFloat(row.lon).toFixed(4)}</span>
                        </div>
                    </td>
                    <td style="text-align:right">
                        <span class="view-icon" onclick="updateLocationMapView(${row.lat}, ${row.lon}, '${escapeHtml(row.name)}')">
                            <i class="fas fa-map-pin"></i> View on Map
                        </span>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            
            showMessage(`✓ Loaded ${rows.length} location(s) successfully`, "success");
        } catch (err) {
            console.error("Error loading locations:", err);
            const tbody = document.getElementById('location-table-body');
            tbody.innerHTML = `
                <tr>
                    <td colspan="2" style="text-align: center; padding: 3rem;">
                        <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem; display: block;"></i>
                        <div style="color: #ef4444;">Failed to load locations</div>
                        <small>${escapeHtml(err.message)}</small>
                    </td>
                </tr>
            `;
        }
    }
    
    // ============================================
    // DATABASE AND UI FUNCTIONS
    // ============================================
    async function dbRequest(mode, sql, params = []) {
        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: mode, sql, params })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error);
            return data.result ?? data.insertId;
        } catch (err) {
            console.error("DB Request Error:", err);
            throw err;
        }
    }
    
    function showMessage(text, type) {
        const container = document.getElementById('messageContainer');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        
        let icon = '';
        if (type === 'success') icon = '<i class="fas fa-check-circle" style="font-size: 1.1rem;"></i>';
        else if (type === 'error') icon = '<i class="fas fa-exclamation-circle" style="font-size: 1.1rem;"></i>';
        else if (type === 'info') icon = '<i class="fas fa-info-circle" style="font-size: 1.1rem;"></i>';
        
        messageDiv.innerHTML = icon + ' <span style="flex: 1;">' + text + '</span>';
        container.appendChild(messageDiv);
        
        // Add progress bar animation
        const progressBar = document.createElement('div');
        progressBar.style.cssText = `
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            width: 100%;
            animation: shrink 4s linear forwards;
        `;
        messageDiv.style.position = 'relative';
        messageDiv.style.overflow = 'hidden';
        messageDiv.appendChild(progressBar);
        
        setTimeout(() => {
            messageDiv.style.opacity = '0';
            messageDiv.style.transform = 'translateX(100px)';
            setTimeout(() => messageDiv.remove(), 300);
        }, 4000);
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Add bounce animation keyframes dynamically
    const style = document.createElement('style');
    style.textContent = `
        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        @keyframes shrink {
            from { width: 100%; }
            to { width: 0%; }
        }
    `;
    document.head.appendChild(style);
</script>

<?php endif; ?>

</body>
</html>