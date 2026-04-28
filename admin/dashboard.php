<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'admin') header("Location: ../index.php");

// ============================================
// FORCE UPDATE DRIVERS TO ONLINE STATUS
// ============================================
$conn->query("
    UPDATE users 
    SET status = 'online' 
    WHERE role = 'driver' 
    AND lat IS NOT NULL 
    AND lng IS NOT NULL
");

// ============================================
// STATISTICS QUERIES
// ============================================
$total_drivers = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM users 
    WHERE role = 'driver'
")->fetch_assoc()['cnt'];

$online_drivers = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM users 
    WHERE role = 'driver' 
    AND status = 'online'
    AND lat IS NOT NULL 
    AND lng IS NOT NULL
")->fetch_assoc()['cnt'];

$monthly_revenue = $conn->query("
    SELECT SUM(fare_amount) as total 
    FROM bookings 
    WHERE status = 'COMPLETED' 
    AND MONTH(created_at) = MONTH(CURDATE())
    AND YEAR(created_at) = YEAR(CURDATE())
")->fetch_assoc()['total'] ?? 0;

// ============================================
// GET TODAS FOR CURRENT ADMIN
// ============================================
$admin_id = $_SESSION['user_id'];
$toda_list = $conn->query("
    SELECT * FROM todas 
    WHERE user_id = $admin_id AND role = 'admin'
    ORDER BY created_at DESC
");

// Get all toda_ids for this admin (para magamit sa pagkuha ng drivers)
$admin_toda_ids = [];
$toda_ids_temp = $conn->query("
    SELECT id FROM todas 
    WHERE user_id = $admin_id AND role = 'admin'
");
while($row = $toda_ids_temp->fetch_assoc()) {
    $admin_toda_ids[] = $row['id'];
}

// ============================================
// GET ALL DRIVERS (for dropdown in assignment)
// ============================================
$all_drivers_list = $conn->query("
    SELECT id, name, email, contact, driver_toda 
    FROM users 
    WHERE role = 'driver'
    ORDER BY name
");

// ============================================
// GET DRIVERS ASSIGNED TO CURRENT ADMIN'S TODAS ONLY
// ============================================
if (!empty($admin_toda_ids)) {
    $toda_placeholders = implode(',', array_fill(0, count($admin_toda_ids), '?'));
    $toda_types = str_repeat('i', count($admin_toda_ids));
    
    $assigned_drivers_query = $conn->prepare("
        SELECT DISTINCT
            u.id, 
            u.name, 
            u.email, 
            u.contact, 
            u.lat, 
            u.lng, 
            u.last_location_update,
            u.status as user_status,
            u.driver_toda,
            d.vehicle_type,
            d.vehicle_plate,
            d.vehicle_color,
            d.rating,
            d.is_online,
            b.id as booking_id,
            b.booking_code,
            b.pickup_landmark,
            b.dropoff_landmark,
            b.pickup_lat,
            b.pickup_lng,
            b.dropoff_lat,
            b.dropoff_lng,
            b.status as booking_status,
            p.name as passenger_name,
            TIMESTAMPDIFF(MINUTE, u.last_location_update, NOW()) as minutes_ago
        FROM users u
        LEFT JOIN drivers d ON u.id = d.user_id
        LEFT JOIN bookings b ON (b.driver_id = u.id AND b.status IN ('ASSIGNED','PASSENGER PICKED UP','IN TRANSIT','ACCEPTED'))
        LEFT JOIN users p ON b.passenger_id = p.id
        INNER JOIN toda_drivers td ON u.id = td.driver_id
        WHERE u.role = 'driver' 
        AND td.toda_id IN ($toda_placeholders)
        ORDER BY 
            CASE 
                WHEN u.lat IS NOT NULL AND u.lng IS NOT NULL AND u.status = 'online' THEN 0
                ELSE 1 
            END,
            u.last_location_update DESC
    ");
    
    $assigned_drivers_query->bind_param($toda_types, ...$admin_toda_ids);
    $assigned_drivers_query->execute();
    $all_drivers = $assigned_drivers_query->get_result();
} else {
    // No TODA created yet, return empty result
    $all_drivers = $conn->query("SELECT * FROM users WHERE 1=0");
}

// ============================================
// BUILD JSON FOR MAP (ONLINE DRIVERS ONLY)
// ============================================
$drivers_json = [];
if ($all_drivers && $all_drivers->num_rows > 0) {
    $all_drivers->data_seek(0);
    while($driver = $all_drivers->fetch_assoc()) {
        $has_location = ($driver['lat'] && $driver['lng']);
        $is_online = ($has_location && strtolower(trim($driver['user_status'])) === 'online');
        
        if ($is_online) {
            $drivers_json[] = [
                'id' => $driver['id'],
                'name' => $driver['name'],
                'lat' => floatval($driver['lat']),
                'lng' => floatval($driver['lng']),
                'vehicle_type' => $driver['vehicle_type'],
                'vehicle_plate' => $driver['vehicle_plate'],
                'rating' => floatval($driver['rating']),
                'contact' => $driver['contact'],
                'status' => strtolower(trim($driver['user_status'])),
                'driver_toda' => $driver['driver_toda'],
                'booking_code' => $driver['booking_code'],
                'booking_status' => $driver['booking_status'],
                'pickup_lat' => $driver['pickup_lat'] ? floatval($driver['pickup_lat']) : null,
                'pickup_lng' => $driver['pickup_lng'] ? floatval($driver['pickup_lng']) : null,
                'dropoff_lat' => $driver['dropoff_lat'] ? floatval($driver['dropoff_lat']) : null,
                'dropoff_lng' => $driver['dropoff_lng'] ? floatval($driver['dropoff_lng']) : null,
                'passenger_name' => $driver['passenger_name'],
                'last_update' => $driver['last_location_update'] ? date('h:i A', strtotime($driver['last_location_update'])) : 'Never',
                'minutes_ago' => $driver['minutes_ago']
            ];
        }
    }
}

// Reset pointer for display
if ($all_drivers && $all_drivers->num_rows > 0) {
    $all_drivers->data_seek(0);
}
?>

<?php include '../includes/header.php'; ?>

<style>
    @font-face {
        font-family: 'NaruMonoDemo';
        src: url('../assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    * {
        font-family: 'NaruMonoDemo', monospace !important;
    }

    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
    }

    .dashboard-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    .main-card {
        background: white;
        border-radius: 28px;
        overflow: hidden;
        box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1);
        animation: slideUp 0.5s ease;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .card-header::before {
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

    .card-header h2 {
        margin: 0;
        font-size: 1.8rem;
        font-weight: bold;
        position: relative;
        z-index: 1;
    }

    .card-header p {
        margin: 0.5rem 0 0;
        opacity: 0.9;
        position: relative;
        z-index: 1;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        padding: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 1rem;
        text-align: center;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        position: relative;
        overflow: hidden;
        border: 1px solid #f0f0f0;
        cursor: pointer;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #667eea, #764ba2);
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }

    .stat-icon {
        width: 45px;
        height: 45px;
        margin: 0 auto 0.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        background: #f0f0f0;
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: bold;
        margin: 0.25rem 0;
        color: #2d3748;
    }

    .stat-label {
        font-size: 0.7rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .map-section {
        padding: 0 2rem 2rem 2rem;
    }

    .section-title {
        font-size: 1.2rem;
        font-weight: bold;
        margin-bottom: 1rem;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .drivers-map {
        width: 100%;
        height: 550px;
        background: #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 1rem;
        z-index: 1;
        position: relative;
        border: 2px solid #e2e8f0;
    }

    .map-controls {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .map-btn {
        padding: 0.5rem 1rem;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.8rem;
        transition: all 0.3s ease;
    }

    .map-btn:hover {
        background: #5a67d8;
        transform: translateY(-1px);
    }

    .map-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-top: 0.5rem;
        padding: 0.5rem;
        background: #f8fafc;
        border-radius: 12px;
    }

    .online-count {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .online-dot {
        width: 10px;
        height: 10px;
        background: #10b981;
        border-radius: 50%;
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .legend {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.7rem;
    }

    .legend-color {
        width: 20px;
        height: 20px;
        border-radius: 50%;
    }

    /* TODA Section Styles */
    .toda-section {
        padding: 0 2rem 2rem 2rem;
    }

    .toda-container {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-radius: 20px;
        padding: 1.5rem;
        margin-top: 1rem;
    }

    .toda-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .btn-toda {
        background: #f59e0b;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-toda:hover {
        background: #d97706;
        transform: translateY(-2px);
    }

    .toda-card {
        background: white;
        border-radius: 16px;
        padding: 1rem;
        margin-bottom: 1rem;
        border: 1px solid #fde68a;
        transition: all 0.3s ease;
    }

    .toda-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .toda-title {
        margin: 0;
        color: #d97706;
        font-size: 1.2rem;
    }

    .assign-driver-btn {
        background: #10b981;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.8rem;
        transition: all 0.3s ease;
    }

    .assign-driver-btn:hover {
        background: #059669;
    }

    .remove-driver-btn {
        background: #ef4444;
        color: white;
        border: none;
        padding: 0.3rem 0.8rem;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.7rem;
        transition: all 0.3s ease;
    }

    .remove-driver-btn:hover {
        background: #dc2626;
    }

    .driver-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem;
        background: #fef3c7;
        margin-bottom: 0.5rem;
        border-radius: 8px;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: white;
        border-radius: 24px;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
        animation: slideUp 0.3s ease;
    }

    .modal-header {
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 1rem;
        color: #2d3748;
    }

    .modal input, .modal select {
        width: 100%;
        padding: 0.75rem;
        margin: 0.5rem 0 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        font-size: 0.9rem;
    }

    .modal-buttons {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }

    .modal-btn {
        flex: 1;
        padding: 0.75rem;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-size: 0.9rem;
    }

    .modal-btn-save {
        background: #667eea;
        color: white;
    }

    .modal-btn-cancel {
        background: #e2e8f0;
        color: #4a5568;
    }

    .drivers-list-section {
        padding: 0 2rem 2rem 2rem;
    }

    .drivers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .driver-card {
        background: white;
        border-radius: 12px;
        padding: 1rem;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
    }

    .driver-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #667eea;
    }

    .driver-card.online {
        border-left: 4px solid #10b981;
        background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
    }

    .driver-card.offline {
        border-left: 4px solid #ef4444;
        opacity: 0.6;
    }

    .driver-card-header {
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

    .driver-info {
        flex: 1;
    }

    .driver-name {
        font-weight: bold;
        font-size: 1rem;
        margin-bottom: 0.25rem;
        color: #2d3748;
    }

    .driver-status {
        font-size: 0.7rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .status-online {
        color: #10b981;
    }

    .status-offline {
        color: #ef4444;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }

    .status-dot.online {
        background: #10b981;
        animation: pulse 1.5s infinite;
    }

    .status-dot.offline {
        background: #ef4444;
    }

    .driver-location {
        font-size: 0.7rem;
        color: #6b7280;
        margin-bottom: 0.5rem;
        font-family: monospace;
    }

    .driver-toda-badge {
        display: inline-block;
        background: #fef3c7;
        color: #d97706;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-size: 0.6rem;
        font-weight: bold;
        margin-top: 0.3rem;
    }

    .vehicle-details {
        font-size: 0.7rem;
        color: #6b7280;
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: 0.5rem;
    }

    .vehicle-badge {
        background: #e0e7ff;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        color: #4338ca;
    }

    .last-update {
        font-size: 0.6rem;
        color: #9ca3af;
        margin-top: 0.5rem;
        text-align: right;
    }

    .status-badge {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-size: 0.6rem;
        font-weight: bold;
    }

    .status-online-badge {
        background: #d1fae5;
        color: #065f46;
    }

    .status-offline-badge {
        background: #fee2e2;
        color: #991b1b;
    }

    .no-data-message {
        text-align: center;
        padding: 2rem;
        background: white;
        border-radius: 16px;
        color: #6b7280;
    }

    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            padding: 1rem;
        }
        
        .map-section, .drivers-list-section, .toda-section {
            padding: 0 1rem 1rem 1rem;
        }
        
        .drivers-map {
            height: 400px;
        }
        
        .drivers-grid {
            grid-template-columns: 1fr;
        }
        
        .map-info {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<!-- Leaflet CSS and JavaScript -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="dashboard-container">
    <div class="main-card">
        <div class="card-header">
            <h2>📍 Driver Tracking Dashboard</h2>
            <p>Real-time location tracking for drivers in your TODA</p>
        </div>

        <!-- Stats Grid - 4 columns -->
        <div class="stats-grid">
        

           

            <div class="stat-card" onclick="openTodaModal()">
                <div class="stat-icon">🚖</div>
                <div class="stat-value">TODA</div>
                <div class="stat-label">Manage TODA</div>
            </div>
        </div>

        <!-- TODA Management Section -->
        <div class="toda-section">
            <div class="section-title">
                <span>🚖</span> Manage TODA
            </div>
            <div class="toda-container">
                <div class="toda-header">
                    <strong>📋 Registered TODAs</strong>
                    <button class="btn-toda" onclick="openTodaModal()">
                        ➕ Create New TODA
                    </button>
                </div>
                <?php if ($toda_list && $toda_list->num_rows > 0): ?>
                    <?php while($toda = $toda_list->fetch_assoc()): 
                        // Get drivers for this TODA from toda_drivers table
                        $drivers_sql = $conn->prepare("
                            SELECT td.driver_id, td.driver_name, u.email, u.contact 
                            FROM toda_drivers td
                            JOIN users u ON td.driver_id = u.id
                            WHERE td.toda_id = ?
                        ");
                        $drivers_sql->bind_param("i", $toda['id']);
                        $drivers_sql->execute();
                        $toda_drivers = $drivers_sql->get_result();
                    ?>
                    <div class="toda-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <h3 class="toda-title">🚖 <?= htmlspecialchars($toda['toda_name']) ?></h3>
                                <small style="color: #999;">Created: <?= date('M d, Y', strtotime($toda['created_at'])) ?></small>
                            </div>
                            <button class="assign-driver-btn" onclick="addDriverToToda(<?= $toda['id'] ?>, '<?= htmlspecialchars($toda['toda_name']) ?>')">
                                ➕ Add Driver
                            </button>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <strong>👨‍✈️ Assigned Drivers:</strong>
                            <?php if ($toda_drivers && $toda_drivers->num_rows > 0): ?>
                                <div style="margin-top: 0.5rem;">
                                    <?php while($driver = $toda_drivers->fetch_assoc()): ?>
                                    <div class="driver-item">
                                        <div>
                                            <strong><?= htmlspecialchars($driver['driver_name']) ?></strong><br>
                                            <small><?= htmlspecialchars($driver['email'] ?? 'N/A') ?> | 📞 <?= htmlspecialchars($driver['contact'] ?? 'N/A') ?></small>
                                        </div>
                                        <button class="remove-driver-btn" onclick="removeDriverFromToda(<?= $toda['id'] ?>, <?= $driver['driver_id'] ?>, '<?= htmlspecialchars($driver['driver_name']) ?>')">
                                            Remove
                                        </button>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: #999; margin-top: 0.5rem;">No drivers assigned yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php 
                        $drivers_sql->close();
                    endwhile; ?>
                <?php else: ?>
                <div class="no-data-message">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🚖</div>
                    <p>No TODA created yet.</p>
                    <p style="font-size: 0.8rem;">Click "Create New TODA" to get started.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Live Map Section -->
        <div class="map-section">
            <div class="section-title">
                <span>🗺️</span> Live Driver Locations
            </div>
            <div class="map-controls">
                <button class="map-btn" onclick="centerMapOnAllDrivers()">📍 Show All Drivers</button>
                <button class="map-btn" onclick="refreshDriverLocations()">🔄 Refresh Map</button>
                <button class="map-btn" onclick="toggleRoutes()">🚦 Toggle Routes</button>
                <button class="map-btn" onclick="locateMe()">🎯 My Location</button>
            </div>
            <div id="driversMap" class="drivers-map"></div>
            <div class="map-info">
                <div class="online-count">
                    <span class="online-dot"></span>
                    <span><strong id="driverCount"><?= count($drivers_json) ?></strong> drivers online from your TODA</span>
                </div>
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #667eea;"></div>
                        <span>Available</span>
                    </div>
                  
                </div>
                <div class="last-auto-refresh">
                    Auto-refresh every 15 seconds
                </div>
            </div>
        </div>

        <!-- All Drivers List Section - Only drivers assigned to current admin's TODAs -->
        <div class="drivers-list-section" id="drivers-list">
            <div class="section-title">
                <span>📋</span> My Drivers List (Assigned to my TODA)
            </div>
            <div class="drivers-grid">
                <?php 
                if ($all_drivers && $all_drivers->num_rows > 0):
                    $has_drivers = false;
                    while($driver = $all_drivers->fetch_assoc()): 
                        $has_drivers = true;
                        $has_location = ($driver['lat'] && $driver['lng']);
                        $is_online = ($has_location && strtolower(trim($driver['user_status'])) === 'online');
                        $status_class = $is_online ? 'online' : 'offline';
                        $status_text = $is_online ? 'Online' : 'Offline';
                        $status_badge_class = $is_online ? 'status-online-badge' : 'status-offline-badge';
                ?>
                <div class="driver-card <?= $status_class ?>" 
                     onclick="centerOnDriver(<?= $driver['lat'] ? 'true' : 'false' ?>, <?= $driver['lat'] ?: 'null' ?>, <?= $driver['lng'] ?: 'null' ?>, '<?= addslashes($driver['name']) ?>')">
                    <div class="driver-card-header">
                        <div class="driver-avatar">
                            <?= $is_online ? '🚗' : '🚙' ?>
                        </div>
                        <div class="driver-info">
                            <div class="driver-name"><?= htmlspecialchars($driver['name']) ?></div>
                            <div class="driver-status">
                                <span class="status-dot <?= $status_class ?>"></span>
                                <span class="status-<?= $status_class ?>"><?= $status_text ?></span>
                                <span class="status-badge <?= $status_badge_class ?>" style="margin-left: 0.5rem;">
                                    <?= strtoupper(htmlspecialchars($driver['user_status'] ?: 'OFFLINE')) ?>
                                </span>
                            </div>
                            <?php if($driver['driver_toda']): ?>
                            <div class="driver-toda-badge">
                                🚖 TODA: <?= htmlspecialchars($driver['driver_toda']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if($has_location): ?>
                    <div class="driver-location">
                        📍 Lat: <?= number_format($driver['lat'], 6) ?>, Lng: <?= number_format($driver['lng'], 6) ?>
                    </div>
                    <?php else: ?>
                    <div class="driver-location">
                        📍 Location not shared yet
                    </div>
                    <?php endif; ?>
                    
                    <?php if($driver['booking_id']): ?>
                    <div class="driver-booking">
                        <span class="booking-code">#<?= htmlspecialchars($driver['booking_code']) ?></span>
                        <span> - <?= htmlspecialchars($driver['booking_status']) ?></span>
                        <?php if($driver['passenger_name']): ?>
                        <br>👤 <?= htmlspecialchars($driver['passenger_name']) ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="vehicle-details">
                        <?php if($driver['vehicle_type']): ?>
                        <span class="vehicle-badge">🚲 <?= htmlspecialchars($driver['vehicle_type']) ?></span>
                        <?php endif; ?>
                        <?php if($driver['vehicle_plate']): ?>
                        <span class="vehicle-badge">🔢 <?= htmlspecialchars($driver['vehicle_plate']) ?></span>
                        <?php endif; ?>
                        <?php if($driver['rating']): ?>
                        <span class="vehicle-badge">⭐ <?= number_format($driver['rating'], 1) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($driver['contact']): ?>
                    <div style="margin-top: 0.5rem; font-size: 0.7rem; color: #667eea;">
                        📞 <?= htmlspecialchars($driver['contact']) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($driver['last_location_update']): ?>
                    <div class="last-update">
                        Last update: <?= date('h:i A', strtotime($driver['last_location_update'])) ?>
                        <?php if($driver['minutes_ago'] !== null && $driver['minutes_ago'] > 0): ?>
                        (<?= $driver['minutes_ago'] ?> min ago)
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php 
                    endwhile;
                else: 
                ?>
                <div class="no-data-message">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🚫</div>
                    <p>No drivers assigned to your TODA yet.</p>
                    <p style="font-size: 0.8rem;">Please add drivers to your TODA to see them here.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Creating TODA -->
<div id="todaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">Create New TODA</div>
        <form id="todaForm">
            <label>TODA Name:</label>
            <input type="text" id="toda_name" placeholder="e.g., BAGSIT, SCAT, CCT" required>
            <small style="color: #666; display: block; margin-top: -0.5rem; margin-bottom: 1rem;">If TODA name already exists, you can add drivers to it.</small>
            <div class="modal-buttons">
                <button type="submit" class="modal-btn modal-btn-save">Save</button>
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeTodaModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal for Adding Driver -->
<div id="addDriverModal" class="modal">
    <div class="modal-content">
        <div class="modal-header" id="addDriverModalTitle">Add Driver to TODA</div>
        <form id="addDriverForm">
            <input type="hidden" id="add_toda_id">
            <label>Select Driver:</label>
            <select id="add_driver_id" required>
                <option value="">-- Select a driver --</option>
                <?php 
                $all_drivers_list->data_seek(0);
                while($driver = $all_drivers_list->fetch_assoc()): 
                ?>
                <option value="<?= $driver['id'] ?>" data-toda="<?= htmlspecialchars($driver['driver_toda'] ?? '') ?>">
                    <?= htmlspecialchars($driver['name']) ?> (<?= htmlspecialchars($driver['email']) ?>)
                    <?= $driver['driver_toda'] ? ' - Currently in ' . $driver['driver_toda'] : '' ?>
                </option>
                <?php endwhile; ?>
            </select>
            <div class="modal-buttons">
                <button type="submit" class="modal-btn modal-btn-save">Add Driver</button>
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeAddDriverModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Map variables
let map;
let driverMarkers = [];
let driverRoutes = [];
let showRoutes = true;

// Driver data from PHP
const driversData = <?php echo json_encode($drivers_json); ?>;

// TODA Modal Functions
function openTodaModal() {
    document.getElementById('todaModal').style.display = 'flex';
}

function closeTodaModal() {
    document.getElementById('todaModal').style.display = 'none';
    document.getElementById('toda_name').value = '';
}

function closeAddDriverModal() {
    document.getElementById('addDriverModal').style.display = 'none';
    document.getElementById('add_driver_id').value = '';
}

// Submit TODA form
document.getElementById('todaForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const todaName = document.getElementById('toda_name').value;
    
    if (!todaName) {
        alert('Please enter a TODA name');
        return;
    }
    
    const formData = new FormData();
    formData.append('toda_name', todaName);
    formData.append('action', 'create');
    
    try {
        const response = await fetch('save_toda.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            if (result.is_existing) {
                alert('TODA already exists! You can now add drivers to it.');
            } else {
                alert('TODA created successfully!');
            }
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error saving TODA: ' + error.message);
    }
});

// Add driver to TODA
function addDriverToToda(todaId, todaName) {
    document.getElementById('addDriverModalTitle').innerHTML = `Add Driver to ${todaName}`;
    document.getElementById('add_toda_id').value = todaId;
    document.getElementById('addDriverModal').style.display = 'flex';
}

document.getElementById('addDriverForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const todaId = document.getElementById('add_toda_id').value;
    const driverId = document.getElementById('add_driver_id').value;
    const driverSelect = document.getElementById('add_driver_id');
    const selectedOption = driverSelect.options[driverSelect.selectedIndex];
    const currentToda = selectedOption.getAttribute('data-toda');
    
    if (!driverId) {
        alert('Please select a driver');
        return;
    }
    
    // Check if driver already has a TODA
    if (currentToda && currentToda !== '') {
        if (!confirm(`This driver is already assigned to "${currentToda}". Do you want to move them to this TODA?`)) {
            return;
        }
    }
    
    const formData = new FormData();
    formData.append('toda_id', todaId);
    formData.append('driver_id', driverId);
    formData.append('action', 'add_driver');
    
    try {
        const response = await fetch('/save_toda.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error adding driver: ' + error.message);
    }
});

// Remove driver from TODA
function removeDriverFromToda(todaId, driverId, driverName) {
    if (confirm(`Are you sure you want to remove ${driverName} from this TODA?`)) {
        const formData = new FormData();
        formData.append('toda_id', todaId);
        formData.append('driver_id', driverId);
        formData.append('action', 'remove_driver');
        
        fetch('/save_toda.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        })
        .catch(error => {
            alert('Error removing driver: ' + error.message);
        });
    }
}

// Initialize map
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing driver tracking map...');
    console.log('Online drivers to display:', driversData.length);
    
    const defaultCenter = [14.5995, 120.9842];
    
    map = L.map('driversMap').setView(defaultCenter, 12);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    L.control.scale().addTo(map);
    
    updateDriverMarkers();
    startAutoRefresh();
});

function getDriverIcon(driver) {
    const bookingStatus = driver.booking_status ? driver.booking_status.toLowerCase() : 'available';
    let color = '#667eea';
    let statusText = 'Available';
    let icon = '🚗';
    
    if (bookingStatus === 'assigned') {
        color = '#f59e0b';
        statusText = 'Assigned';
        icon = '🚕';
    } else if (bookingStatus === 'passenger picked up') {
        color = '#10b981';
        statusText = 'Picked Up';
        icon = '🚘';
    } else if (bookingStatus === 'in transit') {
        color = '#3b82f6';
        statusText = 'In Transit';
        icon = '🚐';
    }
    
    return L.divIcon({
        html: `<div style="background: ${color}; border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3); position: relative;">
                    <span style="font-size: 24px;">${icon}</span>
                    <div style="position: absolute; bottom: -22px; white-space: nowrap; background: ${color}; color: white; padding: 2px 8px; border-radius: 12px; font-size: 9px; font-weight: bold;">
                        ${statusText}
                    </div>
                </div>`,
        iconSize: [44, 44],
        popupAnchor: [0, -22],
        className: 'driver-marker'
    });
}

function updateDriverMarkers() {
    driverMarkers.forEach(marker => map.removeLayer(marker));
    driverMarkers = [];
    
    driverRoutes.forEach(route => map.removeLayer(route));
    driverRoutes = [];
    
    if (driversData.length === 0) {
        L.popup()
            .setLatLng([14.5995, 120.9842])
            .setContent('<strong>⚠️ No Online Drivers</strong><br>There are no online drivers from your TODA at the moment.')
            .openOn(map);
        return;
    }
    
    driversData.forEach(driver => {
        if (driver.lat && driver.lng) {
            const marker = L.marker([driver.lat, driver.lng], {
                icon: getDriverIcon(driver)
            })
            .bindPopup(`
                <div style="min-width: 220px;">
                    <strong style="font-size: 14px;">🚗 ${driver.name}</strong><br>
                    <hr style="margin: 5px 0;">
                    ${driver.driver_toda ? `🚖 TODA: ${driver.driver_toda}<br>` : ''}
                    ${driver.vehicle_type ? `🚲 Type: ${driver.vehicle_type}<br>` : ''}
                    ${driver.vehicle_plate ? `🔢 Plate: ${driver.vehicle_plate}<br>` : ''}
                    ⭐ Rating: ${driver.rating || 'N/A'}<br>
                    📞 Contact: ${driver.contact || 'N/A'}<br>
                    ${driver.booking_code ? `<hr style="margin: 5px 0;"><strong>📋 Active Booking:</strong><br>#${driver.booking_code}<br>Status: ${driver.booking_status}<br>` : ''}
                    <hr style="margin: 5px 0;">
                    <small>📍 Last update: ${driver.last_update || 'Just now'}</small>
                </div>
            `)
            .addTo(map);
            
            driverMarkers.push(marker);
        }
    });
    
    if (driverMarkers.length > 0) {
        const bounds = [];
        driverMarkers.forEach(marker => bounds.push(marker.getLatLng()));
        map.fitBounds(bounds, { padding: [50, 50] });
    }
    
    document.getElementById('driverCount').innerText = driversData.length;
}

function centerOnDriver(hasLocation, lat, lng, name) {
    if (hasLocation && lat && lng) {
        map.flyTo([lat, lng], 16, { duration: 1 });
        driverMarkers.forEach(marker => {
            const markerLatLng = marker.getLatLng();
            if (Math.abs(markerLatLng.lat - lat) < 0.0001 && Math.abs(markerLatLng.lng - lng) < 0.0001) {
                marker.openPopup();
            }
        });
    } else {
        alert(`${name} is currently offline and not sharing location.`);
    }
}

function centerMapOnAllDrivers() {
    if (driverMarkers.length > 0) {
        const bounds = [];
        driverMarkers.forEach(marker => bounds.push(marker.getLatLng()));
        map.fitBounds(bounds, { padding: [50, 50] });
    } else {
        alert('No online drivers from your TODA to display.');
    }
}

function toggleRoutes() {
    showRoutes = !showRoutes;
    updateDriverMarkers();
    event.target.style.background = showRoutes ? '#667eea' : '#6b7280';
}

function locateMe() {
    if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(function(position) {
            map.flyTo([position.coords.latitude, position.coords.longitude], 15);
            L.circle([position.coords.latitude, position.coords.longitude], {
                color: '#ef4444',
                fillColor: '#ef4444',
                fillOpacity: 0.2,
                radius: 100
            }).addTo(map).bindPopup("You are here").openPopup();
        });
    } else {
        alert("Geolocation is not supported.");
    }
}

function refreshDriverLocations() {
    location.reload();
}

let refreshInterval;
function startAutoRefresh() {
    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(() => location.reload(), 15000);
}

window.addEventListener('beforeunload', () => {
    if (refreshInterval) clearInterval(refreshInterval);
});

window.onclick = function(event) {
    const todaModal = document.getElementById('todaModal');
    const addModal = document.getElementById('addDriverModal');
    if (event.target === todaModal) closeTodaModal();
    if (event.target === addModal) closeAddDriverModal();
}
</script>

<?php include '../includes/footer.php'; ?>