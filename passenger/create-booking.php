<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'passenger') {
    header("Location: ../index.php");
    exit;
}

// 1. Get landmarks for dropdown
$landmarks = [];
$locQuery = $conn->query("SELECT id, name, lat, lon FROM locations ORDER BY name ASC");
while ($row = $locQuery->fetch_assoc()) {
    $landmarks[] = $row;
}

// 2. Fare Logic
function calculateFare($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; 
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earth_radius * $c;
    
    // Base 25 (1km) + 5 per extra km
    $farePerTrike = ($distance <= 1) ? 25 : 25 + (($distance - 1) * 5);
    
    return ['dist' => round($distance, 2), 'fare' => round($farePerTrike, 2)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pickup_id   = $_POST['pickup'];
    $dropoff_id  = $_POST['dropoff'];
    $notes       = trim($_POST['notes'] ?? '');
    $pid         = $_SESSION['user_id'];
    $pkup_time   = $_POST['pickup_time'] ?? null;
    $total_pax   = intval($_POST['total_pax'] ?? 1);

    // AUTO-CALCULATE TRIKE UNITS: 1 trike per 4 passengers
    $trike_units = ceil($total_pax / 4);

    $stmtLoc = $conn->prepare("SELECT name, lat, lon FROM locations WHERE id = ?");
    $stmtLoc->bind_param("i", $pickup_id);
    $stmtLoc->execute();
    $p_data = $stmtLoc->get_result()->fetch_assoc();

    $stmtLoc->bind_param("i", $dropoff_id);
    $stmtLoc->execute();
    $d_data = $stmtLoc->get_result()->fetch_assoc();

    $calc = calculateFare($p_data['lat'], $p_data['lon'], $d_data['lat'], $d_data['lon']);
    
    // Total fare = Fare per trike * number of trikes
    $final_fare = $calc['fare'] * $trike_units;
    $code = 'BK' . date('ymdHis') . rand(10,99);

    $stmt = $conn->prepare("INSERT INTO bookings (booking_code, passenger_id, pickup_landmark, dropoff_landmark, notes, pickup_time, Total_Pax, Trike_Units, distance, fare) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisssssidd", $code, $pid, $p_data['name'], $d_data['name'], $notes, $pkup_time, $total_pax, $trike_units, $calc['dist'], $final_fare);

    if ($stmt->execute()) {
        $booking_id = $conn->insert_id;
        
        // QR CONTENT: Complete Details
        $qr_content = "BOOKING INFO\nCode: $code\nFrom: {$p_data['name']}\nTo: {$d_data['name']}\nPax: $total_pax\nTrikes: $trike_units\nTotal Fare: PHP " . number_format($final_fare, 2);

        $qr_path = "../qr-code/$code.png";
        if(!file_exists('../qr-code')) mkdir('../qr-code', 0777, true);
        require_once '../vendor/phpqrcode/qrlib.php';
        QRcode::png($qr_content, $qr_path, QR_ECLEVEL_L, 5);

        header("Location: qr.php?id=$booking_id");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Booking</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="card">
        <h2>New Booking</h2>

        <div class="fare-guide">
            <strong>Fare Rate:</strong> ₱25.00 (1st km) + ₱5.00/km.<br>
            <strong>Capacity:</strong> Max 4 passengers per tricycle.
        </div>

        <form method="POST" id="bookingForm">
            <label>Pickup Time</label>
            <input type="datetime-local" name="pickup_time" required class="form-control">

            <label>Pickup Landmark</label>
            <select name="pickup" id="pickup" required class="form-control location-input">
                <option value="" data-lat="0" data-lon="0">-- Select Pickup --</option>
                <?php foreach($landmarks as $lm): ?>
                    <option value="<?= $lm['id'] ?>" data-lat="<?= $lm['lat'] ?>" data-lon="<?= $lm['lon'] ?>"><?= $lm['name'] ?></option>
                <?php endforeach; ?>
            </select>

            <label>Drop-off Landmark</label>
            <select name="dropoff" id="dropoff" required class="form-control location-input">
                <option value="" data-lat="0" data-lon="0">-- Select Drop-off --</option>
                <?php foreach($landmarks as $lm): ?>
                    <option value="<?= $lm['id'] ?>" data-lat="<?= $lm['lat'] ?>" data-lon="<?= $lm['lon'] ?>"><?= $lm['name'] ?></option>
                <?php endforeach; ?>
            </select>

            <label>Total Passengers</label>
            <input type="number" name="total_pax" id="total_pax" min="1" value="1" required class="form-control location-input">

            <div id="fare-display-box" class="fare-box" style="display:none;">
                <div style="margin-bottom: 5px;">
                    <span class="badge" id="trike-count">1 Tricycle</span>
                    <span class="badge" id="dist-count">0 km</span>
                </div>
                <h3>Estimated Fare: ₱<span id="fare-val">0.00</span></h3>
                <small id="fare-breakdown" style="color: #666;"></small>
            </div>

            <label>Notes (optional)</label>
            <textarea name="notes" class="form-control" style="height:60px;"></textarea>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Confirm Booking</button>
        </form>
    </div>
</div>

<style>
    .form-control { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
    .fare-guide { background: #e7f3ff; padding: 12px; border-radius: 5px; border-left: 5px solid #2196F3; margin-bottom: 15px; font-size: 0.85em; }
    .fare-box { background: #f0fdf4; padding: 15px; border: 2px solid #bbf7d0; text-align: center; margin: 15px 0; border-radius: 8px; }
    .fare-box h3 { color: #15803d; margin: 10px 0; font-size: 1.5em; }
    .badge { background: #15803d; color: white; padding: 3px 10px; border-radius: 20px; font-size: 0.75em; margin: 0 2px; }
</style>

<script>
// Trigger calculation when location or pax changes
document.querySelectorAll('.location-input').forEach(element => {
    element.addEventListener('input', calculateLogic);
});

function calculateLogic() {
    const pickup = document.querySelector('#pickup');
    const dropoff = document.querySelector('#dropoff');
    const pax = parseInt(document.querySelector('#total_pax').value) || 1;
    
    // Auto-calculate trikes: 1 per 4 pax
    const trikeCount = Math.ceil(pax / 4);

    const lat1 = parseFloat(pickup.options[pickup.selectedIndex].dataset.lat);
    const lon1 = parseFloat(pickup.options[pickup.selectedIndex].dataset.lon);
    const lat2 = parseFloat(dropoff.options[dropoff.selectedIndex].dataset.lat);
    const lon2 = parseFloat(dropoff.options[dropoff.selectedIndex].dataset.lon);

    if (lat1 && lat2) {
        // Haversine
        const R = 6371; 
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        const distance = R * c;

        // Computation
        let farePerTrike = (distance <= 1) ? 25 : 25 + ((distance - 1) * 5);
        let totalFare = farePerTrike * trikeCount;

        // Display
        document.getElementById('fare-display-box').style.display = 'block';
        document.getElementById('trike-count').innerText = `${trikeCount} Tricycle${trikeCount > 1 ? 's' : ''}`;
        document.getElementById('dist-count').innerText = `${distance.toFixed(2)} km`;
        document.getElementById('fare-val').innerText = totalFare.toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('fare-breakdown').innerText = `₱${farePerTrike.toFixed(2)} per trike x ${trikeCount}`;
    } else {
        document.getElementById('fare-display-box').style.display = 'none';
    }
}
</script>
</body>
</html>