<?php
require_once '../includes/config.php';

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (!$booking_id || !$token) {
    die('<h2>Invalid Sharing Link</h2><p>This link appears to be invalid or expired.</p>');
}

// Get booking details
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    die('<h2>Booking Not Found</h2><p>The booking you\'re looking for doesn\'t exist.</p>');
}

// Verify token
$expected_token = md5($booking['booking_code'] . $booking['passenger_id']);
if ($token !== $expected_token) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to view this booking.</p>');
}

// Get TODA info
$toda_name = $booking['toda_name'] ?? null;
if (empty($toda_name) && !empty($booking['toda_id'])) {
    $toda_stmt = $conn->prepare("SELECT toda_name FROM todas WHERE id = ?");
    $toda_stmt->bind_param("i", $booking['toda_id']);
    $toda_stmt->execute();
    $toda_result = $toda_stmt->get_result()->fetch_assoc();
    if ($toda_result) {
        $toda_name = $toda_result['toda_name'];
    }
}

// Get driver info
$has_driver = !empty($booking['driver_id']);
$driver_info = null;
$driver_lat = null;
$driver_lng = null;

if ($has_driver && $booking['status'] != 'CANCELLED') {
    $driver_stmt = $conn->prepare("
        SELECT u.name, u.email, u.contact, u.profile, u.lat, u.lng,
               d.vehicle_plate, d.vehicle_color, d.vehicle_type
        FROM users u
        LEFT JOIN drivers d ON u.id = d.user_id
        WHERE u.id = ? AND u.role = 'driver'
    ");
    $driver_stmt->bind_param("i", $booking['driver_id']);
    $driver_stmt->execute();
    $driver_info = $driver_stmt->get_result()->fetch_assoc();
    
    if ($driver_info && !empty($driver_info['lat']) && !empty($driver_info['lng'])) {
        $driver_lat = floatval($driver_info['lat']);
        $driver_lng = floatval($driver_info['lng']);
    }
}

// Get passenger location (if available)
$passenger_lat = null;
$passenger_lng = null;
$passenger_stmt = $conn->prepare("SELECT lat, lng FROM users WHERE id = ? AND role = 'passenger'");
$passenger_stmt->bind_param("i", $booking['passenger_id']);
$passenger_stmt->execute();
$passenger_location = $passenger_stmt->get_result()->fetch_assoc();
if ($passenger_location && !empty($passenger_location['lat']) && !empty($passenger_location['lng'])) {
    $passenger_lat = floatval($passenger_location['lat']);
    $passenger_lng = floatval($passenger_location['lng']);
}

// Get pickup/dropoff coordinates
$pickup_lat = isset($booking['pickup_lat']) && $booking['pickup_lat'] ? floatval($booking['pickup_lat']) : null;
$pickup_lng = isset($booking['pickup_lng']) && $booking['pickup_lng'] ? floatval($booking['pickup_lng']) : null;
$dropoff_lat = isset($booking['dropoff_lat']) && $booking['dropoff_lat'] ? floatval($booking['dropoff_lat']) : null;
$dropoff_lng = isset($booking['dropoff_lng']) && $booking['dropoff_lng'] ? floatval($booking['dropoff_lng']) : null;

$is_completed = ($booking['status'] == 'COMPLETED');
$is_cancelled = ($booking['status'] == 'CANCELLED');
$is_picked_up = ($booking['status'] == 'PASSENGER PICKED UP');
$is_accepted = ($booking['status'] == 'ACCEPTED');

$payment_method = $booking['payment_method'] ?? 'cash';
$method_display = '';
switch(strtolower($payment_method)) {
    case 'cash': $method_display = '💵 Cash'; break;
    case 'card': $method_display = '💳 Card'; break;
    case 'gcash': $method_display = '📱 GCash'; break;
    case 'maya': $method_display = '📱 Maya'; break;
    default: $method_display = '💵 ' . ucfirst($payment_method);
}

// Auto-refresh interval (seconds)
$refresh_interval = 8;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Live Trip Tracking - GoTrike Shared View</title>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            min-height: 100vh;
        }
        
        .shared-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        @media (min-width: 768px) {
            .shared-container {
                padding: 1.5rem;
            }
        }
        
        /* Header */
        .shared-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            color: white;
            display: flex;
 justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .shared-header h1 {
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .shared-header .ref-code {
            background: rgba(255,255,255,0.2);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-family: monospace;
        }
        
        .live-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(0,0,0,0.3);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
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
        
        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        @media (min-width: 768px) {
            .two-columns {
                grid-template-columns: 1fr 0.8fr;
                gap: 1.5rem;
            }
        }
        
        /* Map Card */
        .map-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        #map {
            width: 100%;
            height: 400px;
            background: #e2e8f0;
        }
        
        @media (min-width: 768px) {
            #map {
                height: 500px;
            }
        }
        
        .map-controls {
            padding: 0.75rem;
            background: #f7fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-map {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-driver { background: #3b82f6; color: white; }
        .btn-passenger { background: #f59e0b; color: white; }
        .btn-refresh-map { background: #48bb78; color: white; }
        
        /* Info Card */
        .info-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .info-header {
            background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
            color: white;
            padding: 1rem;
            text-align: center;
        }
        
        .info-header h2 {
            font-size: 1rem;
            margin: 0;
        }
        
        .info-body {
            padding: 1rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .info-label {
            font-weight: bold;
            color: #6b7280;
            font-size: 0.75rem;
        }
        
        .info-value {
            color: #1f2937;
            font-size: 0.8rem;
            text-align: right;
            font-weight: 500;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-accepted { background: #dbeafe; color: #1e40af; }
        .status-passenger_picked_up { background: #fed7aa; color: #9a3412; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        .fare-highlight {
            font-size: 1.1rem;
            font-weight: bold;
            color: #667eea;
        }
        
        /* Driver Card */
        .driver-card {
            background: #f3f4f6;
            border-radius: 16px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .driver-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        
        .driver-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .driver-name {
            font-weight: bold;
            font-size: 1rem;
        }
        
        .driver-contact {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: #e0e7ff;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
            text-decoration: none;
            color: #4338ca;
            margin-top: 0.3rem;
        }
        
        .vehicle-info {
            background: white;
            padding: 0.75rem;
            border-radius: 12px;
            margin-top: 0.5rem;
        }
        
        .vehicle-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            padding: 0.3rem 0;
        }
        
        .waiting-screen {
            text-align: center;
            padding: 2rem;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            margin: 0 auto 1rem;
            border: 3px solid #e2e8f0;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .auto-refresh-note {
            text-align: center;
            font-size: 0.7rem;
            color: #6b7280;
            padding: 0.75rem;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        
        .refresh-countdown {
            color: #667eea;
            font-weight: bold;
        }
        
        .note {
            background: #fef3c7;
            padding: 0.75rem;
            border-radius: 12px;
            font-size: 0.7rem;
            text-align: center;
            margin-top: 1rem;
            color: #92400e;
        }
        
        @media print {
            .map-card, .btn-map, .auto-refresh-note { display: none; }
            .two-columns { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="shared-container">
    <!-- Header -->
    <div class="shared-header">
        <h1>
            <span>🛺</span> GoTrike Live Tracking
        </h1>
        <div class="live-badge">
            <span class="live-dot"></span>
            <span>LIVE UPDATES</span>
            <span class="ref-code">#<?= htmlspecialchars($booking['booking_code']) ?></span>
        </div>
    </div>
    
    <div class="two-columns">
        <!-- LEFT COLUMN: MAP -->
        <div class="map-card">
            <div id="map"></div>
            <div class="map-controls">
                <button onclick="centerOnDriver()" class="btn-map btn-driver">🎯 Center on Driver</button>
                <button onclick="centerOnPickup()" class="btn-map" style="background:#48bb78; color:white;">📍 Center on Pickup</button>
                <button onclick="refreshMapData()" class="btn-map btn-refresh-map">⟳ Refresh Map</button>
            </div>
            <div class="auto-refresh-note">
                🔄 Auto-refreshing map every <span class="refresh-countdown" id="countdown"><?= $refresh_interval ?></span> seconds
            </div>
        </div>
        
        <!-- RIGHT COLUMN: DETAILS -->
        <div class="info-card">
            <div class="info-header">
                <h2>📋 TRIP DETAILS</h2>
            </div>
            <div class="info-body">
                <div class="info-row">
                    <span class="info-label">STATUS</span>
                    <span class="info-value">
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '_', $booking['status'] ?? 'pending')) ?>">
                            <?= htmlspecialchars($booking['status'] ?? 'PENDING') ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">TODA GROUP</span>
                    <span class="info-value"><?= htmlspecialchars($toda_name ?: '—') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">📍 PICKUP</span>
                    <span class="info-value"><?= htmlspecialchars($booking['pickup_landmark'] ?: '—') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">📍 DROP-OFF</span>
                    <span class="info-value"><?= htmlspecialchars($booking['dropoff_landmark'] ?: '—') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">👥 PASSENGERS</span>
                    <span class="info-value"><?= htmlspecialchars($booking['total_pax'] ?? '1') ?> person(s)</span>
                </div>
                <div class="info-row">
                    <span class="info-label">🚲 TRICYCLES</span>
                    <span class="info-value"><?= htmlspecialchars($booking['trike_units'] ?? '1') ?> unit(s)</span>
                </div>
                <div class="info-row">
                    <span class="info-label">📏 DISTANCE</span>
                    <span class="info-value"><?= htmlspecialchars($booking['distance'] ?? '0') ?> km</span>
                </div>
                <div class="info-row">
                    <span class="info-label">💰 FARE</span>
                    <span class="info-value fare-highlight">₱ <?= number_format($booking['fare_amount'] ?? $booking['fare'] ?? 0, 2) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">💳 PAYMENT</span>
                    <span class="info-value"><?= htmlspecialchars($method_display) ?></span>
                </div>
                
                <?php if (!empty($booking['notes'])): ?>
                <div class="info-row">
                    <span class="info-label">📝 NOTES</span>
                    <span class="info-value"><?= htmlspecialchars($booking['notes']) ?></span>
                </div>
                <?php endif; ?>
                
                <!-- DRIVER SECTION -->
                <?php if ($driver_info && !$is_cancelled): ?>
                <div class="driver-card">
                    <div class="driver-profile">
                        <div class="driver-avatar">
                            <?php if (!empty($driver_info['profile']) && file_exists('../' . $driver_info['profile'])): ?>
                                <img src="../<?= htmlspecialchars($driver_info['profile']) ?>" style="width:50px; height:50px; border-radius:50%; object-fit:cover;">
                            <?php else: ?>👨‍✈️<?php endif; ?>
                        </div>
                        <div>
                            <div class="driver-name"><?= htmlspecialchars($driver_info['name'] ?? 'Driver') ?></div>
                            <?php if (!empty($driver_info['contact'])): ?>
                                <a href="tel:<?= htmlspecialchars($driver_info['contact']) ?>" class="driver-contact">📞 <?= htmlspecialchars($driver_info['contact']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="vehicle-info">
                        <div class="vehicle-row"><span>Vehicle Type:</span><span><?= htmlspecialchars($driver_info['vehicle_type'] ?? 'Tricycle') ?></span></div>
                       
                    </div>
                </div>
                <?php elseif (!$is_completed && !$is_cancelled): ?>
                <div class="waiting-screen">
                    <div class="spinner"></div>
                    <p style="font-size: 0.8rem;">Looking for available drivers...</p>
                </div>
                <?php endif; ?>
                
                <div class="note">
                    📌 <strong>Shared Tracking Link</strong><br>
                    This page updates automatically every <?= $refresh_interval ?> seconds.
                    Bookmark this link to track the trip in real-time.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// LIVE MAP FOR SHARED TRIP VIEW
// Auto-refreshes every N seconds
// ============================================

let map;
let driverMarker, pickupMarker, dropoffMarker, passengerMarker;
let routeLine = null;
let refreshTimer;
let countdown = <?= $refresh_interval ?>;
let countdownInterval;

// Data from PHP
const bookingData = {
    bookingId: <?= $booking_id ?>,
    hasDriver: <?= ($has_driver && $driver_lat !== null) ? 'true' : 'false' ?>,
    isCompleted: <?= $is_completed ? 'true' : 'false' ?>,
    isCancelled: <?= $is_cancelled ? 'true' : 'false' ?>,
    isPickedUp: <?= $is_picked_up ? 'true' : 'false' ?>,
    driverLat: <?= $driver_lat !== null ? $driver_lat : 'null' ?>,
    driverLng: <?= $driver_lng !== null ? $driver_lng : 'null' ?>,
    passengerLat: <?= $passenger_lat !== null ? $passenger_lat : 'null' ?>,
    passengerLng: <?= $passenger_lng !== null ? $passenger_lng : 'null' ?>,
    pickupLat: <?= $pickup_lat !== null ? $pickup_lat : 'null' ?>,
    pickupLng: <?= $pickup_lng !== null ? $pickup_lng : 'null' ?>,
    dropoffLat: <?= $dropoff_lat !== null ? $dropoff_lat : 'null' ?>,
    dropoffLng: <?= $dropoff_lng !== null ? $dropoff_lng : 'null' ?>
};

// Custom Icons
const driverIcon = L.divIcon({
    html: '<div style="background: #3b82f6; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><span style="font-size: 22px;">🛺</span><div style="position: absolute; bottom: -22px; white-space: nowrap; background: #3b82f6; color: white; padding: 2px 6px; border-radius: 12px; font-size: 9px; font-weight: bold;">DRIVER</div></div>',
    iconSize: [40, 40],
    popupAnchor: [0, -22]
});

const passengerIcon = L.divIcon({
    html: '<div style="background: #f59e0b; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><span style="font-size: 16px;">🧑</span><div style="position: absolute; bottom: -20px; white-space: nowrap; background: #f59e0b; color: white; padding: 2px 6px; border-radius: 12px; font-size: 9px; font-weight: bold;">PASSENGER</div></div>',
    iconSize: [32, 32],
    popupAnchor: [0, -18]
});

const pickupIcon = L.divIcon({
    html: '<div style="background: #48bb78; border-radius: 50%; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><span style="font-size: 18px;">📍</span><div style="position: absolute; bottom: -20px; white-space: nowrap; background: #48bb78; color: white; padding: 2px 6px; border-radius: 12px; font-size: 9px; font-weight: bold;">PICKUP</div></div>',
    iconSize: [34, 34],
    popupAnchor: [0, -19]
});

const dropoffIcon = L.divIcon({
    html: '<div style="background: #f56565; border-radius: 50%; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><span style="font-size: 18px;">🏁</span><div style="position: absolute; bottom: -20px; white-space: nowrap; background: #f56565; color: white; padding: 2px 6px; border-radius: 12px; font-size: 9px; font-weight: bold;">DROP OFF</div></div>',
    iconSize: [34, 34],
    popupAnchor: [0, -19]
});

// Initialize Map
function initMap() {
    let defaultCenter = [14.5995, 120.9842];
    let defaultZoom = 13;
    
    if (bookingData.hasDriver && bookingData.driverLat) {
        defaultCenter = [bookingData.driverLat, bookingData.driverLng];
        defaultZoom = 14;
    } else if (bookingData.pickupLat) {
        defaultCenter = [bookingData.pickupLat, bookingData.pickupLng];
        defaultZoom = 14;
    }
    
    map = L.map('map').setView(defaultCenter, defaultZoom);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    L.control.scale().addTo(map);
    
    // Draw markers and route
    drawMapElements();
}

// Draw route using OSRM
async function drawRoute(startLat, startLng, endLat, endLng, color = '#3b82f6') {
    if (routeLine) map.removeLayer(routeLine);
    
    try {
        const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${startLng},${startLat};${endLng},${endLat}?overview=full&geometries=geojson`);
        const data = await response.json();
        
        if (data.routes && data.routes[0]) {
            const coordinates = data.routes[0].geometry.coordinates.map(coord => [coord[1], coord[0]]);
            routeLine = L.polyline(coordinates, {
                color: color,
                weight: 4,
                opacity: 0.8,
                lineCap: 'round'
            }).addTo(map);
            
            const distanceKm = (data.routes[0].distance / 1000).toFixed(1);
            const timeMin = Math.round(data.routes[0].duration / 60);
            routeLine.bindPopup(`🛺 Route: ${distanceKm} km | ~${timeMin} mins`);
            return true;
        }
    } catch (error) {
        console.log('Route fetch error:', error);
    }
    
    // Fallback: straight line
    routeLine = L.polyline([[startLat, startLng], [endLat, endLng]], {
        color: color,
        weight: 3,
        opacity: 0.5,
        dashArray: '5, 10'
    }).addTo(map);
    return false;
}

// Draw all map elements
async function drawMapElements() {
    // Clear existing markers
    if (driverMarker) map.removeLayer(driverMarker);
    if (pickupMarker) map.removeLayer(pickupMarker);
    if (dropoffMarker) map.removeLayer(dropoffMarker);
    if (passengerMarker) map.removeLayer(passengerMarker);
    
    // Draw markers based on status
    if (bookingData.hasDriver && bookingData.driverLat && !bookingData.isCompleted && !bookingData.isCancelled) {
        driverMarker = L.marker([bookingData.driverLat, bookingData.driverLng], { icon: driverIcon })
            .bindPopup(bookingData.isPickedUp ? '🛺 Taking passenger to destination' : '🛺 En route to pickup')
            .addTo(map);
    }
    
    // Passenger location (if available and not completed)
    if (bookingData.passengerLat && !bookingData.isCompleted) {
        passengerMarker = L.marker([bookingData.passengerLat, bookingData.passengerLng], { icon: passengerIcon })
            .bindPopup('📍 Passenger Location')
            .addTo(map);
    }
    
    // Pickup location
    if (bookingData.pickupLat) {
        pickupMarker = L.marker([bookingData.pickupLat, bookingData.pickupLng], { icon: pickupIcon })
            .bindPopup('📍 Pickup Location')
            .addTo(map);
    }
    
    // Dropoff location
    if (bookingData.dropoffLat) {
        dropoffMarker = L.marker([bookingData.dropoffLat, bookingData.dropoffLng], { icon: dropoffIcon })
            .bindPopup('🏁 Destination')
            .addTo(map);
    }
    
    // Draw route
    if (bookingData.isPickedUp && bookingData.passengerLat && bookingData.dropoffLat) {
        // Show route from passenger to dropoff
        await drawRoute(bookingData.passengerLat, bookingData.passengerLng, 
                       bookingData.dropoffLat, bookingData.dropoffLng, '#ef4444');
    } 
    else if (bookingData.hasDriver && bookingData.driverLat && bookingData.pickupLat) {
        // Show route from driver to pickup
        await drawRoute(bookingData.driverLat, bookingData.driverLng,
                       bookingData.pickupLat, bookingData.pickupLng, '#3b82f6');
    }
    else if (bookingData.pickupLat && bookingData.dropoffLat && bookingData.isCompleted) {
        // Show completed trip route
        await drawRoute(bookingData.pickupLat, bookingData.pickupLng,
                       bookingData.dropoffLat, bookingData.dropoffLng, '#6b7280');
    }
    
    // Fit bounds to show all relevant points
    const bounds = [];
    if (bookingData.driverLat && !bookingData.isCompleted) bounds.push([bookingData.driverLat, bookingData.driverLng]);
    if (bookingData.passengerLat && !bookingData.isCompleted) bounds.push([bookingData.passengerLat, bookingData.passengerLng]);
    if (bookingData.pickupLat) bounds.push([bookingData.pickupLat, bookingData.pickupLng]);
    if (bookingData.dropoffLat) bounds.push([bookingData.dropoffLat, bookingData.dropoffLng]);
    
    if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [50, 50] });
    }
}

// Center functions
function centerOnDriver() {
    if (driverMarker) {
        const pos = driverMarker.getLatLng();
        map.flyTo([pos.lat, pos.lng], 16);
    } else if (bookingData.driverLat) {
        map.flyTo([bookingData.driverLat, bookingData.driverLng], 16);
    }
}

function centerOnPickup() {
    if (bookingData.pickupLat) {
        map.flyTo([bookingData.pickupLat, bookingData.pickupLng], 16);
    }
}

// Refresh map data via AJAX
async function refreshMapData() {
    try {
        const response = await fetch(`get-booking-live-data.php?id=<?= $booking_id ?>&token=<?= urlencode($token) ?>`);
        const data = await response.json();
        
        if (data.success) {
            // Update bookingData
            bookingData.hasDriver = data.hasDriver;
            bookingData.isPickedUp = data.isPickedUp;
            bookingData.isCompleted = data.isCompleted;
            bookingData.isCancelled = data.isCancelled;
            bookingData.driverLat = data.driverLat;
            bookingData.driverLng = data.driverLng;
            bookingData.passengerLat = data.passengerLat;
            bookingData.passengerLng = data.passengerLng;
            
            // Redraw map
            await drawMapElements();
        }
    } catch (error) {
        console.log('Refresh error:', error);
    }
}

// Auto-refresh countdown
function startAutoRefresh() {
    countdownInterval = setInterval(() => {
        countdown--;
        const countdownEl = document.getElementById('countdown');
        if (countdownEl) countdownEl.textContent = countdown;
        
        if (countdown <= 0) {
            countdown = <?= $refresh_interval ?>;
            refreshMapData();
        }
    }, 1000);
}

// Reload page fully (for major updates)
function fullPageReload() {
    if (!bookingData.isCompleted && !bookingData.isCancelled) {
        setTimeout(() => {
            window.location.reload();
        }, 100);
    }
}

// Start everything
document.addEventListener('DOMContentLoaded', () => {
    initMap();
    startAutoRefresh();
    
    // Also refresh full page every 30 seconds for driver assignment updates
    if (!bookingData.isCompleted && !bookingData.isCancelled) {
        setInterval(() => {
            fullPageReload();
        }, 30000);
    }
});
</script>

</body>
</html>