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

$api_key = '2c48f43ad4134a588fbbde01128581dc';
$mode = 'cycle'; // Para sa tricycle (iwas highway, service roads lang)

$waypoints = $start_lat . '%2C' . $start_lng . '%7C' . $end_lat . '%2C' . $end_lng;
$url = "https://api.geoapify.com/v1/routing?waypoints={$waypoints}&mode={$mode}&apiKey={$api_key}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'GoTrike-Driver/1.0');

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo json_encode(['success' => false, 'error' => 'CURL Error: ' . $curl_error]);
    exit;
}

if ($http_code !== 200) {
    // Fallback: OSRM libreng routing
    $fallback_url = "https://router.project-osrm.org/route/v1/bike/{$start_lng},{$start_lat};{$end_lng},{$end_lat}?overview=full&geometries=geojson";
    
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $fallback_url);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch2, CURLOPT_USERAGENT, 'GoTrike-Driver/1.0');
    
    $response2 = curl_exec($ch2);
    $http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    
    if ($http_code2 === 200) {
        $data2 = json_decode($response2, true);
        if (isset($data2['routes'][0]['geometry']['coordinates'])) {
            $coords = $data2['routes'][0]['geometry']['coordinates'];
            $coordinates = [];
            foreach ($coords as $coord) {
                $coordinates[] = [$coord[1], $coord[0]];
            }
            $distance = $data2['routes'][0]['distance'] ?? 0;
            $time = $data2['routes'][0]['duration'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'coordinates' => $coordinates,
                'distance' => $distance,
                'time' => $time,
                'source' => 'osrm_fallback'
            ]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'error' => 'API returned HTTP ' . $http_code]);
    exit;
}

$data = json_decode($response, true);

if ($data && isset($data['features'][0]['geometry'])) {
    $geometry = $data['features'][0]['geometry'];
    $coordinates = [];
    
    if ($geometry['type'] === 'LineString') {
        foreach ($geometry['coordinates'] as $coord) {
            $coordinates[] = [$coord[1], $coord[0]];
        }
    } elseif ($geometry['type'] === 'MultiLineString') {
        foreach ($geometry['coordinates'] as $line) {
            foreach ($line as $coord) {
                $coordinates[] = [$coord[1], $coord[0]];
            }
        }
    }
    
    $distance = $data['features'][0]['properties']['distance'] ?? 0;
    $time = $data['features'][0]['properties']['time'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'coordinates' => $coordinates,
        'distance' => $distance,
        'time' => $time,
        'source' => 'geoapify'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'No route found']);
}
?>