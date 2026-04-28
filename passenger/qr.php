<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$booking_id = (int)$_GET['id'];
$pid = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND passenger_id = ?");
$stmt->bind_param("ii", $booking_id, $pid);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header("Location: my-bookings.php?error=Booking not found");
    exit;
}

$toda_id = $booking['toda_id'] ?? null;
$toda_name = $booking['toda_name'] ?? null;

if (empty($toda_name) && !empty($toda_id)) {
    $toda_stmt = $conn->prepare("SELECT toda_name FROM todas WHERE id = ?");
    $toda_stmt->bind_param("i", $toda_id);
    $toda_stmt->execute();
    $toda_result = $toda_stmt->get_result()->fetch_assoc();
    if ($toda_result) {
        $toda_name = $toda_result['toda_name'];
    }
}

$has_driver = !empty($booking['driver_id']);
$is_completed = ($booking['status'] == 'COMPLETED');
$is_accepted = ($booking['status'] == 'ACCEPTED');
$is_picked_up = ($booking['status'] == 'PASSENGER PICKED UP');
$is_cancelled = ($booking['status'] == 'CANCELLED');

if ($has_driver && $booking['status'] == 'PENDING') {
    $update_stmt = $conn->prepare("UPDATE bookings SET status = 'ACCEPTED' WHERE id = ?");
    $update_stmt->bind_param("i", $booking_id);
    $update_stmt->execute();
    $booking['status'] = 'ACCEPTED';
    $is_accepted = true;
}

$driver_lat = null;
$driver_lng = null;
$driver_location_updated = null;

if ($has_driver && !$is_completed && !$is_cancelled) {
    $driver_stmt = $conn->prepare("SELECT lat, lng, last_location_update FROM users WHERE id = ? AND role = 'driver'");
    $driver_stmt->bind_param("i", $booking['driver_id']);
    $driver_stmt->execute();
    $driver_location_data = $driver_stmt->get_result()->fetch_assoc();
    
    if ($driver_location_data && !empty($driver_location_data['lat']) && !empty($driver_location_data['lng'])) {
        $driver_lat = floatval($driver_location_data['lat']);
        $driver_lng = floatval($driver_location_data['lng']);
        $driver_location_updated = $driver_location_data['last_location_update'];
    }
}

$passenger_lat = null;
$passenger_lng = null;

$passenger_stmt = $conn->prepare("SELECT lat, lng FROM users WHERE id = ? AND role = 'passenger'");
$passenger_stmt->bind_param("i", $pid);
$passenger_stmt->execute();
$passenger_location_data = $passenger_stmt->get_result()->fetch_assoc();

if ($passenger_location_data && !empty($passenger_location_data['lat']) && !empty($passenger_location_data['lng'])) {
    $passenger_lat = floatval($passenger_location_data['lat']);
    $passenger_lng = floatval($passenger_location_data['lng']);
}

$pickup_lat = isset($booking['pickup_lat']) && $booking['pickup_lat'] ? floatval($booking['pickup_lat']) : null;
$pickup_lng = isset($booking['pickup_lng']) && $booking['pickup_lng'] ? floatval($booking['pickup_lng']) : null;
$dropoff_lat = isset($booking['dropoff_lat']) && $booking['dropoff_lat'] ? floatval($booking['dropoff_lat']) : null;
$dropoff_lng = isset($booking['dropoff_lng']) && $booking['dropoff_lng'] ? floatval($booking['dropoff_lng']) : null;

$current_url = $_SERVER['REQUEST_URI'];

