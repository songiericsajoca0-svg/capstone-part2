<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['restricted' => false, 'message' => '']);
    exit;
}

$from_lat = floatval($_POST['from_lat'] ?? 0);
$from_lon = floatval($_POST['from_lon'] ?? 0);
$to_lat   = floatval($_POST['to_lat'] ?? 0);
$to_lon   = floatval($_POST['to_lon'] ?? 0);

if ($from_lat == 0 || $from_lon == 0 || $to_lat == 0 || $to_lon == 0) {
    echo json_encode(['restricted' => false, 'message' => '']);
    exit;
}

# ============================================
# CACHE (SUPER FAST)
# ============================================
$key = md5($from_lat.$from_lon.$to_lat.$to_lon);
$cache_dir = __DIR__ . '/cache/';
$cache_file = $cache_dir . $key . '.json';

if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0777, true);
}

if (file_exists($cache_file)) {
    echo file_get_contents($cache_file);
    exit;
}

# ============================================
# RESTRICTED ROAD NAMES
# ============================================
$restricted_names = [
    'edsa','epifanio de los santos avenue',
    'c-5','c5','circumferential road 5',
    'c-4','c4',
    'skyway','slex','nlex',
    'commonwealth avenue',
    'quezon avenue',
    'katipunan avenue',
    'roxas boulevard',
    'taft avenue',
    'ortigas avenue',
    'shaw boulevard',
    'marcos highway',
    'macapagal boulevard'
];

function isRestrictedRoad($name) {
    global $restricted_names;
    $name = strtolower($name);
    foreach ($restricted_names as $r) {
        if (strpos($name, $r) !== false) return true;
    }
    return false;
}

# ============================================
# EDSA SEGMENT GEOFENCING (MORE ACCURATE)
# ============================================
$edsa_segments = [
    ['min_lat'=>14.54,'max_lat'=>14.56,'min_lon'=>121.00,'max_lon'=>121.03],
    ['min_lat'=>14.56,'max_lat'=>14.60,'min_lon'=>121.03,'max_lon'=>121.05],
    ['min_lat'=>14.60,'max_lat'=>14.65,'min_lon'=>121.05,'max_lon'=>121.07],
    ['min_lat'=>14.65,'max_lat'=>14.70,'min_lon'=>121.03,'max_lon'=>121.06],
];

function isInsideBox($lat,$lon,$box){
    return $lat >= $box['min_lat'] && $lat <= $box['max_lat'] &&
           $lon >= $box['min_lon'] && $lon <= $box['max_lon'];
}

function crossesEDSA($lat1,$lon1,$lat2,$lon2){
    global $edsa_segments;

    foreach($edsa_segments as $box){
        if (isInsideBox($lat1,$lon1,$box) || isInsideBox($lat2,$lon2,$box)) {
            return true;
        }
    }

    return false;
}

# ============================================
# MAIN LOGIC
# ============================================
$restricted = false;
$message = '';

# 1. FAST EDSA CHECK
if (crossesEDSA($from_lat,$from_lon,$to_lat,$to_lon)) {
    $restricted = true;
    $message = "❌ Route passes near EDSA. Tricycles are NOT allowed.";
}

# 2. OSRM CHECK (ONLY 1 CALL)
if (!$restricted) {

    $url = "https://router.project-osrm.org/route/v1/driving/"
        . "{$from_lon},{$from_lat};{$to_lon},{$to_lat}"
        . "?overview=false&steps=true";

    $response = @file_get_contents($url);

    if ($response) {
        $data = json_decode($response, true);

        if ($data['code'] === 'Ok') {

            $route = $data['routes'][0];
            $distance_km = $route['distance'] / 1000;

            # 2.1 DISTANCE LIMIT
            if ($distance_km > 10) {
                $restricted = true;
                $message = "❌ Route is ".round($distance_km,1)." km. Too far for tricycles.";
            }

            # 2.2 CHECK ROAD NAMES
            if (!$restricted) {
                $steps = $route['legs'][0]['steps'];

                foreach ($steps as $step) {
                    $road = strtolower($step['name'] ?? '');

                    if (isRestrictedRoad($road)) {
                        $restricted = true;
                        $message = "❌ Route passes through {$road}. Tricycles are not allowed.";
                        break;
                    }
                }
            }
        }
    }
}

# ============================================
# FINAL RESPONSE
# ============================================
$result = [
    'restricted' => $restricted,
    'message' => $restricted ? $message : '',
    'from' => ['lat'=>$from_lat,'lon'=>$from_lon],
    'to' => ['lat'=>$to_lat,'lon'=>$to_lon]
];

file_put_contents($cache_file, json_encode($result));

echo json_encode($result);