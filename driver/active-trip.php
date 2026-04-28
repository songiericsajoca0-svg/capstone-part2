<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../dashboard.php");
    exit;
}

$driver_id = $_SESSION['user_id'];
$driver_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Driver';

// Get booking ID from URL
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($booking_id == 0) {
    // Get the most recent active trip (ACCEPTED or PASSENGER PICKED UP)
    $trip_query = $conn->prepare("
        SELECT b.*, u.name as passenger_name, u.contact as passenger_contact 
        FROM bookings b
        LEFT JOIN users u ON b.passenger_id = u.id
        WHERE b.driver_id = ? AND b.status IN ('ACCEPTED', 'PASSENGER PICKED UP')
        ORDER BY b.created_at DESC
        LIMIT 1
    ");
    $trip_query->bind_param("i", $driver_id);
    $trip_query->execute();
    $trip = $trip_query->get_result()->fetch_assoc();
} else {
    $trip_query = $conn->prepare("
        SELECT b.*, u.name as passenger_name, u.contact as passenger_contact 
        FROM bookings b
        LEFT JOIN users u ON b.passenger_id = u.id
        WHERE b.id = ? AND b.driver_id = ?
    ");
    $trip_query->bind_param("ii", $booking_id, $driver_id);
    $trip_query->execute();
    $trip = $trip_query->get_result()->fetch_assoc();
}

// If no active trip, redirect back to dashboard
if (!$trip) {
    header("Location: driver_dashboard.php");
    exit;
}

// Get driver's current location from users table
$driver_query = $conn->prepare("SELECT lat, lng FROM users WHERE id = ? AND role = 'driver'");
$driver_query->bind_param("i", $driver_id);
$driver_query->execute();
$driver_location = $driver_query->get_result()->fetch_assoc();

// Get passenger's current location from users table
$passenger_id = $trip['passenger_id'];
$passenger_query = $conn->prepare("SELECT lat, lng, last_location_update FROM users WHERE id = ? AND role = 'passenger'");
$passenger_query->bind_param("i", $passenger_id);
$passenger_query->execute();
$passenger_location = $passenger_query->get_result()->fetch_assoc();

// Get coordinates from bookings table
$pickup_coords = null;
$dropoff_coords = null;

if (!empty($trip['pickup_lat']) && !empty($trip['pickup_lng'])) {
    $pickup_coords = [
        'lat' => (float)$trip['pickup_lat'], 
        'lng' => (float)$trip['pickup_lng']
    ];
}

if (!empty($trip['dropoff_lat']) && !empty($trip['dropoff_lng'])) {
    $dropoff_coords = [
        'lat' => (float)$trip['dropoff_lat'], 
        'lng' => (float)$trip['dropoff_lng']
    ];
}

// Fallback: If no coordinates in bookings table, try to get from locations table
if (!$pickup_coords && !empty($trip['pickup_landmark'])) {
    $pickup_query = $conn->prepare("SELECT lat, lon FROM locations WHERE name = ? LIMIT 1");
    $pickup_query->bind_param("s", $trip['pickup_landmark']);
    $pickup_query->execute();
    $pickup_result = $pickup_query->get_result()->fetch_assoc();
    if ($pickup_result) {
        $pickup_coords = ['lat' => (float)$pickup_result['lat'], 'lng' => (float)$pickup_result['lon']];
        $update_pickup = $conn->prepare("UPDATE bookings SET pickup_lat = ?, pickup_lng = ? WHERE id = ?");
        $update_pickup->bind_param("ddi", $pickup_result['lat'], $pickup_result['lon'], $trip['id']);
        $update_pickup->execute();
    }
}

if (!$dropoff_coords && !empty($trip['dropoff_landmark'])) {
    $dropoff_query = $conn->prepare("SELECT lat, lon FROM locations WHERE name = ? LIMIT 1");
    $dropoff_query->bind_param("s", $trip['dropoff_landmark']);
    $dropoff_query->execute();
    $dropoff_result = $dropoff_query->get_result()->fetch_assoc();
    if ($dropoff_result) {
        $dropoff_coords = ['lat' => (float)$dropoff_result['lat'], 'lng' => (float)$dropoff_result['lon']];
        $update_dropoff = $conn->prepare("UPDATE bookings SET dropoff_lat = ?, dropoff_lng = ? WHERE id = ?");
        $update_dropoff->bind_param("ddi", $dropoff_result['lat'], $dropoff_result['lon'], $trip['id']);
        $update_dropoff->execute();
    }
}

$current_status = $trip['status'];
$current_url = $_SERVER['REQUEST_URI'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Active Trip | GoTrike Driver</title>
    
    <!-- META REFRESH - Auto reload page every 4 seconds -->
    <meta http-equiv="refresh" content="4; url=<?= htmlspecialchars($current_url) ?>">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <?php include '../includes/header.php'; ?>

    <style>
        @font-face {
            font-family: 'NaruMonoDemo';
            src: url('../assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        * { font-family: 'NaruMonoDemo', monospace !important; }
        
        body { animation: subtlePulse 4s ease-out; }
        
        @keyframes subtlePulse {
            0% { opacity: 1; }
            50% { opacity: 0.98; }
            100% { opacity: 1; }
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        body {
            margin: 0;
            color: #1e293b;
            height: 100vh;
            overflow: hidden;
            position: relative;
        }
        
        #map {
            height: 100%;
            width: 100%;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
        }
        
        .trip-panel {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-radius: 28px 28px 0 0;
            padding: 20px;
            box-shadow: 0 -10px 35px rgba(0,0,0,0.2);
            z-index: 100;
            transition: transform 0.3s ease;
            max-height: 55%;
            overflow-y: auto;
            pointer-events: auto;
        }
        
        .trip-panel.minimized {
            transform: translateY(calc(100% - 70px));
        }
        
        .panel-handle {
            text-align: center;
            padding: 8px;
            cursor: pointer;
            margin: -10px 0 10px 0;
        }
        
        .panel-handle span {
            display: inline-block;
            width: 50px;
            height: 5px;
            background: #cbd5e1;
            border-radius: 5px;
        }
        
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .passenger-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .passenger-avatar {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .passenger-info h3 {
            font-size: 16px;
            margin-bottom: 5px;
            color: #1e293b;
        }
        
        .booking-code {
            font-size: 10px;
            color: #667eea;
            background: #e0e7ff;
            padding: 3px 10px;
            border-radius: 15px;
            display: inline-block;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-accepted {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-picked_up {
            background: #fed7aa;
            color: #9a3412;
        }
        
        .location-item {
            margin-bottom: 14px;
        }
        
        .location-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .location-icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .pickup-icon {
            background: #d1fae5;
            color: #10b981;
        }
        
        .dropoff-icon {
            background: #fee2e2;
            color: #ef4444;
        }
        
        .location-details {
            flex: 1;
        }
        
        .location-label {
            font-size: 9px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .location-address {
            font-size: 13px;
            color: #1e293b;
            font-weight: 500;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 15px 0;
            padding: 12px 0;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .detail-item {
            text-align: center;
        }
        
        .detail-value {
            font-size: 18px;
            font-weight: bold;
            color: #1e293b;
        }
        
        .detail-label {
            font-size: 9px;
            color: #94a3b8;
            text-transform: uppercase;
        }
        
        .fare-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
        }
        
        .fare-amount {
            font-size: 24px;
            font-weight: bold;
            color: #10b981;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 15px;
        }
        
        .btn-pickup, .btn-complete {
            flex: 1;
            padding: 14px;
            border-radius: 16px;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-pickup {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        .btn-pickup:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245,158,11,0.3);
        }
        
        .btn-complete {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16,185,129,0.3);
        }
        
        .btn-cancel {
            flex: 0.5;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            padding: 14px;
            border-radius: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        
        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: white;
            border: none;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        .locate-btn {
            position: fixed;
            bottom: 30px;
            right: 20px;
            background: white;
            border: none;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            font-size: 22px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .locate-btn:hover {
            transform: scale(1.05);
            background: #667eea;
            color: white;
        }
        
        .distance-info {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 10px;
            border-radius: 14px;
            margin-top: 12px;
            font-size: 12px;
            text-align: center;
            color: #667eea;
            font-weight: bold;
        }
        
        .progress-bar {
            margin-top: 12px;
            background: #e2e8f0;
            border-radius: 10px;
            height: 6px;
            overflow: hidden;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #10b981, #667eea);
            height: 100%;
            width: 0%;
            transition: width 0.5s ease;
            border-radius: 10px;
        }
        
        .notes-section {
            background: #fef3c7;
            padding: 10px;
            border-radius: 14px;
            margin-top: 12px;
            font-size: 11px;
            color: #92400e;
        }
        
        .connection-status {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(10px);
            color: white;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 11px;
            z-index: 200;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
        }
        
        .connection-status.online {
            background: rgba(16,185,129,0.9);
        }
        
        .connection-status.offline {
            background: rgba(239,68,68,0.9);
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: white;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.7; }
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
            font-family: monospace;
            z-index: 9999;
            pointer-events: none;
            transition: all 0.3s ease;
        }
        
        .refresh-indicator.blink {
            background: rgba(72,187,120,0.8);
            color: white;
            transform: scale(1.05);
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
            font-family: monospace;
            z-index: 9999;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .map-saved-indicator.show {
            opacity: 1;
        }
        
        .passenger-location-status {
            background: #fef3c7;
            padding: 8px 12px;
            border-radius: 12px;
            margin-top: 10px;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #92400e;
        }
        
        .passenger-location-status .live-dot-small {
            width: 6px;
            height: 6px;
            background: #10b981;
            border-radius: 50%;
            animation: blink 1.5s infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        .route-loading {
            position: absolute;
            bottom: 100px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 10px;
            z-index: 1000;
            pointer-events: none;
        }
        
        @media (max-width: 480px) {
            .trip-panel {
                padding: 15px;
                max-height: 55%;
            }
            .fare-amount {
                font-size: 20px;
            }
            .detail-value {
                font-size: 16px;
            }
        }
        
        /* Para hindi mag-flash ang panel bago ma-apply ang minimized state */
        .trip-panel {
            transition: transform 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- CRITICAL: JavaScript na nag-a-apply ng panel state AGAD -->
    <script>
        (function() {
            try {
                const PANEL_STORAGE_KEY = 'gotrike_driver_panel_state_<?= $trip['id'] ?>';
                const savedState = localStorage.getItem(PANEL_STORAGE_KEY);
                if (savedState) {
                    const state = JSON.parse(savedState);
                    const isValid = (Date.now() - state.timestamp) < 3600000;
                    if (isValid && state.minimized) {
                        const style = document.createElement('style');
                        style.textContent = '.trip-panel { transform: translateY(calc(100% - 70px)) !important; }';
                        document.head.appendChild(style);
                    }
                }
            } catch(e) {}
        })();
    </script>
    
    <div id="map"></div>
    
    <div class="connection-status online" id="connectionStatus">
        <span class="status-dot"></span>
        <span id="connectionText">Online</span>
        <span style="margin-left: 8px; font-size: 10px;" id="locationStatus">📍 Tracking ON</span>
    </div>
    
    <button class="back-btn" onclick="goBack()">←</button>
    <button class="locate-btn" onclick="centerOnDriver()">📍</button>
    
    <div id="refreshIndicator" class="refresh-indicator">🔄 Auto-refresh in <span id="countdown">4</span>s</div>
    <div id="mapSavedIndicator" class="map-saved-indicator">💾 Map view saved!</div>
    
    <div class="trip-panel" id="tripPanel">
        <div class="panel-handle" onclick="togglePanel()">
            <span></span>
        </div>
        <div class="panel-header">
            <div class="passenger-section">
                <div class="passenger-avatar">👤</div>
                <div class="passenger-info">
                    <h3><?= htmlspecialchars($trip['passenger_name'] ?? 'Guest') ?></h3>
                    <span class="booking-code">#<?= htmlspecialchars($trip['booking_code']) ?></span>
                </div>
            </div>
            <span class="status-badge <?= $current_status == 'ACCEPTED' ? 'status-accepted' : 'status-picked_up' ?>" id="statusBadge">
                <?php 
                if ($current_status == 'ACCEPTED') echo 'WAITING FOR PICKUP';
                elseif ($current_status == 'PASSENGER PICKED UP') echo 'IN TRANSIT';
                else echo htmlspecialchars($current_status);
                ?>
            </span>
        </div>
        
        <div class="passenger-location-status" id="passengerLocationStatus">
            <span class="live-dot-small"></span>
            <span>Passenger location: </span>
            <strong id="passengerLocationText">Waiting for location...</strong>
            <span id="passengerLastUpdate" style="font-size: 9px; margin-left: auto;"></span>
        </div>
        
        <div class="location-item">
            <div class="location-row">
                <div class="location-icon pickup-icon">📍</div>
                <div class="location-details">
                    <div class="location-label">PICKUP LOCATION</div>
                    <div class="location-address" id="pickupAddress"><?= htmlspecialchars($trip['pickup_landmark'] ?? 'Not specified') ?></div>
                </div>
            </div>
        </div>
        
        <div class="location-item">
            <div class="location-row">
                <div class="location-icon dropoff-icon">🏁</div>
                <div class="location-details">
                    <div class="location-label">DROP OFF LOCATION</div>
                    <div class="location-address" id="dropoffAddress"><?= htmlspecialchars($trip['dropoff_landmark'] ?? 'Not specified') ?></div>
                </div>
            </div>
        </div>
        
        <div class="details-grid">
            <div class="detail-item">
                <div class="detail-value"><?= $trip['total_pax'] ?? $trip['total_passengers'] ?? 1 ?></div>
                <div class="detail-label">Passengers</div>
            </div>
            <div class="detail-item">
                <div class="detail-value"><?= $trip['trike_units'] ?? $trip['required_tricycles'] ?? 1 ?></div>
                <div class="detail-label">Tricycles</div>
            </div>
            <div class="detail-item">
                <div class="detail-value" id="distanceValue"><?= number_format($trip['distance'] ?? $trip['distance_km'] ?? 0, 1) ?></div>
                <div class="detail-label">Distance (km)</div>
            </div>
        </div>
        
        <div class="fare-section">
            <span style="font-weight: bold;">Total Fare</span>
            <span class="fare-amount">₱ <?= number_format($trip['fare_amount'] ?? $trip['fare'] ?? 0, 2) ?></span>
        </div>
        
        <div id="tripInfo" class="distance-info">
            <?php if ($current_status == 'ACCEPTED'): ?>
                🛺 Loading tricycle route to pickup...
            <?php else: ?>
                🛺 Loading tricycle route to destination...
            <?php endif; ?>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        
        <?php if (!empty($trip['notes'])): ?>
        <div class="notes-section">
            📝 "<?= htmlspecialchars($trip['notes']) ?>"
        </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <?php if ($current_status == 'ACCEPTED'): ?>
                <button class="btn-pickup" id="pickupBtn" onclick="markAsPickedUp(<?= $trip['id'] ?>)">
                    🚗 Mark as Picked Up
                </button>
            <?php elseif ($current_status == 'PASSENGER PICKED UP'): ?>
                <button class="btn-complete" id="completeBtn" onclick="completeTrip(<?= $trip['id'] ?>)">
                    ✅ Complete Trip
                </button>
            <?php endif; ?>
            <button class="btn-cancel" onclick="cancelTrip(<?= $trip['id'] ?>)">
                ✖ Cancel
            </button>
        </div>
    </div>

    <script>
        // ============================================
        // PANEL STATE PRESERVER
        // ============================================
        
        const PANEL_STORAGE_KEY = 'gotrike_driver_panel_state_<?= $trip['id'] ?>';
        
        function savePanelState() {
            const panel = document.getElementById('tripPanel');
            const isMinimized = panel.classList.contains('minimized');
            localStorage.setItem(PANEL_STORAGE_KEY, JSON.stringify({
                minimized: isMinimized,
                timestamp: Date.now()
            }));
        }
        
        function loadAndApplyPanelState() {
            const savedState = localStorage.getItem(PANEL_STORAGE_KEY);
            const panel = document.getElementById('tripPanel');
            if (savedState && panel) {
                try {
                    const state = JSON.parse(savedState);
                    const isValid = (Date.now() - state.timestamp) < 3600000;
                    if (isValid && state.minimized) {
                        panel.classList.add('minimized');
                    } else if (isValid && !state.minimized) {
                        panel.classList.remove('minimized');
                    }
                } catch(e) {}
            }
        }
        
        function togglePanel() {
            const panel = document.getElementById('tripPanel');
            panel.classList.toggle('minimized');
            savePanelState();
        }
        
        // ============================================
        // MAP VIEW PRESERVER - AGAD NA NA-RERESTORE
        // ============================================
        
        const MAP_STORAGE_KEY = 'gotrike_driver_map_view_<?= $trip['id'] ?>';
        
        function saveMapView() {
            if (!map) return;
            const center = map.getCenter();
            const zoom = map.getZoom();
            localStorage.setItem(MAP_STORAGE_KEY, JSON.stringify({
                lat: center.lat, 
                lng: center.lng, 
                zoom: zoom, 
                timestamp: Date.now(),
                bookingId: <?= $trip['id'] ?>
            }));
            
            const indicator = document.getElementById('mapSavedIndicator');
            if (indicator) {
                indicator.classList.add('show');
                setTimeout(() => indicator.classList.remove('show'), 1500);
            }
        }
        
        function getSavedMapView() {
            const savedView = localStorage.getItem(MAP_STORAGE_KEY);
            if (savedView) {
                try {
                    const view = JSON.parse(savedView);
                    if (view.bookingId === <?= $trip['id'] ?> && (Date.now() - view.timestamp) < 3600000 && view.lat && view.lng && view.zoom) {
                        return view;
                    }
                } catch(e) {}
            }
            return null;
        }
        
        function enableAutoSaveMapView() {
            if (!map) return;
            map.on('moveend', function() { if (isMapReady) saveMapView(); });
            map.on('zoomend', function() { if (isMapReady) saveMapView(); });
        }
        
        // ============================================
        // COUNTDOWN INDICATOR
        // ============================================
        
        let refreshCountdown = 4;
        let countdownInterval;
        
        function updateCountdown() {
            const countdownEl = document.getElementById('countdown');
            if (countdownEl) countdownEl.textContent = refreshCountdown;
            const indicator = document.getElementById('refreshIndicator');
            if (refreshCountdown <= 1 && indicator) indicator.classList.add('blink');
            else if (indicator) indicator.classList.remove('blink');
            
            if (refreshCountdown <= 0) {
                if (countdownInterval) clearInterval(countdownInterval);
                resetCountdown();
            } else {
                refreshCountdown--;
            }
        }
        
        function resetCountdown() {
            refreshCountdown = 4;
            if (countdownInterval) clearInterval(countdownInterval);
            countdownInterval = setInterval(() => updateCountdown(), 1000);
        }
        
        function startCountdown() {
            refreshCountdown = 4;
            if (countdownInterval) clearInterval(countdownInterval);
            countdownInterval = setInterval(() => updateCountdown(), 1000);
        }
        
        // ============================================
        // TRICYCLE ROUTING FUNCTIONS
        // ============================================
        
        let currentRoutePolyline = null;
        let routeCache = {};
        
        async function fetchTricycleRoute(startLat, startLng, endLat, endLng) {
            const url = `get_route_driver.php?start_lat=${startLat}&start_lng=${startLng}&end_lat=${endLat}&end_lng=${endLng}`;
            
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
        
        async function drawDriverToPickupRoute(driverPos) {
            if (!pickupCoords) return null;
            
            const cacheKey = `trike_pickup_${driverPos.lat.toFixed(6)},${driverPos.lng.toFixed(6)}|${pickupCoords.lat.toFixed(6)},${pickupCoords.lng.toFixed(6)}`;
            
            if (routeCache[cacheKey] && (Date.now() - routeCache[cacheKey].timestamp) < 10000) {
                const cached = routeCache[cacheKey];
                if (currentRoutePolyline) map.removeLayer(currentRoutePolyline);
                currentRoutePolyline = L.polyline(cached.latLngs, {
                    color: '#f59e0b',
                    weight: 5,
                    opacity: 0.95,
                    lineCap: 'round',
                    lineJoin: 'round'
                }).addTo(map);
                updateTripInfo(cached.distance, 'pickup');
                return { distance: cached.distance };
            }
            
            showRouteLoading();
            const result = await fetchTricycleRoute(driverPos.lat, driverPos.lng, pickupCoords.lat, pickupCoords.lng);
            hideRouteLoading();
            
            if (result.success && result.coordinates.length > 0) {
                const latLngs = result.coordinates;
                const distanceToPickup = parseFloat((result.distance / 1000).toFixed(2));
                const etaMinutes = Math.max(1, Math.round(result.time / 60));
                
                if (currentRoutePolyline) map.removeLayer(currentRoutePolyline);
                currentRoutePolyline = L.polyline(latLngs, {
                    color: '#f59e0b',
                    weight: 5,
                    opacity: 0.95,
                    lineCap: 'round',
                    lineJoin: 'round'
                }).addTo(map);
                
                routeCache[cacheKey] = { latLngs: latLngs, distance: distanceToPickup, timestamp: Date.now() };
                updateTripInfo(distanceToPickup, 'pickup', etaMinutes);
                document.getElementById('distanceValue').innerHTML = distanceToPickup.toFixed(1);
                return { distanceToPickup };
            } else {
                if (currentRoutePolyline) map.removeLayer(currentRoutePolyline);
                currentRoutePolyline = L.polyline([[driverPos.lat, driverPos.lng], [pickupCoords.lat, pickupCoords.lng]], {
                    color: '#f59e0b',
                    weight: 3,
                    opacity: 0.6,
                    dashArray: '5, 10'
                }).addTo(map);
                const straightDistance = getDistance(driverPos, pickupCoords);
                updateTripInfo(straightDistance, 'pickup');
                return { distanceToPickup: straightDistance };
            }
        }
        
        async function drawDriverToDropoffRoute(driverPos) {
            if (!dropoffCoords) return null;
            
            const cacheKey = `trike_dropoff_${driverPos.lat.toFixed(6)},${driverPos.lng.toFixed(6)}|${dropoffCoords.lat.toFixed(6)},${dropoffCoords.lng.toFixed(6)}`;
            
            if (routeCache[cacheKey] && (Date.now() - routeCache[cacheKey].timestamp) < 10000) {
                const cached = routeCache[cacheKey];
                if (currentRoutePolyline) map.removeLayer(currentRoutePolyline);
                currentRoutePolyline = L.polyline(cached.latLngs, {
                    color: '#ef4444',
                    weight: 5,
                    opacity: 0.95,
                    lineCap: 'round',
                    lineJoin: 'round'
                }).addTo(map);
                updateTripInfo(cached.distance, 'dropoff');
                updateProgressBar(cached.distance);
                return { distance: cached.distance };
            }
            
            showRouteLoading();
            const result = await fetchTricycleRoute(driverPos.lat, driverPos.lng, dropoffCoords.lat, dropoffCoords.lng);
            hideRouteLoading();
            
            if (result.success && result.coordinates.length > 0) {
                const latLngs = result.coordinates;
                const remainingDistance = parseFloat((result.distance / 1000).toFixed(2));
                const etaMinutes = Math.max(1, Math.round(result.time / 60));
                
                if (currentRoutePolyline) map.removeLayer(currentRoutePolyline);
                currentRoutePolyline = L.polyline(latLngs, {
                    color: '#ef4444',
                    weight: 5,
                    opacity: 0.95,
                    lineCap: 'round',
                    lineJoin: 'round'
                }).addTo(map);
                
                routeCache[cacheKey] = { latLngs: latLngs, distance: remainingDistance, timestamp: Date.now() };
                updateTripInfo(remainingDistance, 'dropoff', etaMinutes);
                document.getElementById('distanceValue').innerHTML = remainingDistance.toFixed(1);
                updateProgressBar(remainingDistance);
                return { remainingDistance };
            } else {
                if (currentRoutePolyline) map.removeLayer(currentRoutePolyline);
                currentRoutePolyline = L.polyline([[driverPos.lat, driverPos.lng], [dropoffCoords.lat, dropoffCoords.lng]], {
                    color: '#ef4444',
                    weight: 3,
                    opacity: 0.6,
                    dashArray: '5, 10'
                }).addTo(map);
                const straightDistance = getDistance(driverPos, dropoffCoords);
                updateTripInfo(straightDistance, 'dropoff');
                updateProgressBar(straightDistance);
                return { remainingDistance: straightDistance };
            }
        }
        
        function showRouteLoading() {
            let loadingDiv = document.querySelector('.route-loading');
            if (!loadingDiv) {
                loadingDiv = document.createElement('div');
                loadingDiv.className = 'route-loading';
                loadingDiv.innerHTML = '🛺 Finding tricycle route...';
                document.body.appendChild(loadingDiv);
            }
            loadingDiv.style.display = 'block';
        }
        
        function hideRouteLoading() {
            const loadingDiv = document.querySelector('.route-loading');
            if (loadingDiv) loadingDiv.style.display = 'none';
        }
        
        function updateTripInfo(distance, type, etaMinutes = null) {
            const tripInfo = document.getElementById('tripInfo');
            if (type === 'pickup') {
                if (etaMinutes) {
                    tripInfo.innerHTML = `🛺 ${distance.toFixed(1)} km to pickup | ⏱️ ETA: ${etaMinutes} min (Tricycle route)`;
                } else {
                    tripInfo.innerHTML = `🛺 ${distance.toFixed(1)} km to pickup (Tricycle route)`;
                }
            } else {
                if (etaMinutes) {
                    tripInfo.innerHTML = `🛺 ${distance.toFixed(1)} km to destination | ⏱️ ETA: ${etaMinutes} min (Tricycle route)`;
                } else {
                    tripInfo.innerHTML = `🛺 ${distance.toFixed(1)} km to destination (Tricycle route)`;
                }
            }
        }
        
        function updateProgressBar(remainingDistance) {
            const fullDistance = parseFloat(<?= $trip['distance'] ?? 0 ?>);
            if (fullDistance > 0) {
                const traveledDistance = fullDistance - remainingDistance;
                const progressPercent = Math.min(100, Math.max(0, (traveledDistance / fullDistance) * 100));
                document.getElementById('progressFill').style.width = progressPercent + '%';
            }
        }
        
        function getDistance(point1, point2) {
            const R = 6371;
            const lat1 = point1.lat * Math.PI / 180;
            const lat2 = point2.lat * Math.PI / 180;
            const deltaLat = (point2.lat - point1.lat) * Math.PI / 180;
            const deltaLng = (point2.lng - point1.lng) * Math.PI / 180;
            const a = Math.sin(deltaLat/2) * Math.sin(deltaLat/2) +
                      Math.cos(lat1) * Math.cos(lat2) *
                      Math.sin(deltaLng/2) * Math.sin(deltaLng/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }
        
        // ============================================
        // MAIN MAP FUNCTIONS
        // ============================================
        
        const bookingId = <?= $trip['id'] ?>;
        const passengerId = <?= $passenger_id ?>;
        let currentStatus = '<?= $current_status ?>';
        
        let map;
        let pickupMarker, dropoffMarker, driverMarker, passengerMarker;
        let watchId, heartbeatInterval, locationSaveInterval, passengerPollInterval;
        let pickupCoords = null, dropoffCoords = null;
        let isPickedUp = (currentStatus === 'PASSENGER PICKED UP');
        let isMapReady = false;
        let lastKnownDriverPos = null;
        let lastKnownPassengerPos = null;
        let arrivalAlertShown = false, destinationAlertShown = false, isPageVisible = true;
        
        const dbPickupLat = <?= isset($pickup_coords['lat']) ? $pickup_coords['lat'] : 'null' ?>;
        const dbPickupLng = <?= isset($pickup_coords['lng']) ? $pickup_coords['lng'] : 'null' ?>;
        const dbDropoffLat = <?= isset($dropoff_coords['lat']) ? $dropoff_coords['lat'] : 'null' ?>;
        const dbDropoffLng = <?= isset($dropoff_coords['lng']) ? $dropoff_coords['lng'] : 'null' ?>;
        const driverStartLat = <?= isset($driver_location['lat']) ? $driver_location['lat'] : 'null' ?>;
        const driverStartLng = <?= isset($driver_location['lng']) ? $driver_location['lng'] : 'null' ?>;
        const passengerStartLat = <?= isset($passenger_location['lat']) ? $passenger_location['lat'] : 'null' ?>;
        const passengerStartLng = <?= isset($passenger_location['lng']) ? $passenger_location['lng'] : 'null' ?>;
        
        const defaultCenter = [14.5995, 120.9842];
        
        const driverIcon = L.divIcon({
            html: '<div style="background: #667eea; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 3px 8px rgba(0,0,0,0.2); font-size: 20px;">🛺<div style="position: absolute; bottom: -22px; left: 50%; transform: translateX(-50%); white-space: nowrap; background: #667eea; color: white; padding: 2px 8px; border-radius: 12px; font-size: 9px; font-weight: bold;">YOU</div></div>',
            iconSize: [44, 44],
            popupAnchor: [0, -25]
        });
        
        const passengerIcon = L.divIcon({
            html: '<div style="background: #f59e0b; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 3px 8px rgba(0,0,0,0.2); font-size: 18px;">🧑<div style="position: absolute; bottom: -20px; left: 50%; transform: translateX(-50%); white-space: nowrap; background: #f59e0b; color: white; padding: 2px 6px; border-radius: 12px; font-size: 8px; font-weight: bold;">PASSENGER</div></div>',
            iconSize: [40, 40],
            popupAnchor: [0, -22]
        });
        
        const pickupIcon = L.divIcon({
            html: '<div style="background: #10b981; width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 3px 8px rgba(0,0,0,0.2); font-size: 18px;">📍<div style="position: absolute; bottom: -20px; left: 50%; transform: translateX(-50%); white-space: nowrap; background: #10b981; color: white; padding: 2px 8px; border-radius: 12px; font-size: 8px; font-weight: bold;">PICK-UP</div></div>',
            iconSize: [42, 42],
            popupAnchor: [0, -23]
        });
        
        const dropoffIcon = L.divIcon({
            html: '<div style="background: #ef4444; width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 3px 8px rgba(0,0,0,0.2); font-size: 18px;">🏁<div style="position: absolute; bottom: -20px; left: 50%; transform: translateX(-50%); white-space: nowrap; background: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 8px; font-weight: bold;">DROP-OFF</div></div>',
            iconSize: [42, 42],
            popupAnchor: [0, -23]
        });
        
        async function geocodeAddress(address) {
            if (!address || address === 'Not specified') return null;
            try {
                const response = await fetch(`https://api.geoapify.com/v1/geocode/search?text=${encodeURIComponent(address)}&apiKey=2c48f43ad4134a588fbbde01128581dc`);
                const data = await response.json();
                if (data.features && data.features.length > 0) {
                    const coords = data.features[0].geometry.coordinates;
                    return { lat: coords[1], lng: coords[0] };
                }
                return null;
            } catch (error) {
                return null;
            }
        }
        
        async function pollPassengerLocation() {
            try {
                const response = await fetch(`get_passenger_location.php?passenger_id=${passengerId}&booking_id=${bookingId}&t=${Date.now()}`);
                const data = await response.json();
                
                if (data.success && data.lat && data.lng) {
                    const passengerLat = parseFloat(data.lat);
                    const passengerLng = parseFloat(data.lng);
                    lastKnownPassengerPos = { lat: passengerLat, lng: passengerLng };
                    
                    if (passengerMarker) {
                        passengerMarker.setLatLng([passengerLat, passengerLng]);
                    } else if (map) {
                        passengerMarker = L.marker([passengerLat, passengerLng], { icon: passengerIcon }).addTo(map);
                    }
                    
                    const passengerLocationText = document.getElementById('passengerLocationText');
                    const distanceToPassenger = lastKnownDriverPos ? getDistance(lastKnownDriverPos, { lat: passengerLat, lng: passengerLng }).toFixed(2) : null;
                    
                    if (passengerLocationText) {
                        if (distanceToPassenger && distanceToPassenger < 0.5) {
                            passengerLocationText.innerHTML = `📍 Nearby (${distanceToPassenger} km away)`;
                        } else if (distanceToPassenger) {
                            passengerLocationText.innerHTML = `📍 ${distanceToPassenger} km away`;
                        } else {
                            passengerLocationText.innerHTML = `📍 Live tracking active`;
                        }
                    }
                    
                    if (data.last_update) {
                        const lastUpdateEl = document.getElementById('passengerLastUpdate');
                        if (lastUpdateEl) lastUpdateEl.innerHTML = data.last_update;
                    }
                }
            } catch (error) {}
        }
        
        async function saveDriverLocationToDatabase(location) {
            if (!location || typeof location.lat !== 'number' || typeof location.lng !== 'number') return;
            try {
                await fetch('save_driver_location.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ lat: location.lat, lng: location.lng, booking_id: bookingId, status: currentStatus })
                });
            } catch (error) {}
        }
        
        function startTracking() {
            if (!navigator.geolocation) {
                Swal.fire({ icon: 'error', title: 'GPS Error', text: 'Geolocation is not supported.' });
                return;
            }
            
            locationSaveInterval = setInterval(() => {
                if (lastKnownDriverPos) saveDriverLocationToDatabase(lastKnownDriverPos);
            }, 5000);
            
            const watchOptions = { enableHighAccuracy: true, maximumAge: 0, timeout: 5000 };
            
            watchId = navigator.geolocation.watchPosition(
                async function(position) {
                    const driverLatLng = { lat: position.coords.latitude, lng: position.coords.longitude };
                    saveDriverLocationToDatabase(driverLatLng);
                    
                    if (driverMarker) {
                        driverMarker.setLatLng([driverLatLng.lat, driverLatLng.lng]);
                    } else {
                        driverMarker = L.marker([driverLatLng.lat, driverLatLng.lng], { icon: driverIcon }).addTo(map);
                    }
                    
                    lastKnownDriverPos = driverLatLng;
                    
                    if (isPickedUp) {
                        await drawDriverToDropoffRoute(driverLatLng);
                        const distanceToDropoff = getDistance(driverLatLng, dropoffCoords);
                        if (distanceToDropoff < 0.1 && !destinationAlertShown) {
                            destinationAlertShown = true;
                            Swal.fire({ icon: 'success', title: 'Destination Reached!', text: 'Complete the trip.', timer: 5000 });
                        }
                    } else {
                        await drawDriverToPickupRoute(driverLatLng);
                        const distanceToPickup = getDistance(driverLatLng, pickupCoords);
                        if (distanceToPickup < 0.1 && !arrivalAlertShown) {
                            arrivalAlertShown = true;
                            Swal.fire({ icon: 'info', title: 'You have arrived!', text: 'Click "Mark as Picked Up" to continue.', timer: 5000 });
                        }
                    }
                },
                function(error) {
                    const locationStatus = document.getElementById('locationStatus');
                    if (locationStatus) locationStatus.innerHTML = '📍 GPS Error';
                },
                watchOptions
            );
            
            heartbeatInterval = setInterval(() => {
                if (navigator.geolocation && isPageVisible) {
                    navigator.geolocation.getCurrentPosition(() => {}, () => {}, { enableHighAccuracy: true, timeout: 10000 });
                }
            }, 30000);
            
            passengerPollInterval = setInterval(() => { if (isPageVisible) pollPassengerLocation(); }, 4000);
            pollPassengerLocation();
        }
        
        function clearAllIntervals() {
            if (watchId) navigator.geolocation.clearWatch(watchId);
            if (heartbeatInterval) clearInterval(heartbeatInterval);
            if (locationSaveInterval) clearInterval(locationSaveInterval);
            if (passengerPollInterval) clearInterval(passengerPollInterval);
            if (countdownInterval) clearInterval(countdownInterval);
        }
        
        async function initMap() {
            // Get saved map view BEFORE creating map
            const savedView = getSavedMapView();
            
            // Set initial center based on saved view or default
            let initialCenter = defaultCenter;
            let initialZoom = 13;
            
            if (savedView) {
                initialCenter = [savedView.lat, savedView.lng];
                initialZoom = savedView.zoom;
                console.log('📌 Will restore map view after load:', savedView);
            } else if (pickupCoords) {
                initialCenter = [pickupCoords.lat, pickupCoords.lng];
                initialZoom = 14;
            }
            
            // Create map with saved view immediately - NO LOADING POPUP
            map = L.map('map').setView(initialCenter, initialZoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors', 
                maxZoom: 20
            }).addTo(map);
            
            // Get coordinates
            if (dbPickupLat && dbPickupLng) pickupCoords = { lat: dbPickupLat, lng: dbPickupLng };
            else pickupCoords = await geocodeAddress("<?= addslashes($trip['pickup_landmark'] ?? '') ?>");
            
            if (dbDropoffLat && dbDropoffLng) dropoffCoords = { lat: dbDropoffLat, lng: dbDropoffLng };
            else dropoffCoords = await geocodeAddress("<?= addslashes($trip['dropoff_landmark'] ?? '') ?>");
            
            if (!pickupCoords || !dropoffCoords) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Could not get location coordinates' });
                return;
            }
            
            // Add markers
            pickupMarker = L.marker([pickupCoords.lat, pickupCoords.lng], { icon: pickupIcon }).addTo(map);
            dropoffMarker = L.marker([dropoffCoords.lat, dropoffCoords.lng], { icon: dropoffIcon }).addTo(map);
            
            let driverStartPosition = (driverStartLat && driverStartLng) ? { lat: driverStartLat, lng: driverStartLng } : pickupCoords;
            driverMarker = L.marker([driverStartPosition.lat, driverStartPosition.lng], { icon: driverIcon }).addTo(map);
            lastKnownDriverPos = driverStartPosition;
            
            if (passengerStartLat && passengerStartLng) {
                passengerMarker = L.marker([passengerStartLat, passengerStartLng], { icon: passengerIcon }).addTo(map);
                lastKnownPassengerPos = { lat: passengerStartLat, lng: passengerStartLng };
            }
            
            // Draw initial route
            if (isPickedUp) {
                await drawDriverToDropoffRoute(driverStartPosition);
            } else {
                await drawDriverToPickupRoute(driverStartPosition);
            }
            
            // If no saved view was used, fit bounds
            if (!savedView) {
                const bounds = L.latLngBounds([pickupCoords, dropoffCoords]);
                if (driverStartPosition) bounds.extend([driverStartPosition.lat, driverStartPosition.lng]);
                if (lastKnownPassengerPos) bounds.extend([lastKnownPassengerPos.lat, lastKnownPassengerPos.lng]);
                map.fitBounds(bounds, { padding: [50, 50] });
            }
            
            isMapReady = true;
            enableAutoSaveMapView();
            
            // Apply panel state
            loadAndApplyPanelState();
            
            // Start tracking and countdown
            startTracking();
            startCountdown();
        }
        
        function centerOnDriver() {
            if (driverMarker) {
                map.setView(driverMarker.getLatLng(), 17);
                saveMapView();
            } else if (lastKnownDriverPos) {
                map.setView([lastKnownDriverPos.lat, lastKnownDriverPos.lng], 17);
                saveMapView();
            }
        }
        
        async function markAsPickedUp(bookingId) {
            const result = await Swal.fire({
                title: 'Passenger Picked Up?',
                text: "Confirm you have picked up the passenger.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                confirmButtonText: 'Yes, Picked Up'
            });
            
            if (result.isConfirmed) {
                Swal.fire({ title: 'Updating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                
                try {
                    const formData = new URLSearchParams();
                    formData.append('action', 'pickup');
                    formData.append('booking_id', bookingId);
                    
                    const response = await fetch('update_trip_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        isPickedUp = true;
                        currentStatus = 'PASSENGER PICKED UP';
                        document.getElementById('statusBadge').innerHTML = 'IN TRANSIT';
                        document.getElementById('statusBadge').className = 'status-badge status-picked_up';
                        
                        const actionDiv = document.querySelector('.action-buttons');
                        actionDiv.innerHTML = `
                            <button class="btn-complete" onclick="completeTrip(${bookingId})">✅ Complete Trip</button>
                            <button class="btn-cancel" onclick="cancelTrip(${bookingId})">✖ Cancel</button>
                        `;
                        
                        if (lastKnownDriverPos) {
                            await drawDriverToDropoffRoute(lastKnownDriverPos);
                        }
                        
                        Swal.fire({ icon: 'success', title: 'Passenger Picked Up!', timer: 2000, showConfirmButton: false });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to update status' });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Failed to connect to server.' });
                }
            }
        }
        
        async function completeTrip(bookingId) {
            const result = await Swal.fire({
                title: 'Complete Trip?',
                text: "Confirm passenger has reached destination.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Yes, Complete'
            });
            
            if (result.isConfirmed) {
                Swal.fire({ title: 'Completing trip...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                
                try {
                    const formData = new URLSearchParams();
                    formData.append('action', 'complete');
                    formData.append('booking_id', bookingId);
                    
                    const response = await fetch('update_trip_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        clearAllIntervals();
                        Swal.fire({ icon: 'success', title: 'Trip Completed!', timer: 1500, showConfirmButton: false })
                            .then(() => { window.location.href = 'driver_dashboard.php'; });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to complete trip' });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Failed to connect to server.' });
                }
            }
        }
        
        async function cancelTrip(bookingId) {
            const result = await Swal.fire({
                title: 'Cancel Trip?',
                text: "Are you sure you want to cancel this trip?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, Cancel'
            });
            
            if (result.isConfirmed) {
                Swal.fire({ title: 'Cancelling...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                
                try {
                    const formData = new URLSearchParams();
                    formData.append('action', 'cancel');
                    formData.append('booking_id', bookingId);
                    
                    const response = await fetch('update_trip_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        clearAllIntervals();
                        Swal.fire({ icon: 'success', title: 'Cancelled', timer: 1500, showConfirmButton: false })
                            .then(() => { window.location.href = 'driver_dashboard.php'; });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to cancel trip' });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Failed to connect to server.' });
                }
            }
        }
        
        function goBack() {
            clearAllIntervals();
            window.location.href = 'driver_dashboard.php';
        }
        
        document.addEventListener('visibilitychange', () => { isPageVisible = !document.hidden; });
        window.addEventListener('online', () => document.getElementById('connectionStatus').className = 'connection-status online');
        window.addEventListener('offline', () => document.getElementById('connectionStatus').className = 'connection-status offline');
        window.addEventListener('beforeunload', () => {
            clearAllIntervals();
            if (map && isMapReady) saveMapView();
            savePanelState();
        });
        
        document.addEventListener('DOMContentLoaded', initMap);
    </script>

</body>
</html>