// Generate shareable link
$shareable_link = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . "/capstone-part2/passenger/share-booking.php?id=" . $booking_id . "&token=" . md5($booking['booking_code'] . $booking['passenger_id']);
if ($has_driver && !$is_cancelled) {
    $driver_stmt = $conn->prepare("
        SELECT u.name, u.email, u.contact, u.profile, 
               d.vehicle_plate, d.vehicle_color, d.vehicle_type
        FROM users u
        LEFT JOIN drivers d ON u.id = d.user_id
        WHERE u.id = ? AND u.role = 'driver'
    ");
    $driver_stmt->bind_param("i", $booking['driver_id']);
    $driver_stmt->execute();
    $driver_info = $driver_stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Booking Ticket - GoTrike</title>
    
    <?php include '../includes/header.php'; ?>
    
    <style>
        @font-face {
            font-family: 'NaruMonoDemo';
            src: url('../assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        * { font-family: 'NaruMonoDemo', monospace !important; }
        
        body {
            transition: opacity 0.15s ease-in-out;
            opacity: 1;
        }
        
        body.reloading {
            opacity: 0.98;
        }
        
        .refresh-indicator {
            position: fixed;
            bottom: 15px;
            left: 15px;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            color: rgba(255,255,255,0.7);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            z-index: 9999;
            pointer-events: none;
        }
        
        .refresh-indicator.blink {
            background: rgba(72,187,120,0.8);
            color: white;
            transform: scale(1.05);
        }
        
        #map {
            width: 100%;
            height: 300px;
            background: #e2e8f0;
            min-height: 250px;
            z-index: 1;
        }
        
        @media (min-width: 768px) {
            #map { height: 400px; }
        }
        
        .map-saved-indicator {
            position: fixed;
            bottom: 15px;
            right: 15px;
            background: rgba(72,187,120,0.9);
            backdrop-filter: blur(8px);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            z-index: 9999;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .map-saved-indicator.show { opacity: 1; }
        
        .ticket-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 1rem;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        
        .two-column-layout {
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (min-width: 768px) {
            .ticket-page { padding: 2rem; }
            .two-column-layout { grid-template-columns: 1fr 1fr; gap: 2rem; }
        }
        
        .ticket-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.15);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .ticket-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.25rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        @media (min-width: 768px) { .ticket-header { padding: 1.5rem; } }
        
        .ticket-header::before {
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
        
        .ticket-header h1 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            position: relative;
            z-index: 1;
        }
        
        @media (min-width: 768px) { .ticket-header h1 { font-size: 1.5rem; } }
        
        .ticket-header .ref {
            margin-top: 0.5rem;
            font-size: 0.75rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        
        .status-message {
            padding: 0.875rem 1rem;
            margin: 0;
            text-align: center;
            font-weight: bold;
            font-size: 0.85rem;
        }
        
        .status-message-accepted {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border-bottom: 2px solid #1e40af;
        }
        
        .status-message-picked_up {
            background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
            color: #9a3412;
            border-bottom: 2px solid #9a3412;
        }
        
        .status-message-cancelled {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border-bottom: 2px solid #991b1b;
        }
        
        .driver-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.15);
            border: 1px solid rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            height: fit-content;
        }
        
        .driver-header {
            background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
            color: white;
            padding: 1rem;
            text-align: center;
        }
        
        .driver-header h2 {
            margin: 0;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        @media (min-width: 768px) { .driver-header h2 { font-size: 1.3rem; } }
        
        .location-info {
            padding: 0.75rem;
            background: #f7fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .live-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #48bb78;
            font-weight: bold;
            font-size: 0.7rem;
        }
        
        .live-dot {
            width: 8px;
            height: 8px;
            background: #48bb78;
            border-radius: 50%;
            animation: blink 1.5s infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        .waiting-screen {
            padding: 1.5rem;
            text-align: center;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            margin: 0 auto 1rem;
            border: 4px solid #e2e8f0;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin: 1rem 0;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 0%;
            animation: loading 3s infinite;
            border-radius: 2px;
        }
        
        @keyframes loading {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 100%; }
        }
        
        .driver-info-screen { padding: 1rem; }
        
        .driver-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
            flex-wrap: wrap;
        }
        
        .driver-avatar {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }
        
        .driver-name {
            font-size: 1rem;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }
        
        .driver-contact {
            margin-top: 0.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #e0e7ff;
            padding: 0.25rem 0.7rem;
            border-radius: 50px;
            font-size: 0.7rem;
            color: #4338ca;
            text-decoration: none;
        }
        
        .vehicle-info {
            background: #f7fafc;
            padding: 0.75rem;
            border-radius: 12px;
            margin-bottom: 0.75rem;
        }
        
        .vehicle-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .details-section { padding: 1rem; }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 0.6rem 0.8rem;
            align-items: baseline;
        }
        
        .info-label {
            color: #718096;
            font-size: 0.7rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .info-value {
            font-weight: 600;
            color: #2d3748;
            font-size: 0.8rem;
            text-align: right;
        }
        
        .fare-highlight {
            color: #667eea;
            font-size: 0.95rem;
            font-weight: bold;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #92400e; }
        .status-accepted { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #1e40af; }
        .status-passenger_picked_up { background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%); color: #9a3412; }
        .status-completed { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; }
        .status-cancelled { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b; }
        
        .payment-method-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: bold;
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            color: #4338ca;
        }
        
        .divider {
            grid-column: 1 / -1;
            height: 1px;
            background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
            margin: 0.2rem 0;
        }
        
        .action-area {
            padding: 1rem;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            text-align: center;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .btn-print, .btn-back, .btn-refresh, .btn-center, .btn-cancel-booking, .btn-share, .btn-copy-text {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 12px;
            font-weight: bold;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-back, .btn-refresh { background: #2d3748; color: white; }
        .btn-refresh { background: #48bb78; }
        .btn-center { background: #667eea; color: white; font-size: 0.7rem; padding: 0.25rem 0.7rem; }
        .btn-cancel-booking { background: #ef4444; color: white; }
        .btn-cancel-booking:hover { background: #dc2626; transform: translateY(-2px); }
        .btn-share { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
        .btn-copy-text { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        
        .toda-info-card {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 0.75rem;
            border-radius: 12px;
            margin: 1rem 1rem 0 1rem;
            border-left: 4px solid #f59e0b;
        }
        
        .toda-info-card h4 {
            margin: 0 0 0.3rem 0;
            color: #92400e;
            font-size: 0.85rem;
        }
        
        .gps-accuracy {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-left: 5px;
        }
        
        .gps-high { background: #48bb78; box-shadow: 0 0 5px #48bb78; }
        .gps-medium { background: #fbbf24; box-shadow: 0 0 5px #fbbf24; }
        .gps-low { background: #ef4444; box-shadow: 0 0 5px #ef4444; }
        
        @media print {
            .driver-card, .btn-refresh, .btn-back, .btn-cancel-booking, .refresh-indicator, .map-saved-indicator, .btn-share, .btn-copy-text { display: none !important; }
            .two-column-layout { grid-template-columns: 1fr; }
        }
        
        .leaflet-control-zoom {
            bottom: 20px;
            top: auto !important;
            left: 20px;
        }
        
        .route-loading {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 11px;
            z-index: 1000;
        }
        
        /* Share Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 24px;
            max-width: 500px;
            width: 90%;
            padding: 1.5rem;
            animation: slideUp 0.3s ease;
        }
        
        .modal-header {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #1f2937;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-close {
            cursor: pointer;
            font-size: 1.5rem;
            color: #9ca3af;
            background: none;
            border: none;
        }
        
        .share-link-container {
            background: #f3f4f6;
            padding: 0.75rem;
            border-radius: 12px;
            margin: 1rem 0;
            word-break: break-all;
            font-size: 0.75rem;
            font-family: monospace;
        }
        
        .copy-success {
            background: #10b981;
            color: white;
            padding: 0.5rem;
            border-radius: 8px;
            text-align: center;
            margin-top: 0.5rem;
            font-size: 0.75rem;
        }
        
        .booking-text-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            margin: 1rem 0;
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.7rem;
            white-space: pre-wrap;
        }
        
        .copy-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 0.5rem;
        }
    </style>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="ticket-page">
    <div class="two-column-layout">
        <!-- LEFT CARD -->
        <div class="ticket-card">
            <div class="ticket-header">
                <h1>🎫 BOOKING TICKET</h1>
                <div class="ref">Reference: #<?= htmlspecialchars($booking['booking_code']) ?></div>
            </div>

            <div class="toda-info-card">
                <h4>🚖 <?= htmlspecialchars($toda_name ?: 'Loading...') ?></h4>
                <p>🏢 TODA Group | 🆔 ID: <?= htmlspecialchars($toda_id ?: '—') ?></p>
            </div>

            <div id="statusMessageContainer">
                <?php if ($is_accepted && !$is_completed && !$is_picked_up && !$is_cancelled): ?>
                <div class="status-message status-message-accepted">
                    🚗 "Your driver has accepted your booking and is on the way to your pickup location!"
                </div>
                <?php elseif ($is_picked_up && !$is_completed && !$is_cancelled): ?>
                <div class="status-message status-message-picked_up">
                    🚐 "Pickup completed! Your driver is now heading to your dropoff location."
                </div>
                <?php elseif ($is_cancelled): ?>
                <div class="status-message status-message-cancelled">
                    ❌ "This booking has been cancelled."
                </div>
                <?php endif; ?>
            </div>

            <div class="details-section">
                <div class="info-grid">
                    <div class="info-label">STATUS</div>
                    <div class="info-value">
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '_', $booking['status'] ?? 'pending')) ?>">
                            <?= htmlspecialchars($booking['status'] ?? 'PENDING') ?>
                        </span>
                    </div>
                    <div class="divider"></div>
                    <div class="info-label">💳 PAYMENT</div>
                    <div class="info-value">
                        <?php 
                        $payment_method = $booking['payment_method'] ?? 'cash';
                        $method_display = '';
                        switch(strtolower($payment_method)) {
                            case 'cash': $method_display = '💵 Cash'; break;
                            case 'card': $method_display = '💳 Card'; break;
                            case 'gcash': $method_display = '📱 GCash'; break;
                            case 'maya': $method_display = '📱 Maya'; break;
                            default: $method_display = '💵 ' . ucfirst($payment_method);
                        }
                        ?>
                        <span class="payment-method-badge"><?= htmlspecialchars($method_display) ?></span>
                    </div>
                    <div class="info-label">📍 PICKUP</div>
                    <div class="info-value"><?= htmlspecialchars($booking['pickup_landmark'] ?: '—') ?></div>
                    <div class="info-label">📍 DROP-OFF</div>
                    <div class="info-value"><?= htmlspecialchars($booking['dropoff_landmark'] ?: '—') ?></div>
                    <div class="divider"></div>
                    <div class="info-label">👥 PASSENGERS</div>
                    <div class="info-value"><?= htmlspecialchars($booking['total_pax'] ?? '1') ?> person(s)</div>
                    <div class="info-label">🚲 TRICYCLES</div>
                    <div class="info-value"><?= htmlspecialchars($booking['trike_units'] ?? '1') ?> unit(s)</div>
                    <div class="info-label">📏 DISTANCE</div>
                    <div class="info-value"><?= htmlspecialchars($booking['distance'] ?? '0') ?> km</div>
                    <div class="info-label">💰 FARE</div>
                    <div class="info-value fare-highlight">₱ <?= number_format($booking['fare_amount'] ?? $booking['fare'] ?? 0, 2) ?></div>
                    <div class="divider"></div>
                    <div class="info-label">📝 NOTES</div>
                    <div class="info-value notes-text">"<?= htmlspecialchars($booking['notes'] ?: 'No special instructions') ?>"</div>
                </div>
            </div>

            <div class="action-area">
                <a href="my-bookings.php" class="btn-back">← Back</a>
                <button onclick="openShareModal()" class="btn-share">📤 Share Trip</button>
                <?php if (!$is_completed && !$is_cancelled): ?>
                <button onclick="manualRefresh()" class="btn-refresh">⟳ Refresh Now</button>
                <button onclick="cancelBooking(<?= $booking_id ?>)" class="btn-cancel-booking">✖ Cancel Booking</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT CARD -->
        <div class="driver-card">
            <div class="driver-header">
                <h2>
                    <span>🛺</span>
                    <?php if ($is_completed): ?>Trip Completed! 🎉
                    <?php elseif ($is_cancelled): ?>Booking Cancelled ❌
                    <?php elseif ($is_picked_up): ?>On Your Way! 🚐
                    <?php elseif ($is_accepted): ?>Driver Assigned! 🛺
                    <?php elseif ($has_driver): ?>Your Driver is Here!
                    <?php else: ?>Finding Your Driver...<?php endif; ?>
                </h2>
            </div>

            <div id="map"></div>
            
            <div id="locationInfoContainer">
                <?php if ($has_driver && !$is_completed && !$is_cancelled && $driver_lat && $driver_lng): ?>
                <div class="location-info">
                    <div class="live-badge"><span class="live-dot"></span>LIVE TRACKING</div>
                    <div class="last-updated">Next refresh: <span id="refreshCountdown">4</span>s</div>
                    <button onclick="centerOnDriver()" class="btn-center">🎯 Center on Driver</button>
                    <button onclick="centerOnPassenger()" class="btn-center" style="background:#f59e0b;">📍 Center on Me</button>
                    <button onclick="saveMapView()" class="btn-center" style="background:#48bb78;">💾 Save View</button>
                </div>
                <?php elseif ($is_completed): ?>
                <div class="location-info">
                    <div class="live-badge" style="color: #065f46;"><span>✅</span>TRIP COMPLETED</div>
                    <div class="last-updated">Thank you for riding with GoTrike!</div>
                </div>
                <?php elseif ($is_cancelled): ?>
                <div class="location-info">
                    <div class="live-badge" style="color: #991b1b;"><span>❌</span>BOOKING CANCELLED</div>
                </div>
                <?php endif; ?>
            </div>

            <div id="driverInfoContainer">
                <?php if ($has_driver && !$is_cancelled && $driver_info): ?>
                    <div class="driver-info-screen">
                        <div class="driver-profile">
                            <div class="driver-avatar">
                                <?php if (!empty($driver_info['profile']) && file_exists($driver_info['profile'])): ?>
                                    <img src="<?= htmlspecialchars($driver_info['profile']) ?>" style="width: 55px; height: 55px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>👨‍✈️<?php endif; ?>
                            </div>
                            <div class="driver-details">
                                <div class="driver-name"><?= htmlspecialchars($driver_info['name'] ?? 'Driver') ?></div>
                                <?php if (!empty($driver_info['contact'])): ?>
                                    <a href="tel:<?= htmlspecialchars($driver_info['contact']) ?>" class="driver-contact">📞 <?= htmlspecialchars($driver_info['contact']) ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="vehicle-info">
                            <div class="vehicle-row"><span>Type:</span><span><?= htmlspecialchars($driver_info['vehicle_type'] ?? 'Tricycle') ?></span></div>
                            <div class="vehicle-row"><span>Email:</span><span><?= htmlspecialchars($driver_info['email'] ?? 'Not available') ?></span></div>
                        </div>
                    </div>
                <?php elseif (!$is_completed && !$is_cancelled): ?>
                    <div class="waiting-screen">
                        <div class="spinner"></div>
                        <h3>Looking for drivers nearby...</h3>
                        <p>We're finding the best driver for your trip</p>
                        <div class="progress-bar"><div class="progress-fill"></div></div>
                        <p style="font-size: 0.75rem; margin-top: 1rem;">⏱️ Auto-refresh every 4 seconds</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div id="shareModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>📤 Share Trip Details</span>
            <button class="modal-close" onclick="closeShareModal()">&times;</button>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <button onclick="copyShareLink()" class="btn-share" style="width: 100%; margin-bottom: 0.5rem;">🔗 Copy Shareable Link</button>
            <button onclick="copyBookingText()" class="btn-copy-text" style="width: 100%;">📋 Copy Trip Details as Text</button>
        </div>
        
        <div class="share-link-container" id="shareLinkContainer">
            <?= htmlspecialchars($shareable_link) ?>
        </div>
        
        <div id="bookingTextContainer" class="booking-text-box" style="display: none;">
            <!-- Booking text will appear here -->
        </div>
        
        <div id="copyMessage" style="text-align: center; font-size: 0.7rem; color: #10b981; display: none;">
            ✓ Copied to clipboard!
        </div>
    </div>
</div>

<div id="refreshIndicator" class="refresh-indicator">🔄 Auto-refresh in <span id="countdown">4</span>s</div>
<div id="mapSavedIndicator" class="map-saved-indicator">💾 Map view saved!</div>

<script>
// Share Modal Functions
function openShareModal() {
    document.getElementById('shareModal').classList.add('active');
}

function closeShareModal() {
    document.getElementById('shareModal').classList.remove('active');
    document.getElementById('copyMessage').style.display = 'none';
    document.getElementById('bookingTextContainer').style.display = 'none';
}

async function copyShareLink() {
    const link = '<?= addslashes($shareable_link) ?>';
    try {
        await navigator.clipboard.writeText(link);
        showCopyMessage('Link copied to clipboard!');
    } catch (err) {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = link;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showCopyMessage('Link copied to clipboard!');
    }
}

function getBookingText() {
    const bookingCode = '<?= addslashes($booking['booking_code']) ?>';
    const status = '<?= addslashes($booking['status'] ?? 'PENDING') ?>';
    const pickup = '<?= addslashes($booking['pickup_landmark'] ?: '—') ?>';
    const dropoff = '<?= addslashes($booking['dropoff_landmark'] ?: '—') ?>';
    const pax = '<?= addslashes($booking['total_pax'] ?? '1') ?>';
    const trikes = '<?= addslashes($booking['trike_units'] ?? '1') ?>';
    const fare = '₱ <?= number_format($booking['fare_amount'] ?? $booking['fare'] ?? 0, 2) ?>';
    const payment = '<?= addslashes($method_display) ?>';
    const notes = '<?= addslashes($booking['notes'] ?: 'No special instructions') ?>';
    const toda = '<?= addslashes($toda_name ?: 'Loading...') ?>';
    const driverName = '<?= addslashes($driver_info['name'] ?? 'Not yet assigned') ?>';
    const driverContact = '<?= addslashes($driver_info['contact'] ?? 'N/A') ?>';
    const vehicleType = '<?= addslashes($driver_info['vehicle_type'] ?? 'Tricycle') ?>';
    const vehiclePlate = '<?= addslashes($driver_info['vehicle_plate'] ?? 'N/A') ?>';
    
    let text = `=====================================\n`;
    text += `         🎫 GOTRIKE BOOKING TICKET\n`;
    text += `=====================================\n\n`;
    text += `📌 BOOKING CODE: ${bookingCode}\n`;
    text += `📌 STATUS: ${status}\n`;
    text += `📌 TODA: ${toda}\n\n`;
    text += `📍 PICKUP LOCATION:\n   ${pickup}\n\n`;
    text += `📍 DROP-OFF LOCATION:\n   ${dropoff}\n\n`;
    text += `👥 PASSENGERS: ${pax} person(s)\n`;
    text += `🚲 TRICYCLES: ${trikes} unit(s)\n`;
    text += `💰 FARE: ${fare}\n`;
    text += `💳 PAYMENT: ${payment}\n`;
    text += `📝 NOTES: ${notes}\n\n`;
    
    if (driverName !== 'Not yet assigned') {
        text += `=====================================\n`;
        text += `            DRIVER INFORMATION\n`;
        text += `=====================================\n`;
        text += `👨‍✈️ DRIVER: ${driverName}\n`;
        text += `📞 CONTACT: ${driverContact}\n`;
        text += `🚲 VEHICLE: ${vehicleType}\n`;
        text += `🔢 PLATE #: ${vehiclePlate}\n\n`;
    }
    
    text += `=====================================\n`;
    text += `🔗 TRACKING LINK: ${'<?= addslashes($shareable_link) ?>'}\n`;
    text += `=====================================\n`;
    text += `📅 Generated: ${new Date().toLocaleString()}\n`;
    text += `🚀 GoTrike - Safe & Reliable Rides!\n`;
    
    return text;
}

async function copyBookingText() {
    const bookingText = getBookingText();
    const textContainer = document.getElementById('bookingTextContainer');
    
    // Show the text in the modal
    textContainer.style.display = 'block';
    textContainer.textContent = bookingText;
    
    try {
        await navigator.clipboard.writeText(bookingText);
        showCopyMessage('Trip details copied to clipboard!');
        
        // Auto-hide the text container after 3 seconds
        setTimeout(() => {
            textContainer.style.display = 'none';
        }, 3000);
    } catch (err) {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = bookingText;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showCopyMessage('Trip details copied to clipboard!');
        
        setTimeout(() => {
            textContainer.style.display = 'none';
        }, 3000);
    }
}

function showCopyMessage(message) {
    const msgDiv = document.getElementById('copyMessage');
    msgDiv.textContent = message;
    msgDiv.style.display = 'block';
    setTimeout(() => {
        msgDiv.style.display = 'none';
    }, 2000);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('shareModal');
    if (event.target === modal) {
        closeShareModal();
    }
}

// ============================================
// REAL-TIME PASSENGER GPS TRACKING
// ============================================

let map, driverMarker, pickupMarker, dropoffMarker, passengerMarker, passengerAccuracyCircle;
let countdownInterval;
let refreshCountdown = 4;
let isMapReady = false;
let routeLine = null;
let watchPositionId = null;
let lastPassengerUpdate = 0;
let passengerUpdateInterval = null;

const MAP_STORAGE_KEY = 'gotrike_map_view_<?= $booking_id ?>';
const bookingId = <?= $booking_id ?>;
const isAccepted = <?= $is_accepted ? 'true' : 'false' ?>;
const isPickedUp = <?= $is_picked_up ? 'true' : 'false' ?>;
const shouldTrackPassenger = (isAccepted || isPickedUp);

// Icons
const driverIcon = L.divIcon({
    html: '<div style="background: #3b82f6; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><span style="font-size: 22px;">🛺</span><div style="position: absolute; bottom: -22px; white-space: nowrap; background: #3b82f6; color: white; padding: 2px 6px; border-radius: 12px; font-size: 9px; font-weight: bold;">DRIVER</div></div>',
    iconSize: [40, 40], popupAnchor: [0, -22]
});

const passengerIcon = L.divIcon({
    html: '<div style="background: #f59e0b; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><span style="font-size: 16px;">🧑</span><div style="position: absolute; bottom: -20px; white-space: nowrap; background: #f59e0b; color: white; padding: 2px 6px; border-radius: 12px; font-size: 9px; font-weight: bold;">YOU</div></div>',
    iconSize: [32, 32], popupAnchor: [0, -18]
});

const pickupIcon = L.divIcon({
    html: '<div style="background: #48bb78; border-radius: 50%; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><span style="font-size: 18px;">📍</span><div style="position: absolute; bottom: -20px; white-space: nowrap; background: #48bb78; color: white; padding: 2px 6px; border-radius: 12px; font-size: 9px; font-weight: bold;">PICKUP</div></div>',
    iconSize: [34, 34], popupAnchor: [0, -19]
});

const dropoffIcon = L.divIcon({
    html: '<div style="background: #f56565; border-radius: 50%; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><span style="font-size: 18px;">🏁</span><div style="position: absolute; bottom: -20px; white-space: nowrap; background: #f56565; color: white; padding: 2px 6px; border-radius: 12px; font-size: 9px; font-weight: bold;">DROP OFF</div></div>',
    iconSize: [34, 34], popupAnchor: [0, -19]
});

const currentData = {
    bookingId: <?= $booking_id ?>,
    hasDriver: <?= ($has_driver && $driver_lat !== null && $driver_lng !== null) ? 'true' : 'false' ?>,
    isCompleted: <?= $is_completed ? 'true' : 'false' ?>,
    isCancelled: <?= $is_cancelled ? 'true' : 'false' ?>,
    isPickedUp: <?= $is_picked_up ? 'true' : 'false' ?>,
    isAccepted: <?= $is_accepted ? 'true' : 'false' ?>,
    driverLat: <?= $driver_lat !== null ? $driver_lat : 'null' ?>,
    driverLng: <?= $driver_lng !== null ? $driver_lng : 'null' ?>,
    passengerLat: <?= $passenger_lat !== null ? $passenger_lat : 'null' ?>,
    passengerLng: <?= $passenger_lng !== null ? $passenger_lng : 'null' ?>,
    pickupLat: <?= $pickup_lat !== null ? $pickup_lat : 'null' ?>,
    pickupLng: <?= $pickup_lng !== null ? $pickup_lng : 'null' ?>,
    dropoffLat: <?= $dropoff_lat !== null ? $dropoff_lat : 'null' ?>,
    dropoffLng: <?= $dropoff_lng !== null ? $dropoff_lng : 'null' ?>
};

async function fetchRouteFromPHP(startLat, startLng, endLat, endLng) {
    const url = `get-route.php?start_lat=${startLat}&start_lng=${startLng}&end_lat=${endLat}&end_lng=${endLng}`;
    try {
        const response = await fetch(url);
        const data = await response.json();
        if (data.success && data.coordinates && data.coordinates.length > 0) {
            return data;
        } else {
            return { success: false, error: data.error };
        }
    } catch (error) {
        return { success: false, error: error.message };
    }
}

async function drawAccurateRoute(startLat, startLng, endLat, endLng, color = '#3b82f6', isPickedUp = false) {
    const mapDiv = document.getElementById('map');
    let loadingDiv = document.querySelector('.route-loading');
    if (!loadingDiv && mapDiv) {
        loadingDiv = document.createElement('div');
        loadingDiv.className = 'route-loading';
        loadingDiv.innerHTML = '🛺 Finding tricycle route...';
        mapDiv.appendChild(loadingDiv);
    }
    
    const finalColor = isPickedUp ? '#ef4444' : color;
    const result = await fetchRouteFromPHP(startLat, startLng, endLat, endLng);
    
    if (loadingDiv) loadingDiv.remove();
    
    if (result.success && result.coordinates && result.coordinates.length > 0) {
        if (routeLine) map.removeLayer(routeLine);
        routeLine = L.polyline(result.coordinates, {
            color: finalColor,
            weight: 5,
            opacity: 0.9,
            lineCap: 'round',
            lineJoin: 'round'
        }).addTo(map);
        
        const distanceKm = (result.distance / 1000).toFixed(1);
        const timeMin = Math.round(result.time / 60);
        routeLine.bindPopup(`🛺 Tricycle Route: ${distanceKm} km | ${timeMin} mins`);
        return true;
    } else {
        if (routeLine) map.removeLayer(routeLine);
        routeLine = L.polyline([[startLat, startLng], [endLat, endLng]], {
            color: finalColor,
            weight: 3,
            opacity: 0.5,
            dashArray: '5, 10'
        }).addTo(map);
        routeLine.bindPopup('⚠️ Using estimated route (offline mode)');
        return false;
    }
}

async function updateRouteForPassenger() {
    if (!currentData.hasDriver || !currentData.driverLat) return;
    
    let destinationLat, destinationLng;
    
    if (currentData.isPickedUp && currentData.dropoffLat) {
        destinationLat = currentData.dropoffLat;
        destinationLng = currentData.dropoffLng;
        if (currentData.passengerLat) {
            await drawAccurateRoute(currentData.passengerLat, currentData.passengerLng, destinationLat, destinationLng, '#ef4444', true);
        }
    } else if (currentData.pickupLat) {
        destinationLat = currentData.pickupLat;
        destinationLng = currentData.pickupLng;
        await drawAccurateRoute(currentData.driverLat, currentData.driverLng, destinationLat, destinationLng, '#3b82f6', false);
    }
}

function saveMapView() {
    if (!map || !isMapReady) return;
    const center = map.getCenter();
    const zoom = map.getZoom();
    const viewData = { 
        lat: center.lat, 
        lng: center.lng, 
        zoom: zoom, 
        timestamp: Date.now(),
        bookingId: currentData.bookingId
    };
    localStorage.setItem(MAP_STORAGE_KEY, JSON.stringify(viewData));
    
    const indicator = document.getElementById('mapSavedIndicator');
    if (indicator) {
        indicator.classList.add('show');
        setTimeout(() => indicator.classList.remove('show'), 1500);
    }
}

function loadSavedMapView() {
    if (!map) return false;
    const saved = localStorage.getItem(MAP_STORAGE_KEY);
    if (saved) {
        try {
            const view = JSON.parse(saved);
            if (view.bookingId === currentData.bookingId && (Date.now() - view.timestamp) < 3600000 && view.lat && view.lng && view.zoom) {
                map.setView([view.lat, view.lng], view.zoom);
                return true;
            }
        } catch(e) {}
    }
    return false;
}

function centerOnDriver() {
    if (currentData.isCompleted || currentData.isCancelled) return;
    if (driverMarker) {
        const ll = driverMarker.getLatLng();
        map.flyTo([ll.lat, ll.lng], 16);
        setTimeout(() => saveMapView(), 500);
    }
}

function centerOnPassenger() {
    if (passengerMarker) {
        const ll = passengerMarker.getLatLng();
        map.flyTo([ll.lat, ll.lng], 16);
        setTimeout(() => saveMapView(), 500);
    } else if (currentData.passengerLat) {
        map.flyTo([currentData.passengerLat, currentData.passengerLng], 16);
    }
}

function manualRefresh() {
    if (map && isMapReady) saveMapView();
    performSmoothReload();
}

function updateCountdownDisplay() {
    const cd = document.getElementById('countdown');
    const refreshCd = document.getElementById('refreshCountdown');
    if (cd) cd.textContent = refreshCountdown;
    if (refreshCd) refreshCd.textContent = refreshCountdown;
    
    const indicator = document.getElementById('refreshIndicator');
    if (refreshCountdown <= 2 && indicator) indicator.classList.add('blink');
    else if (indicator) indicator.classList.remove('blink');
}

function startCountdown() {
    if (countdownInterval) clearInterval(countdownInterval);
    countdownInterval = setInterval(() => {
        if (refreshCountdown > 0) {
            refreshCountdown--;
            updateCountdownDisplay();
            
            if (refreshCountdown === 0 && !currentData.isCompleted && !currentData.isCancelled) {
                performSmoothReload();
            }
        }
    }, 1000);
}

async function sendPassengerLocation(lat, lng, accuracy = null) {
    try {
        const response = await fetch('update-passenger-location.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                lat: lat, 
                lng: lng, 
                booking_id: currentData.bookingId,
                accuracy: accuracy
            })
        });
        const data = await response.json();
        
        if (passengerMarker) {
            passengerMarker.setLatLng([lat, lng]);
        } else if (map && isMapReady) {
            passengerMarker = L.marker([lat, lng], { icon: passengerIcon })
                .bindPopup('📍 Your Current Location')
                .addTo(map);
        }
        
        if (passengerAccuracyCircle) {
            passengerAccuracyCircle.setLatLng([lat, lng]);
            if (accuracy) {
                passengerAccuracyCircle.setRadius(accuracy);
            }
        } else if (accuracy && map && isMapReady) {
            passengerAccuracyCircle = L.circle([lat, lng], {
                radius: accuracy,
                color: '#f59e0b',
                fillColor: '#f59e0b',
                fillOpacity: 0.15,
                weight: 1
            }).addTo(map);
        }
        
        currentData.passengerLat = lat;
        currentData.passengerLng = lng;
        
        if (currentData.hasDriver && !currentData.isCompleted) {
            await updateRouteForPassenger();
        }
        
        return data;
    } catch (error) {
        console.log('Location update error:', error);
    }
}

function startPassengerTracking() {
    if (!shouldTrackPassenger) {
        console.log('Passenger tracking not needed for current status');
        return;
    }
    
    if (!navigator.geolocation) {
        console.log('Geolocation not supported');
        return;
    }
    
    watchPositionId = navigator.geolocation.watchPosition(
        async (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;
            
            const now = Date.now();
            if (now - lastPassengerUpdate >= 2000) {
                lastPassengerUpdate = now;
                await sendPassengerLocation(lat, lng, accuracy);
                
                updateGPSAccuracyIndicator(accuracy);
            }
        },
        (error) => {
            console.log('GPS Error:', error.message);
            let errorMsg = '';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMsg = 'Location permission denied. Please enable GPS.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMsg = 'Location unavailable. Please check your GPS.';
                    break;
                case error.TIMEOUT:
                    errorMsg = 'Location timeout. Please try again.';
                    break;
            }
            const locationInfo = document.querySelector('.location-info');
            if (locationInfo && errorMsg) {
                const errorDiv = locationInfo.querySelector('.gps-error');
                if (!errorDiv) {
                    const err = document.createElement('div');
                    err.className = 'gps-error';
                    err.style.color = '#ef4444';
                    err.style.fontSize = '10px';
                    err.textContent = '⚠️ ' + errorMsg;
                    locationInfo.appendChild(err);
                    setTimeout(() => err.remove(), 5000);
                }
            }
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
    
    passengerUpdateInterval = setInterval(async () => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const accuracy = position.coords.accuracy;
                    await sendPassengerLocation(lat, lng, accuracy);
                },
                (error) => {
                    console.log('Periodic location error:', error.message);
                },
                { enableHighAccuracy: true, timeout: 5000 }
            );
        }
    }, 5000);
}

function updateGPSAccuracyIndicator(accuracy) {
    const locationInfo = document.querySelector('.location-info');
    if (!locationInfo) return;
    
    let existingIndicator = locationInfo.querySelector('.gps-accuracy-indicator');
    if (!existingIndicator) {
        existingIndicator = document.createElement('div');
        existingIndicator.className = 'gps-accuracy-indicator';
        existingIndicator.style.display = 'flex';
        existingIndicator.style.alignItems = 'center';
        existingIndicator.style.gap = '5px';
        existingIndicator.style.fontSize = '10px';
        existingIndicator.style.marginLeft = 'auto';
        locationInfo.appendChild(existingIndicator);
    }
    
    let accuracyClass = 'gps-low';
    let accuracyText = 'Poor';
    if (accuracy <= 10) {
        accuracyClass = 'gps-high';
        accuracyText = 'High';
    } else if (accuracy <= 50) {
        accuracyClass = 'gps-medium';
        accuracyText = 'Medium';
    }
    
    existingIndicator.innerHTML = `
        <span>📡 GPS:</span>
        <span class="gps-accuracy ${accuracyClass}"></span>
        <span>${accuracyText} (${Math.round(accuracy)}m)</span>
    `;
}

function stopPassengerTracking() {
    if (watchPositionId !== null) {
        navigator.geolocation.clearWatch(watchPositionId);
        watchPositionId = null;
    }
    if (passengerUpdateInterval) {
        clearInterval(passengerUpdateInterval);
        passengerUpdateInterval = null;
    }
}

function performSmoothReload() {
    if (currentData.isCompleted || currentData.isCancelled) {
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
        return;
    }
    
    const scrollY = window.scrollY;
    localStorage.setItem('saved_scroll_position', scrollY);
    
    if (map && isMapReady) {
        saveMapView();
    }
    
    document.body.style.transition = 'opacity 0.1s ease-in-out';
    document.body.style.opacity = '0.98';
    
    setTimeout(() => {
        window.location.reload();
    }, 50);
}

window.addEventListener('load', function() {
    const savedScroll = localStorage.getItem('saved_scroll_position');
    if (savedScroll) {
        window.scrollTo(0, parseInt(savedScroll));
        localStorage.removeItem('saved_scroll_position');
    }
    document.body.style.opacity = '1';
});

async function cancelBooking(bookingId) {
    const result = await Swal.fire({
        title: 'Cancel Booking?',
        text: "Are you sure you want to cancel this booking?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Cancel',
        cancelButtonText: 'No'
    });
    
    if (result.isConfirmed) {
        Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        try {
            const formData = new URLSearchParams();
            formData.append('booking_id', bookingId);
            
            const response = await fetch('cancel-booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            
            const data = await response.json();
            
            if (data.success) {
                stopPassengerTracking();
                Swal.fire({ icon: 'success', title: 'Cancelled!', timer: 2000, showConfirmButton: false })
                    .then(() => window.location.href = 'my-bookings.php');
            } else {
                Swal.fire({ icon: 'error', title: 'Failed', text: data.message });
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Connection failed' });
        }
    }
}

document.addEventListener('DOMContentLoaded', async function() {
    console.log('🚀 Page loaded - SMOOTH AUTO-RELOAD every 4 seconds');
    console.log('📍 Passenger tracking enabled:', shouldTrackPassenger);
    
    if (currentData.isCancelled) {
        const mapDiv = document.getElementById('map');
        if (mapDiv) {
            mapDiv.style.display = 'flex';
            mapDiv.style.alignItems = 'center';
            mapDiv.style.justifyContent = 'center';
            mapDiv.style.backgroundColor = '#e2e8f0';
            mapDiv.innerHTML = '<div style="text-align: center; color: #991b1b;"><span style="font-size: 48px;">❌</span><br><strong>Booking Cancelled</strong></div>';
        }
        startCountdown();
        return;
    }
    
    let mapCenter = [14.5995, 120.9842];
    let mapZoom = 13;
    
    if (shouldTrackPassenger && currentData.passengerLat) {
        mapCenter = [currentData.passengerLat, currentData.passengerLng];
        mapZoom = 15;
    } else if (currentData.hasDriver && currentData.driverLat) {
        mapCenter = [currentData.driverLat, currentData.driverLng];
        mapZoom = 14;
    } else if (currentData.pickupLat) {
        mapCenter = [currentData.pickupLat, currentData.pickupLng];
        mapZoom = 14;
    }
    
    map = L.map('map').setView(mapCenter, mapZoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
        attribution: '© OpenStreetMap', 
        maxZoom: 19 
    }).addTo(map);
    L.control.scale().addTo(map);
    
    const viewRestored = loadSavedMapView();
    
    if (currentData.isCompleted) {
        if (currentData.pickupLat) L.marker([currentData.pickupLat, currentData.pickupLng], { icon: pickupIcon }).addTo(map);
        if (currentData.dropoffLat) L.marker([currentData.dropoffLat, currentData.dropoffLng], { icon: dropoffIcon }).addTo(map);
        if (currentData.pickupLat && currentData.dropoffLat) {
            await drawAccurateRoute(currentData.pickupLat, currentData.pickupLng, currentData.dropoffLat, currentData.dropoffLng, '#3b82f6', false);
            if (!viewRestored) map.fitBounds([[currentData.pickupLat, currentData.pickupLng], [currentData.dropoffLat, currentData.dropoffLng]], { padding: [50, 50] });
        }
    } 
    else if (currentData.hasDriver && currentData.driverLat) {
        driverMarker = L.marker([currentData.driverLat, currentData.driverLng], { icon: driverIcon })
            .bindPopup(currentData.isPickedUp ? '🛺 Taking you to destination!' : '🛺 Coming to pickup location')
            .addTo(map);
        
        if (currentData.passengerLat) {
            passengerMarker = L.marker([currentData.passengerLat, currentData.passengerLng], { icon: passengerIcon })
                .bindPopup('📍 Your current location').addTo(map);
        } else if (shouldTrackPassenger) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(async (pos) => {
                    await sendPassengerLocation(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
                }, null, { enableHighAccuracy: true, timeout: 5000 });
            }
        }
        
        if (currentData.pickupLat) {
            pickupMarker = L.marker([currentData.pickupLat, currentData.pickupLng], { icon: pickupIcon })
                .bindPopup(currentData.isPickedUp ? '📍 Pickup location' : '📍 Meet driver here')
                .addTo(map);
        }
        if (currentData.dropoffLat) {
            dropoffMarker = L.marker([currentData.dropoffLat, currentData.dropoffLng], { icon: dropoffIcon })
                .bindPopup('🏁 Destination').addTo(map);
        }
        
        await updateRouteForPassenger();
        
        if (!viewRestored) {
            let bounds = [[currentData.driverLat, currentData.driverLng]];
            if (currentData.passengerLat && currentData.isPickedUp) {
                bounds.push([currentData.passengerLat, currentData.passengerLng]);
            }
            if (currentData.pickupLat && !currentData.isPickedUp) {
                bounds.push([currentData.pickupLat, currentData.pickupLng]);
            }
            if (currentData.dropoffLat && currentData.isPickedUp) {
                bounds.push([currentData.dropoffLat, currentData.dropoffLng]);
            }
            if (bounds.length > 1) map.fitBounds(bounds, { padding: [50, 50] });
        }
    }
    else if (currentData.pickupLat) {
        L.marker([currentData.pickupLat, currentData.pickupLng], { icon: pickupIcon })
            .bindPopup('📍 Pickup Location').addTo(map);
        if (currentData.dropoffLat) {
            L.marker([currentData.dropoffLat, currentData.dropoffLng], { icon: dropoffIcon })
                .bindPopup('🏁 Destination').addTo(map);
            await drawAccurateRoute(currentData.pickupLat, currentData.pickupLng, currentData.dropoffLat, currentData.dropoffLng, '#3b82f6', false);
            if (!viewRestored) map.fitBounds([[currentData.pickupLat, currentData.pickupLng], [currentData.dropoffLat, currentData.dropoffLng]], { padding: [50, 50] });
        }
    }
    
    setTimeout(() => {
        isMapReady = true;
        if (!viewRestored) setTimeout(() => { if (isMapReady) saveMapView(); }, 1000);
    }, 500);
    
    map.on('moveend', () => { if (isMapReady) saveMapView(); });
    map.on('zoomend', () => { if (isMapReady) saveMapView(); });
    
    if (shouldTrackPassenger && !currentData.isCompleted && !currentData.isCancelled) {
        console.log('🎯 Starting real-time passenger GPS tracking...');
        startPassengerTracking();
    }
    
    startCountdown();
    
    if (!shouldTrackPassenger && navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            fetch('update-passenger-location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lat: pos.coords.latitude, lng: pos.coords.longitude, booking_id: currentData.bookingId })
            }).catch(err => console.log('Location update error:', err));
        }, function(err) {
            console.log('GPS error:', err.message);
        }, { enableHighAccuracy: true, timeout: 10000 });
    }
});

window.addEventListener('beforeunload', () => {
    if (countdownInterval) clearInterval(countdownInterval);
    if (map && isMapReady && !currentData.isCancelled) saveMapView();
    stopPassengerTracking();
});
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>