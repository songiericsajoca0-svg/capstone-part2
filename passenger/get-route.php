<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$start_lat = $_GET['start_lat'] ?? null;
$start_lng = $_GET['start_lng'] ?? null;
$end_lat = $_GET['end_lat'] ?? null;
$end_lng = $_GET['end_lng'] ?? null;

if (!$start_lat || !$start_lng || !$end_lat || !$end_lng) {
    echo json_encode(['success' => false, 'error' => 'Missing coordinates']);
    exit;
}

// GAMITIN ANG LIBRENG OSRM API (OpenStreetMap Routing Machine)
// Ito ay libre at walang API key na kailangan!
// Profile: 'foot' para sa tricycle/bike routes (iwas highway)
$coordinates = "{$start_lng},{$start_lat};{$end_lng},{$end_lat}";
$url = "https://router.project-osrm.org/route/v1/foot/{$coordinates}?overview=full&geometries=geojson";

// Alternative kung ayaw ng foot, gamitin ang 'bike' profile
// $url = "https://router.project-osrm.org/route/v1/bike/{$coordinates}?overview=full&geometries=geojson";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'GoTrike-App/1.0');

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo json_encode(['success' => false, 'error' => 'CURL Error: ' . $curl_error]);
    exit;
}

if ($http_code !== 200) {
    // Fallback: Gumamit ng GraphHopper (libre din)
    $fallback_url = "https://graphhopper.com/api/1/route?point={$start_lat},{$start_lng}&point={$end_lat},{$end_lng}&vehicle=bike&locale=en&key=7a1e5cf0-b850-4a43-8a38-5d6ebd694132";
    
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $fallback_url);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch2, CURLOPT_USERAGENT, 'GoTrike-App/1.0');
    
    $response2 = curl_exec($ch2);
    $http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    
    if ($http_code2 === 200) {
        $data2 = json_decode($response2, true);
        if (isset($data2['paths'][0]['points']['coordinates'])) {
            $coords = $data2['paths'][0]['points']['coordinates'];
            $coordinates = [];
            foreach ($coords as $coord) {
                $coordinates[] = [$coord[1], $coord[0]];
            }
            $distance = $data2['paths'][0]['distance'] ?? 0;
            $time = $data2['paths'][0]['time'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'coordinates' => $coordinates,
                'distance' => $distance,
                'time' => $time / 1000,
                'source' => 'graphhopper'
            ]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'error' => 'OSRM HTTP ' . $http_code]);
    exit;
}

$data = json_decode($response, true);

if ($data && isset($data['routes'][0]['geometry']['coordinates'])) {
    $coords = $data['routes'][0]['geometry']['coordinates'];
    $coordinates = [];
    foreach ($coords as $coord) {
        $coordinates[] = [$coord[1], $coord[0]];
    }
    
    $distance = $data['routes'][0]['distance'] ?? 0;
    $time = $data['routes'][0]['duration'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'coordinates' => $coordinates,
        'distance' => $distance,
        'time' => $time,
        'source' => 'osrm'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'No route found from OSRM']);
}
?>