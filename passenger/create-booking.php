<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'passenger') {
    header("Location: ../index.php");
    exit;
}

// Add GPS columns to users table if not exists
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS lat DECIMAL(10, 8) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS lng DECIMAL(11, 8) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_location_update DATETIME DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('passenger', 'driver', 'admin') DEFAULT 'passenger'");

// Get all pickup routes with toda info
$allPickupRoutes = [];
$routesQuery = $conn->query("
    SELECT tr.*, t.id as toda_id, t.toda_name 
    FROM todas_routes tr
    JOIN todas t ON tr.toda_id = t.id
    WHERE tr.is_active = 1
    ORDER BY t.toda_name ASC, tr.route_name ASC
");
while ($row = $routesQuery->fetch_assoc()) {
    $allPickupRoutes[] = $row;
}

// Get landmarks for dropoff (from locations table)
$dropoffLandmarks = [];
$locQuery = $conn->query("SELECT id, name, lat, lon FROM locations ORDER BY name ASC");
while ($row = $locQuery->fetch_assoc()) {
    $dropoffLandmarks[] = $row;
}

// Get all TODA groups for driver info
$todaGroups = [];
$todaQuery = $conn->query("
    SELECT DISTINCT t.*, 
           COUNT(DISTINCT td.id) as driver_count
    FROM todas t
    LEFT JOIN toda_drivers td ON t.id = td.toda_id
    GROUP BY t.id
    ORDER BY t.toda_name ASC
");

while ($row = $todaQuery->fetch_assoc()) {
    // Get drivers with their real-time GPS from users table
    $driversQuery = $conn->prepare("
        SELECT td.driver_name, u.id as user_id, u.name, u.lat, u.lng, u.last_location_update
        FROM toda_drivers td
        LEFT JOIN users u ON td.driver_id = u.id
        WHERE td.toda_id = ? AND u.role = 'driver'
        ORDER BY u.last_location_update DESC
    ");
    $driversQuery->bind_param("i", $row['id']);
    $driversQuery->execute();
    $driversResult = $driversQuery->get_result();
    
    $drivers_list = [];
    $driver_locations = [];
    while ($driver = $driversResult->fetch_assoc()) {
        $driverName = $driver['driver_name'] ?: $driver['name'];
        if ($driverName) {
            $drivers_list[] = [
                'name' => $driverName,
                'lat' => $driver['lat'],
                'lng' => $driver['lng'],
                'last_update' => $driver['last_location_update']
            ];
            if ($driver['lat'] && $driver['lng']) {
                $driver_locations[] = [
                    'lat' => $driver['lat'],
                    'lng' => $driver['lng'],
                    'name' => $driverName
                ];
            }
        }
    }
    $driversQuery->close();
    
    // Get pickup routes for this TODA
    $routesQuery = $conn->prepare("
        SELECT * FROM todas_routes 
        WHERE toda_id = ? AND is_active = 1
        ORDER BY route_name ASC
    ");
    $routesQuery->bind_param("i", $row['id']);
    $routesQuery->execute();
    $routesResult = $routesQuery->get_result();
    
    $pickup_routes = [];
    while ($route = $routesResult->fetch_assoc()) {
        $pickup_routes[] = [
            'id' => $route['id'],
            'route_name' => $route['route_name'],
            'lat' => $route['lat'],
            'lng' => $route['lng']
        ];
    }
    $routesQuery->close();
    
    $row['pickup_routes'] = $pickup_routes;
    $row['drivers_list'] = $drivers_list;
    $row['driver_locations'] = $driver_locations;
    $todaGroups[] = $row;
}

// Fare calculation function
function calculateFare($total_pax, $distance_km) {
    if ($total_pax <= 2) {
        $base_fare = 30;
    } else {
        $base_fare = $total_pax * 15;
    }
    
    $rounded_distance = ceil($distance_km);
    $pickup_fee = $rounded_distance * 10;
    $total_fare = $base_fare + $pickup_fee;
    
    $required_tricycles = ceil($total_pax / 4);
    
    return [
        'base_fare' => $base_fare,
        'pickup_fee' => $pickup_fee,
        'distance_km' => $distance_km,
        'rounded_distance' => $rounded_distance,
        'total_fare' => $total_fare,
        'required_tricycles' => $required_tricycles
    ];
}

// HIGHWAY RESTRICTION FUNCTION
function hasHighwayRestriction($pickup_lat, $pickup_lng, $dropoff_lat, $dropoff_lng) {
    $restricted_highways = [
        'nlex', 'north luzon expressway', 'north luzon', 
        'slex', 'south luzon expressway', 'south luzon',
        'skyway', 'skyway stage', 'metro manila skyway',
        'edsa', 'epifanio delos santos', 'edsa highway',
        'commonwealth', 'commonwealth avenue',
        'c5', 'c-5', 'c5 road', 'circumferential road 5',
        'expressway', 'toll road', 'turnpike',
        'macapagal', 'macapagal highway', 'roxas blvd',
        'quezon avenue', 'tandang sora', 'katipunan',
        'a bonifacio', 'bonifacio drive', 'buendia',
        'osmena highway', 'osmeña highway', 'president serrano',
        'quirino highway', 'quirino', 'manila east road',
        'marcos highway', 'marcos hi-way', 'ortigas extension'
    ];
    
    try {
        $url = "https://router.project-osrm.org/route/v1/driving/{$pickup_lng},{$pickup_lat};{$dropoff_lng},{$dropoff_lat}?overview=full&geometries=geojson";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['routes'][0]['legs'][0]['steps'])) {
            return false;
        }
        
        foreach ($data['routes'][0]['legs'][0]['steps'] as $step) {
            $name = strtolower($step['name'] ?? '');
            $ref = strtolower($step['ref'] ?? '');
            $destination = strtolower($step['destinations'] ?? '');
            $mode = strtolower($step['mode'] ?? '');
            
            if ($mode === 'driving') {
                foreach ($restricted_highways as $highway) {
                    if (strpos($name, $highway) !== false || 
                        strpos($ref, $highway) !== false || 
                        strpos($destination, $highway) !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Highway check error: " . $e->getMessage());
        return false;
    }
}

function hasLongDistanceRestriction($distance_km) {
    $max_allowed_distance = 30;
    return $distance_km > $max_allowed_distance;
}

// Add payment columns if not exists
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending'");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT NULL");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100) DEFAULT NULL");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_date DATETIME DEFAULT NULL");

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toda_id         = $_POST['toda_id'];
    $pickup_route_id = $_POST['pickup_route'];
    $dropoff_id      = $_POST['dropoff'];
    $notes           = trim($_POST['notes'] ?? '');
    $pid             = $_SESSION['user_id'];
    $total_pax       = intval($_POST['total_pax'] ?? 1);
    $payment_method  = $_POST['payment_method'] ?? 'cash';
    $preferred_time  = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    $passenger_lat = $_POST['passenger_lat'] ?? null;
    $passenger_lng = $_POST['passenger_lng'] ?? null;
    
    if ($passenger_lat && $passenger_lng) {
        $updateLocation = $conn->prepare("UPDATE users SET lat = ?, lng = ?, last_location_update = NOW() WHERE id = ?");
        $updateLocation->bind_param("ddi", $passenger_lat, $passenger_lng, $pid);
        $updateLocation->execute();
        $updateLocation->close();
    }

    if ($total_pax > 4) {
        $error_message = "❌ Maximum of 4 passengers only per tricycle! You have $total_pax passengers.";
    } else {
        $stmtToda = $conn->prepare("SELECT id, toda_name FROM todas WHERE id = ?");
        $stmtToda->bind_param("i", $toda_id);
        $stmtToda->execute();
        $toda_data = $stmtToda->get_result()->fetch_assoc();
        $stmtToda->close();
        
        $stmtPickup = $conn->prepare("SELECT id, route_name, lat, lng FROM todas_routes WHERE id = ?");
        $stmtPickup->bind_param("i", $pickup_route_id);
        $stmtPickup->execute();
        $p_data = $stmtPickup->get_result()->fetch_assoc();
        $stmtPickup->close();
        
        $stmtDropoff = $conn->prepare("SELECT id, name, lat, lon FROM locations WHERE id = ?");
        $stmtDropoff->bind_param("i", $dropoff_id);
        $stmtDropoff->execute();
        $d_data = $stmtDropoff->get_result()->fetch_assoc();
        $stmtDropoff->close();
        
        if (!$toda_data || !$p_data || !$d_data) {
            $error_message = "❌ Invalid selection made.";
        } else {
            $pickup_lat = $p_data['lat'];
            $pickup_lng = $p_data['lng'];
            $dropoff_lat = $d_data['lat'];
            $dropoff_lng = $d_data['lon'];
            
            $earth_radius = 6371;
            $dLat = deg2rad($dropoff_lat - $pickup_lat);
            $dLon = deg2rad($dropoff_lng - $pickup_lng);
            $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($pickup_lat)) * cos(deg2rad($dropoff_lat)) * sin($dLon/2) * sin($dLon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $distance = $earth_radius * $c;
            
            $has_highway = hasHighwayRestriction($pickup_lat, $pickup_lng, $dropoff_lat, $dropoff_lng);
            $is_too_far = hasLongDistanceRestriction($distance);
            
            if ($has_highway) {
                $error_message = "❌ Booking not allowed! This route passes through a highway. Tricycles are not permitted on highways for safety reasons. Please choose a different destination or pickup location.";
            } elseif ($is_too_far) {
                $error_message = "❌ Booking not allowed! Distance exceeds maximum allowed (30 km). Your trip is " . round($distance, 2) . " km. Tricycles are only allowed for shorter distances.";
            } else {
                $fare_details = calculateFare($total_pax, $distance);
                
                $code = 'BK' . date('ymdHis') . rand(10,99);
                $payment_status = ($payment_method === 'gcash') ? 'pending' : 'paid';
                $status = 'PENDING';
                
                $stmt = $conn->prepare("INSERT INTO bookings (
                    booking_code, 
                    passenger_id, 
                    pickup_landmark, 
                    dropoff_landmark, 
                    fare_amount, 
                    notes, 
                    status, 
                    total_pax, 
                    trike_units, 
                    distance, 
                    fare, 
                    preferred_time, 
                    payment_status, 
                    payment_method, 
                    pickup_lat, 
                    pickup_lng, 
                    dropoff_lat, 
                    dropoff_lng, 
                    toda_id, 
                    toda_name, 
                    pickup_route_name,
                    pickup_route_id,
                    total_passengers,
                    required_tricycles,
                    distance_km
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param(
                    "sissdssiiddssssdddisssiid",
                    $code,
                    $pid,
                    $p_data['route_name'],
                    $d_data['name'],
                    $fare_details['total_fare'],
                    $notes,
                    $status,
                    $total_pax,
                    $fare_details['required_tricycles'],
                    $distance,
                    $fare_details['total_fare'],
                    $preferred_time,
                    $payment_status,
                    $payment_method,
                    $pickup_lat,
                    $pickup_lng,
                    $dropoff_lat,
                    $dropoff_lng,
                    $toda_id,
                    $toda_data['toda_name'],
                    $p_data['route_name'],
                    $p_data['id'],
                    $total_pax,
                    $fare_details['required_tricycles'],
                    $distance
                );

                if ($stmt->execute()) {
                    $booking_id = $conn->insert_id;
                    $stmt->close();
                    
                    if ($payment_method === 'gcash') {
                        header("Location: gcash-payment.php?id=$booking_id");
                        exit;
                    } else {
                        $qr_content = "BOOKING INFO\nCode: $code\nTODA: {$toda_data['toda_name']}\nFrom: {$p_data['route_name']}\nTo: {$d_data['name']}\nPax: $total_pax\nDistance: " . round($distance, 2) . "km\nTotal Fare: PHP " . number_format($fare_details['total_fare'], 2);
                        $qr_path = "../qr-code/$code.png";
                        if(!file_exists('../qr-code')) mkdir('../qr-code', 0777, true);
                        require_once '../vendor/phpqrcode/qrlib.php';
                        QRcode::png($qr_content, $qr_path, QR_ECLEVEL_L, 5);
                        
                        header("Location: qr.php?id=$booking_id");
                        exit;
                    }
                } else {
                    $error_message = "❌ Error creating booking: " . $conn->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Create Booking - GoTrike</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }

        .card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
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
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .card-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            font-size: 0.8rem;
        }

        .card-body {
            padding: 1.25rem;
        }

        .fare-guide {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdef5 100%);
            padding: 1rem;
            border-radius: 16px;
            border-left: 5px solid #2196F3;
            margin-bottom: 1rem;
            font-size: 0.75rem;
        }

        .highway-warning {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            padding: 0.75rem;
            border-radius: 12px;
            border-left: 4px solid #ef4444;
            margin-bottom: 1rem;
            font-size: 0.75rem;
            color: #991b1b;
            display: none;
        }

        .map-container {
            margin-bottom: 1rem;
            border-radius: 16px;
            overflow: hidden;
            border: 3px solid #e0e0e0;
            position: relative;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        #routeMap {
            height: 400px;
            width: 100%;
        }
        
        /* Mobile fullscreen map styles */
        .map-fullscreen {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            z-index: 9999 !important;
            border-radius: 0 !important;
            margin: 0 !important;
        }
        
        .map-fullscreen #routeMap {
            height: 100vh !important;
            border-radius: 0 !important;
        }
        
        .fullscreen-btn {
            position: absolute;
            bottom: 80px;
            right: 10px;
            z-index: 1000;
            background: white;
            border: none;
            border-radius: 40px;
            width: 44px;
            height: 44px;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .fullscreen-btn:hover {
            background: #667eea;
            color: white;
            transform: scale(1.05);
        }
        
        .close-fullscreen-btn {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 10000;
            background: rgba(0,0,0,0.7);
            color: white;
            border: none;
            border-radius: 40px;
            width: 44px;
            height: 44px;
            font-size: 24px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }
        
        .map-fullscreen + .close-fullscreen-btn {
            display: flex;
        }
        
        .leaflet-control-zoom {
            border: none !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2) !important;
        }
        
        .leaflet-control-zoom a {
            background: white !important;
            color: #667eea !important;
            font-weight: bold !important;
            border-radius: 8px !important;
            margin: 2px !important;
            width: 36px !important;
            height: 36px !important;
            line-height: 36px !important;
            font-size: 18px !important;
        }
        
        .leaflet-control-zoom a:hover {
            background: #667eea !important;
            color: white !important;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #333;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Searchable dropdown styles */
        .searchable-dropdown {
            position: relative;
            width: 100%;
        }
        
        .searchable-dropdown input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 0.9rem;
            box-sizing: border-box;
            cursor: pointer;
            background: white;
            transition: all 0.3s ease;
        }
        
        .searchable-dropdown input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 250px;
            overflow-y: auto;
            background: white;
            border: 2px solid #e0e0e0;
            border-top: none;
            border-radius: 0 0 12px 12px;
            z-index: 9999;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .dropdown-list.show {
            display: block;
        }
        
        .dropdown-item {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s ease;
        }
        
        .dropdown-item:hover {
            background: #f3f4f6;
        }
        
        .dropdown-item.selected {
            background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
            color: #667eea;
            font-weight: bold;
            border-left: 3px solid #667eea;
        }
        
        .dropdown-item .item-main {
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 0.85rem;
        }
        
        .dropdown-item .item-sub {
            font-size: 0.7rem;
            color: #6c757d;
        }
        
        .no-results {
            padding: 12px;
            text-align: center;
            color: #6c757d;
            font-style: italic;
            font-size: 0.8rem;
        }

        .passenger-input-wrapper {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #f9fafb;
            padding: 0.5rem;
            border-radius: 12px;
            border: 2px solid #e0e0e0;
        }
        
        .passenger-input-wrapper button {
            width: 44px;
            height: 44px;
            border: none;
            background: #667eea;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            touch-action: manipulation;
        }
        
        .passenger-input-wrapper button:hover {
            background: #764ba2;
            transform: scale(1.05);
        }
        
        .passenger-input-wrapper button:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            transform: none;
        }
        
        .passenger-input-wrapper input {
            flex: 1;
            text-align: center;
            font-size: 1.3rem;
            font-weight: bold;
            border: none;
            background: transparent;
            padding: 8px;
        }
        
        .passenger-input-wrapper input:focus {
            outline: none;
        }
        
        .passenger-info {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
            font-size: 0.7rem;
            color: #666;
        }

        .payment-options {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .payment-option {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .payment-option:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .payment-option input[type="radio"] {
            margin: 0;
            width: auto;
        }

        .payment-option.selected {
            border-color: #00B4D8;
            background: linear-gradient(135deg, #e6f7ff 0%, #f0f9ff 100%);
        }

        .gcash-icon {
            width: 36px;
            height: 36px;
            background: #00B4D8;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1rem;
        }

        .fare-display-box {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            padding: 1rem;
            border-radius: 16px;
            text-align: center;
            margin: 1rem 0;
            border: 2px solid #bbf7d0;
        }

        .trip-badges {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .badge-primary {
            background: #667eea;
            color: white;
        }

        .badge-success {
            background: #10b981;
            color: white;
        }
        
        .badge-info {
            background: #3b82f6;
            color: white;
        }
        
        .badge-warning {
            background: #f59e0b;
            color: white;
        }

        .fare-display-box h3 {
            color: #059669;
            margin: 0.5rem 0;
            font-size: 2rem;
        }
        
        .fare-breakdown-details {
            background: white;
            padding: 0.75rem;
            border-radius: 12px;
            margin-top: 0.75rem;
            text-align: left;
            font-size: 0.75rem;
        }
        
        .fare-breakdown-details p {
            margin: 0.4rem 0;
        }
        
        .driver-list {
            background: #f3f4f6;
            padding: 0.75rem;
            border-radius: 12px;
            margin-top: 0.5rem;
            font-size: 0.75rem;
            max-height: 180px;
            overflow-y: auto;
        }
        
        .driver-list h4 {
            margin: 0 0 0.5rem 0;
            font-size: 0.8rem;
            color: #374151;
        }
        
        .driver-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .driver-item:last-child {
            border-bottom: none;
        }
        
        .driver-name {
            font-weight: bold;
            color: #374151;
            font-size: 0.8rem;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            touch-action: manipulation;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .info-text {
            text-align: center;
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid #e0e0e0;
            font-size: 0.7rem;
            color: #6c757d;
        }
        
        .alert-danger {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            color: #b91c1c;
            font-weight: bold;
            font-size: 0.8rem;
        }
        
        .gps-status {
            background: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 0.6rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.75rem;
            display: none;
            align-items: center;
            gap: 0.5rem;
        }
        
        .gps-status.success {
            background: #d1fae5;
            border-left-color: #10b981;
            color: #065f46;
        }
        
        .gps-status.error {
            background: #fee2e2;
            border-left-color: #ef4444;
            color: #991b1b;
        }
        
        .gps-status.info {
            background: #fef3c7;
            border-left-color: #f59e0b;
            color: #92400e;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 6px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .legend {
            background: rgba(255,255,255,0.9);
            padding: 0.4rem;
            border-radius: 8px;
            font-size: 0.6rem;
            margin-top: 0.5rem;
            position: absolute;
            bottom: 10px;
            left: 10px;
            z-index: 1000;
            background: rgba(255,255,255,0.9);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            max-width: calc(100% - 80px);
        }
        
        .legend span {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 0.2rem;
        }
        
        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            white-space: nowrap;
        }
        
        .zoom-hint {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 9px;
            z-index: 1000;
            pointer-events: none;
            font-family: monospace;
        }
        
        .toda-info-card {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 0.75rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            display: none;
            border-left: 4px solid #f59e0b;
        }
        
        .toda-info-card h4 {
            margin: 0 0 0.3rem 0;
            color: #92400e;
            font-size: 0.85rem;
        }
        
        .toda-info-card p {
            margin: 0;
            font-size: 0.75rem;
            color: #78350f;
        }
        
        .passenger-warning {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 0.7rem;
            margin-top: 0.5rem;
            display: none;
            text-align: center;
        }
        
        textarea.form-control {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 0.85rem;
            box-sizing: border-box;
            font-family: inherit;
        }
        
        textarea.form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        @media (min-width: 768px) {
            .container {
                padding: 2rem;
            }
            .card-body {
                padding: 2rem;
            }
            .form-row {
                grid-template-columns: 1fr 1fr;
                gap: 1.5rem;
            }
            .payment-options {
                flex-direction: row;
            }
            #routeMap {
                height: 450px;
            }
            .fare-guide {
                font-size: 0.85rem;
                padding: 1.2rem;
            }
            .legend {
                font-size: 0.7rem;
                padding: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            #routeMap {
                height: 350px;
            }
            .card-header h2 {
                font-size: 1.3rem;
            }
            .badge {
                font-size: 0.6rem;
                padding: 0.3rem 0.6rem;
            }
            .fare-display-box h3 {
                font-size: 1.8rem;
            }
            .passenger-input-wrapper button {
                width: 40px;
                height: 40px;
            }
            .legend {
                font-size: 0.55rem;
                bottom: 5px;
                left: 5px;
                padding: 0.3rem;
            }
            .zoom-hint {
                font-size: 8px;
                bottom: 5px;
                right: 5px;
            }
            .fullscreen-btn {
                bottom: 70px;
                right: 8px;
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2> New Booking</h2>
            <p>Book your tricycle ride with GoTrike</p>
        </div>
        
        <div class="card-body">
            <div class="fare-guide">
                <strong>💰 NEW FARE RATE:</strong><br>
                • <strong>1-2 passengers:</strong> ₱30 base fare<br>
                • <strong>3-4 passengers:</strong> ₱15 per passenger<br>
                • <strong>Pickup fee:</strong> ₱10 per kilometer (rounded up)<br>
                <strong>👥 Capacity:</strong> Maximum <strong>4 passengers</strong> per tricycle<br>
                <strong>🚲 Tricycles needed:</strong> 1 tricycle only (max 4 pax)<br>
                <strong>⚠️ RESTRICTION:</strong> <span style="color:#dc2626;">Tricycles are NOT allowed on highways!</span>
            </div>
            
            <div id="highway-warning" class="highway-warning">
                <strong>⚠️ HIGHWAY RESTRICTION:</strong> Tricycles are not allowed on highways for safety reasons.
            </div>
            
            <div id="gps-status" class="gps-status info">
                <span>📍</span>
                <span id="gps-status-text">Getting your location...</span>
            </div>
            
            <?php if ($error_message): ?>
            <div class="alert-danger">
                <?= htmlspecialchars($error_message) ?>
            </div>
            <?php endif; ?>

            <div class="map-container" id="mapContainer" style="position: relative;">
                <div id="routeMap"></div>
                <button class="fullscreen-btn" id="fullscreenBtn" title="Fullscreen Map">⛶</button>
                <div class="zoom-hint">🔍 Use + / - buttons or scroll to zoom</div>
                <div class="legend">
                    <div class="legend-item"><span style="background: #10b981;"></span> Pickup</div>
                    <div class="legend-item"><span style="background: #ef4444;"></span> Dropoff</div>
                    <div class="legend-item"><span style="background: #3b82f6;"></span> You</div>
                    <div class="legend-item"><span style="background: #f59e0b;"></span> Drivers</div>
                </div>
            </div>
            <button class="close-fullscreen-btn" id="closeFullscreenBtn">✕</button>

            <form method="POST" id="bookingForm">
                <input type="hidden" name="passenger_lat" id="passenger_lat" value="">
                <input type="hidden" name="passenger_lng" id="passenger_lng" value="">
                
                <div id="toda-info-card" class="toda-info-card">
                    <h4>🚖 <span id="toda-name-display"></span></h4>
                    <p>👨‍✈️ <span id="driver-count-display"></span> drivers available</p>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>📍 Pickup Location (Search by name)</label>
                        <div class="searchable-dropdown" id="pickup-dropdown">
                            <input type="text" id="pickup-search" placeholder="Type to search pickup locations..." autocomplete="off">
                            <input type="hidden" id="pickup_route" name="pickup_route" value="">
                            <div id="pickup-list" class="dropdown-list"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>📍 Drop-off Landmark (Search by name)</label>
                        <div class="searchable-dropdown" id="dropoff-dropdown">
                            <input type="text" id="dropoff-search" placeholder="Type to search dropoff locations..." autocomplete="off">
                            <input type="hidden" id="dropoff" name="dropoff" value="">
                            <div id="dropoff-list" class="dropdown-list"></div>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" name="toda_id" id="toda_id" value="">

                <div class="form-group">
                    <label>👥 Total Passengers <span style="color:#dc2626;">(MAX: 4)</span></label>
                    <div class="passenger-input-wrapper">
                        <button type="button" id="decrement-pax" class="decrement">-</button>
                        <input type="number" name="total_pax" id="total_pax" min="1" max="4" value="1" required>
                        <button type="button" id="increment-pax" class="increment">+</button>
                    </div>
                    <div class="passenger-info">
                        <span>🚲 <span id="tricycle-count">1</span> tricycle needed</span>
                        <span>💰 <span id="per-person-estimate">₱0</span> estimated per person</span>
                    </div>
                    <div id="passenger-warning" class="passenger-warning">
                        ❌ Maximum of 4 passengers only per tricycle!
                    </div>
                </div>
                
               

                <div class="form-group">
                    <label>💳 Payment Method</label>
                    <div class="payment-options">
                        <label class="payment-option selected" id="cashOption">
                            <input type="radio" name="payment_method" value="cash" checked>
                            <span class="gcash-icon">💰</span>
                            <span>Cash on Pickup</span>
                        </label>
                    </div>
                </div>

                <div id="fare-display-box" class="fare-display-box" style="display:none;">
                    <div class="trip-badges">
                        <span class="badge badge-primary" id="trip-badge">Trip Details</span>
                        <span class="badge badge-success" id="dist-count">0 km</span>
                        <span class="badge badge-info" id="pax-badge">1 Passenger</span>
                    </div>
                    <h3>₱<span id="fare-val">0</span></h3>
                    <div class="fare-breakdown-details" id="fare-breakdown">
                        <p><strong>Fare Breakdown:</strong></p>
                        <p id="base-fare-detail">Base fare: ₱0</p>
                        <p id="pickup-fee-detail">Pickup fee: ₱0</p>
                        <p id="distance-detail">Distance: 0 km</p>
                        <p id="tricycle-detail">Tricycles: 1</p>
                    </div>
                </div>

                <div class="form-group">
                    <label>📝 Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Special requests or additional information..."></textarea>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">✅ Confirm Booking</button>
                
                <div class="info-text">
                    <small>You will receive a QR code after booking confirmation.</small>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const MAPTILER_KEY = "DI93VaqaUOALks9Ooffd";

let map;
let pickupMarker = null;
let dropoffMarker = null;
let passengerMarker = null;
let driverMarkers = [];
let routeLayer = null;
let isFullscreen = false;

const dropoffLandmarks = <?= json_encode($dropoffLandmarks) ?>;
const todaGroups = <?= json_encode($todaGroups) ?>;
const allPickupRoutes = <?= json_encode($allPickupRoutes) ?>;

let passengerLat = null;
let passengerLng = null;
let allLocationPoints = [];

function initMap() {
    const defaultCenter = [14.5995, 120.9842];
    
    map = L.map('routeMap').setView(defaultCenter, 13);
    
    L.tileLayer(`https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, {
        tileSize: 512,
        zoomOffset: -1,
        attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a>',
        maxZoom: 19,
        minZoom: 10
    }).addTo(map);
    
    map.zoomControl.setPosition('topright');
    
    // Invalidate size after a short delay to ensure proper rendering
    setTimeout(() => {
        map.invalidateSize();
    }, 200);
}

function addPointToZoomBounds(lat, lng, type = null) {
    const point = [lat, lng];
    if (type) point.type = type;
    allLocationPoints.push(point);
}

function fitMapToAllLocations() {
    if (allLocationPoints.length === 0) {
        if (passengerLat && passengerLng) {
            map.setView([passengerLat, passengerLng], 15);
        }
        return;
    }
    
    let minLat = Infinity, maxLat = -Infinity;
    let minLng = Infinity, maxLng = -Infinity;
    
    allLocationPoints.forEach(point => {
        minLat = Math.min(minLat, point[0]);
        maxLat = Math.max(maxLat, point[0]);
        minLng = Math.min(minLng, point[1]);
        maxLng = Math.max(maxLng, point[1]);
    });
    
    const latPadding = (maxLat - minLat) * 0.1;
    const lngPadding = (maxLng - minLng) * 0.1;
    
    const bounds = L.latLngBounds(
        [minLat - latPadding, minLng - lngPadding],
        [maxLat + latPadding, maxLng + lngPadding]
    );
    
    map.fitBounds(bounds, {
        padding: [50, 50],
        maxZoom: 18
    });
}

function updatePassengerLocation(lat, lng) {
    if (passengerMarker) {
        map.removeLayer(passengerMarker);
    }
    
    passengerMarker = L.marker([lat, lng], {
        icon: L.divIcon({
            html: '<div style="background: #3b82f6; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
            iconSize: [22, 22],
            className: 'passenger-marker'
        }),
        zIndexOffset: 100
    }).addTo(map);
    passengerMarker.bindTooltip('<b>📍 You are here</b><br>Your current location', { permanent: false });
    
    addPointToZoomBounds(lat, lng, 'passenger');
}

function updateDriverLocations(todaId) {
    driverMarkers.forEach(marker => {
        if (marker && map) map.removeLayer(marker);
    });
    driverMarkers = [];
    
    allLocationPoints = allLocationPoints.filter(point => point.type !== 'driver');
    
    const toda = todaGroups.find(t => t.id == todaId);
    if (toda && toda.driver_locations) {
        toda.driver_locations.forEach(driver => {
            if (driver.lat && driver.lng) {
                const marker = L.marker([driver.lat, driver.lng], {
                    icon: L.divIcon({
                        html: '<div style="background: #f59e0b; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                        iconSize: [16, 16],
                        className: 'driver-marker'
                    }),
                    zIndexOffset: 80
                }).addTo(map);
                marker.bindTooltip(`<b>🚖 Driver: ${driver.name}</b><br>Real-time location`, { permanent: false });
                driverMarkers.push(marker);
                
                addPointToZoomBounds(driver.lat, driver.lng, 'driver');
            }
        });
    }
}

function updateTricycleCount() {
    const totalPax = parseInt(document.getElementById('total_pax').value) || 1;
    const tricycles = Math.ceil(totalPax / 4);
    document.getElementById('tricycle-count').innerText = tricycles;
    return tricycles;
}

function updateMarkers() {
    const pickupRouteId = document.getElementById('pickup_route').value;
    const dropoffId = document.getElementById('dropoff').value;
    
    if (pickupMarker) {
        map.removeLayer(pickupMarker);
        pickupMarker = null;
    }
    if (dropoffMarker) {
        map.removeLayer(dropoffMarker);
        dropoffMarker = null;
    }
    
    allLocationPoints = allLocationPoints.filter(point => point.type !== 'pickup' && point.type !== 'dropoff');
    
    if (pickupRouteId) {
        const pickupRoute = allPickupRoutes.find(r => r.id == pickupRouteId);
        if (pickupRoute) {
            pickupMarker = L.marker([pickupRoute.lat, pickupRoute.lng], {
                icon: L.divIcon({
                    html: '<div style="background: #10b981; width: 14px; height: 14px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                    iconSize: [18, 18],
                    className: 'custom-marker'
                }),
                zIndexOffset: 90
            }).addTo(map);
            pickupMarker.bindTooltip('<b>📍 PICKUP LOCATION</b><br>' + pickupRoute.route_name, { 
                permanent: false,
                direction: 'top'
            });
            addPointToZoomBounds(pickupRoute.lat, pickupRoute.lng, 'pickup');
        }
    }
    
    if (dropoffId) {
        const dropoff = dropoffLandmarks.find(l => l.id == dropoffId);
        if (dropoff) {
            dropoffMarker = L.marker([dropoff.lat, dropoff.lon], {
                icon: L.divIcon({
                    html: '<div style="background: #ef4444; width: 14px; height: 14px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                    iconSize: [18, 18],
                    className: 'custom-marker'
                }),
                zIndexOffset: 90
            }).addTo(map);
            dropoffMarker.bindTooltip('<b>🏁 DROPOFF LOCATION</b><br>' + dropoff.name, { 
                permanent: false,
                direction: 'top'
            });
            addPointToZoomBounds(dropoff.lat, dropoff.lon, 'dropoff');
        }
    }
    
    if (allLocationPoints.length > 0) {
        setTimeout(() => {
            fitMapToAllLocations();
        }, 100);
    }
    
    if (pickupRouteId && dropoffId) {
        const pickupRoute = allPickupRoutes.find(r => r.id == pickupRouteId);
        const dropoff = dropoffLandmarks.find(l => l.id == dropoffId);
        if (pickupRoute && dropoff) {
            drawRoute(pickupRoute.lat, pickupRoute.lng, dropoff.lat, dropoff.lon);
        }
    }
}

function drawRoute(startLat, startLon, endLat, endLon) {
    if (routeLayer) {
        map.removeLayer(routeLayer);
    }
    
    const url = `https://router.project-osrm.org/route/v1/driving/${startLon},${startLat};${endLon},${endLat}?overview=full&geometries=geojson`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.code === 'Ok' && data.routes && data.routes[0]) {
                const route = data.routes[0].geometry;
                
                routeLayer = L.geoJSON(route, {
                    style: {
                        color: '#667eea',
                        weight: 5,
                        opacity: 0.8
                    }
                }).addTo(map);
                
                if (routeLayer) {
                    routeLayer.bringToFront();
                }
            }
        })
        .catch(error => console.error('Error drawing route:', error));
}

function calculateNewFare(totalPax, distanceKm) {
    let baseFare;
    if (totalPax <= 2) {
        baseFare = 30;
    } else {
        baseFare = totalPax * 15;
    }
    
    const roundedDistance = Math.ceil(distanceKm);
    const pickupFee = roundedDistance * 10;
    const totalFare = baseFare + pickupFee;
    const requiredTricycles = Math.ceil(totalPax / 4);
    
    return {
        baseFare: baseFare,
        pickupFee: pickupFee,
        distanceKm: distanceKm,
        roundedDistance: roundedDistance,
        totalFare: totalFare,
        requiredTricycles: requiredTricycles
    };
}

function calculateLogic() {
    const pickupRouteId = document.getElementById('pickup_route').value;
    const dropoffId = document.getElementById('dropoff').value;
    const totalPax = parseInt(document.getElementById('total_pax').value) || 1;
    
    const passengerWarning = document.getElementById('passenger-warning');
    
    if (totalPax > 4) {
        passengerWarning.style.display = 'block';
        document.getElementById('fare-display-box').style.display = 'none';
        document.getElementById('submitBtn').disabled = true;
        return;
    } else {
        passengerWarning.style.display = 'none';
    }
    
    if (!pickupRouteId || !dropoffId) {
        document.getElementById('fare-display-box').style.display = 'none';
        document.getElementById('submitBtn').disabled = false;
        return;
    }
    
    const pickupRoute = allPickupRoutes.find(r => r.id == pickupRouteId);
    const dropoff = dropoffLandmarks.find(l => l.id == dropoffId);
    
    if (!pickupRoute || !dropoff) {
        document.getElementById('fare-display-box').style.display = 'none';
        return;
    }
    
    updateMarkers();
    
    const R = 6371;
    const dLat = (dropoff.lat - pickupRoute.lat) * Math.PI / 180;
    const dLon = (dropoff.lon - pickupRoute.lng) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(pickupRoute.lat * Math.PI / 180) * Math.cos(dropoff.lat * Math.PI / 180) * 
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    const distance = R * c;
    
    const fareDetails = calculateNewFare(totalPax, distance);
    
    const fareBox = document.getElementById('fare-display-box');
    fareBox.style.display = 'block';
    
    document.getElementById('dist-count').innerHTML = `📏 ${distance.toFixed(2)} km`;
    document.getElementById('pax-badge').innerHTML = `${totalPax} Passenger${totalPax > 1 ? 's' : ''}`;
    document.getElementById('fare-val').innerText = fareDetails.totalFare;
    
    const perPerson = Math.ceil(fareDetails.totalFare / totalPax);
    document.getElementById('per-person-estimate').innerHTML = `₱${perPerson}`;
    
    document.getElementById('base-fare-detail').innerHTML = `Base fare: ₱${fareDetails.baseFare} ${totalPax <= 2 ? '(1-2 pax)' : `(${totalPax} pax × ₱15)`}`;
    document.getElementById('pickup-fee-detail').innerHTML = `Pickup fee: ₱${fareDetails.pickupFee} (${fareDetails.roundedDistance} km × ₱10)`;
    document.getElementById('distance-detail').innerHTML = `Distance: ${distance.toFixed(2)} km (rounded up to ${fareDetails.roundedDistance} km)`;
    document.getElementById('tricycle-detail').innerHTML = `Tricycles needed: ${fareDetails.requiredTricycles} (${totalPax} passengers ÷ 4)`;
    
    document.getElementById('submitBtn').disabled = false;
}

function getCurrentLocation() {
    const gpsStatusDiv = document.getElementById('gps-status');
    const gpsStatusText = document.getElementById('gps-status-text');
    const passengerLatInput = document.getElementById('passenger_lat');
    const passengerLngInput = document.getElementById('passenger_lng');
    
    if (navigator.geolocation) {
        gpsStatusDiv.style.display = 'flex';
        gpsStatusDiv.className = 'gps-status info';
        gpsStatusText.innerHTML = '📍 Getting your location... <span class="loading-spinner"></span>';
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                passengerLat = position.coords.latitude;
                passengerLng = position.coords.longitude;
                
                passengerLatInput.value = passengerLat;
                passengerLngInput.value = passengerLng;
                
                updatePassengerLocation(passengerLat, passengerLng);
                
                gpsStatusDiv.className = 'gps-status success';
                gpsStatusText.innerHTML = `✅ GPS location captured!`;
                
                setTimeout(() => {
                    gpsStatusDiv.style.display = 'none';
                }, 5000);
                
                updateMarkers();
                
                setTimeout(() => fitMapToAllLocations(), 500);
            },
            function(error) {
                let errorMsg = '⚠️ Unable to get your location. Please enable GPS.';
                
                gpsStatusDiv.className = 'gps-status error';
                gpsStatusText.innerHTML = errorMsg;
                
                setTimeout(() => {
                    gpsStatusDiv.style.display = 'none';
                }, 8000);
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    } else {
        gpsStatusDiv.style.display = 'flex';
        gpsStatusDiv.className = 'gps-status error';
        gpsStatusText.innerHTML = '⚠️ Your browser does not support geolocation.';
        
        setTimeout(() => {
            gpsStatusDiv.style.display = 'none';
        }, 5000);
    }
}

function initSearchableDropdowns() {
    const pickupSearch = document.getElementById('pickup-search');
    const pickupList = document.getElementById('pickup-list');
    const dropoffSearch = document.getElementById('dropoff-search');
    const dropoffList = document.getElementById('dropoff-list');
    
    pickupSearch.addEventListener('click', function(e) {
        e.stopPropagation();
        renderPickupList(pickupSearch.value);
        pickupList.classList.add('show');
    });
    
    pickupSearch.addEventListener('focus', function(e) {
        e.stopPropagation();
        renderPickupList(pickupSearch.value);
        pickupList.classList.add('show');
    });
    
    pickupSearch.addEventListener('input', function(e) {
        renderPickupList(e.target.value);
        pickupList.classList.add('show');
    });
    
    dropoffSearch.addEventListener('click', function(e) {
        e.stopPropagation();
        renderDropoffList(dropoffSearch.value);
        dropoffList.classList.add('show');
    });
    
    dropoffSearch.addEventListener('focus', function(e) {
        e.stopPropagation();
        renderDropoffList(dropoffSearch.value);
        dropoffList.classList.add('show');
    });
    
    dropoffSearch.addEventListener('input', function(e) {
        renderDropoffList(e.target.value);
        dropoffList.classList.add('show');
    });
    
    document.addEventListener('click', function(e) {
        if (!pickupSearch.contains(e.target) && !pickupList.contains(e.target)) {
            pickupList.classList.remove('show');
        }
        if (!dropoffSearch.contains(e.target) && !dropoffList.contains(e.target)) {
            dropoffList.classList.remove('show');
        }
    });
}

function renderPickupList(searchTerm) {
    const pickupList = document.getElementById('pickup-list');
    const filtered = allPickupRoutes.filter(route => 
        route.route_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        route.toda_name.toLowerCase().includes(searchTerm.toLowerCase())
    );
    
    if (filtered.length === 0) {
        pickupList.innerHTML = '<div class="no-results">No pickup locations found</div>';
        return;
    }
    
    pickupList.innerHTML = filtered.map(route => `
        <div class="dropdown-item" data-id="${route.id}" data-name="${escapeHtml(route.route_name)}" 
             data-lat="${route.lat}" data-lng="${route.lng}" data-toda-id="${route.toda_id}" data-toda-name="${escapeHtml(route.toda_name)}">
            <div class="item-main">📍 ${escapeHtml(route.route_name)}</div>
            <div class="item-sub">🚖 ${escapeHtml(route.toda_name)}</div>
        </div>
    `).join('');
    
    pickupList.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const id = this.dataset.id;
            const name = this.dataset.name;
            const todaId = this.dataset.todaId;
            const todaName = this.dataset.todaName;
            
            document.getElementById('pickup-search').value = name;
            document.getElementById('pickup_route').value = id;
            document.getElementById('toda_id').value = todaId;
            
            const todaInfo = document.getElementById('toda-info-card');
            document.getElementById('toda-name-display').innerText = todaName;
            
            const toda = todaGroups.find(t => t.id == todaId);
            if (toda) {
                document.getElementById('driver-count-display').innerText = toda.driver_count;
                
                const driverListDiv = document.getElementById('driver-list');
                const driversContainer = document.getElementById('drivers-list-container');
                
                if (toda.drivers_list && toda.drivers_list.length > 0) {
                    let driversHtml = '';
                    toda.drivers_list.forEach(driver => {
                        driversHtml += `<div class="driver-item"><span class="driver-name">👨‍✈️ ${escapeHtml(driver.name)}</span></div>`;
                    });
                    driversContainer.innerHTML = driversHtml;
                    driverListDiv.style.display = 'block';
                    updateDriverLocations(todaId);
                } else {
                    driverListDiv.style.display = 'none';
                    updateDriverLocations(null);
                }
                
                todaInfo.style.display = 'block';
            }
            
            pickupList.classList.remove('show');
            updateMarkers();
            calculateLogic();
            
            pickupList.querySelectorAll('.dropdown-item').forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
}

function renderDropoffList(searchTerm) {
    const dropoffList = document.getElementById('dropoff-list');
    const filtered = dropoffLandmarks.filter(landmark => 
        landmark.name.toLowerCase().includes(searchTerm.toLowerCase())
    );
    
    if (filtered.length === 0) {
        dropoffList.innerHTML = '<div class="no-results">No dropoff locations found</div>';
        return;
    }
    
    dropoffList.innerHTML = filtered.map(landmark => `
        <div class="dropdown-item" data-id="${landmark.id}" data-name="${escapeHtml(landmark.name)}" 
             data-lat="${landmark.lat}" data-lon="${landmark.lon}">
            <div class="item-main">📍 ${escapeHtml(landmark.name)}</div>
        </div>
    `).join('');
    
    dropoffList.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const id = this.dataset.id;
            const name = this.dataset.name;
            
            document.getElementById('dropoff-search').value = name;
            document.getElementById('dropoff').value = id;
            
            dropoffList.classList.remove('show');
            updateMarkers();
            calculateLogic();
            
            dropoffList.querySelectorAll('.dropdown-item').forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function toggleFullscreen() {
    const mapContainer = document.getElementById('mapContainer');
    const closeBtn = document.getElementById('closeFullscreenBtn');
    
    if (!isFullscreen) {
        mapContainer.classList.add('map-fullscreen');
        closeBtn.style.display = 'flex';
        isFullscreen = true;
        setTimeout(() => {
            map.invalidateSize();
        }, 100);
    } else {
        mapContainer.classList.remove('map-fullscreen');
        closeBtn.style.display = 'none';
        isFullscreen = false;
        setTimeout(() => {
            map.invalidateSize();
            fitMapToAllLocations();
        }, 100);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initMap();
    initSearchableDropdowns();
    renderPickupList('');
    renderDropoffList('');
    
    setTimeout(() => {
        getCurrentLocation();
    }, 500);
    
    const totalPaxInput = document.getElementById('total_pax');
    const decrementBtn = document.getElementById('decrement-pax');
    const incrementBtn = document.getElementById('increment-pax');
    
    function updatePassengerCount() {
        let val = parseInt(totalPaxInput.value) || 1;
        if (val < 1) val = 1;
        if (val > 4) val = 4;
        totalPaxInput.value = val;
        updateTricycleCount();
        calculateLogic();
        
        decrementBtn.disabled = val <= 1;
        incrementBtn.disabled = val >= 4;
    }
    
    decrementBtn.addEventListener('click', function() {
        let val = parseInt(totalPaxInput.value) || 1;
        if (val > 1) {
            totalPaxInput.value = val - 1;
            updatePassengerCount();
        }
    });
    
    incrementBtn.addEventListener('click', function() {
        let val = parseInt(totalPaxInput.value) || 1;
        if (val < 4) {
            totalPaxInput.value = val + 1;
            updatePassengerCount();
        }
    });
    
    totalPaxInput.addEventListener('input', updatePassengerCount);
    
    document.querySelectorAll('.payment-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;
        });
    });
    
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    const closeFullscreenBtn = document.getElementById('closeFullscreenBtn');
    
    fullscreenBtn.addEventListener('click', toggleFullscreen);
    closeFullscreenBtn.addEventListener('click', toggleFullscreen);
    
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        const pickupRouteVal = document.getElementById('pickup_route').value;
        const dropoffVal = document.getElementById('dropoff').value;
        
        if (!pickupRouteVal) {
            e.preventDefault();
            alert('Please select a pickup location');
            return false;
        }
        
        if (!dropoffVal) {
            e.preventDefault();
            alert('Please select a dropoff location');
            return false;
        }
        
        const passengerLatVal = document.getElementById('passenger_lat').value;
        const passengerLngVal = document.getElementById('passenger_lng').value;
        
        if (!passengerLatVal || !passengerLngVal) {
            e.preventDefault();
            alert('Getting your location... Please click Confirm Booking again.');
            getCurrentLocation();
            return false;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>