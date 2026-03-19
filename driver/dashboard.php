<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// 1. Siguraduhin na Driver lamang ang makaka-access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../login.php");
    exit;
}

$driver_id = $_SESSION['user_id'];
$today_date = date('Y-m-d');

// 2. Kumuha ng User Data at Stats gamit ang LEFT JOIN
// Ginagamit natin ang IFNULL para hindi mag-error kung wala pang record sa driver_stats
$query = "SELECT u.name, u.status, u.profile, 
                 IFNULL(s.today_earnings, 0.00) as today_earnings, 
                 IFNULL(s.lifetime_earnings, 0.00) as lifetime_earnings, 
                 IFNULL(s.total_completed_trips, 0) as total_trips,
                 s.last_update 
          FROM users u 
          LEFT JOIN driver_stats s ON u.id = s.driver_id 
          WHERE u.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

// 3. Logic para sa Automatic Record Creation at Daily Reset (24 Hours)
if (!$data || $data['last_update'] === null) {
    // Kung wala pang record sa driver_stats, gawan natin
    $init = $conn->prepare("INSERT INTO driver_stats (driver_id, last_update) VALUES (?, ?)");
    $init->bind_param("is", $driver_id, $today_date);
    $init->execute();
    
    $today_earnings = 0.00;
    $total_trips = 0;
} else if ($data['last_update'] !== $today_date) {
    // Kung magkaiba ang petsa, i-reset ang daily earnings sa database
    $reset = $conn->prepare("UPDATE driver_stats SET today_earnings = 0.00, last_update = ? WHERE driver_id = ?");
    $reset->bind_param("si", $today_date, $driver_id);
    $reset->execute();
    
    $today_earnings = 0.00;
    $total_trips = $data['total_trips'];
} else {
    // Normal state: same day update
    $today_earnings = $data['today_earnings'];
    $total_trips = $data['total_trips'];
}

$driver_name    = $data['name'] ?? 'Driver';
$current_status = $data['status'] ?? 'offline'; 
$profile_pic    = !empty($data['profile']) 
                  ? '../uploads/drivers_profile/' . $data['profile'] 
                  : '../assets/default-driver.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard | SmartTrike</title>
    <?php include '../includes/header.php'; ?>
    <style>
        @font-face {
            font-family: 'NaruMonoDemo';
            src: url('../assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
        }

        :root {
            --primary: #4f46e5;
            --online: #10b981;
            --busy: #f59e0b;
            --offline: #64748b;
        }

        body {
            font-family: 'NaruMonoDemo', monospace !important;
            background-color: #f1f5f9;
            margin: 0;
            color: #1e293b;
        }

        .container { 
            max-width: 500px; 
            margin: 0 auto; 
            padding: 1.5rem; 
        }

        .header-card {
            background: linear-gradient(135deg, #1e1b4b, #4f46e5);
            color: white;
            padding: 2.5rem 1.5rem;
            border-radius: 35px;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(79, 70, 229, 0.4);
            margin-bottom: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 5px solid rgba(255,255,255,0.2);
            object-fit: cover;
            background: white;
            margin-bottom: 1rem;
        }

        .indicator-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 99px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 10px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .dot { height: 10px; width: 10px; border-radius: 50%; display: inline-block; }
        .dot-online { background-color: var(--online); }
        .dot-busy { background-color: var(--busy); }
        .dot-offline { background-color: var(--offline); }

        .card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .card-title {
            font-size: 0.75rem;
            font-weight: bold;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 1.2rem;
            display: block;
            text-align: center;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .btn-status {
            border: 1px solid #e2e8f0;
            padding: 12px 5px;
            border-radius: 15px;
            font-family: 'NaruMonoDemo', monospace;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            font-size: 0.75rem;
            background: #f8fafc;
            color: #64748b;
        }

        .btn-status.active-online { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .btn-status.active-busy { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .btn-status.active-offline { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }

        .btn-status:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

        .stats-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .stat-item { text-align: center; padding: 1.2rem; background: #f8fafc; border-radius: 20px; }
        .stat-val { font-size: 1.4rem; font-weight: bold; color: var(--primary); display: block; }
        .stat-lbl { font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; margin-top: 5px; }

        .lifetime-footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
            font-size: 0.7rem;
            color: #94a3b8;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-card">
        <img src="<?= $profile_pic ?>" class="profile-pic" alt="Driver Profile">
        <h2 style="margin:0;"><?= htmlspecialchars($driver_name) ?></h2>
        <div class="indicator-pill">
            <span class="dot dot-<?= $current_status ?>"></span>
            <?= strtoupper($current_status) ?>
        </div>
    </div>

    <div class="card">
        <span class="card-title">Select Availability</span>
        <div class="status-grid">
            <button onclick="updateStatus('online')" class="btn-status <?= $current_status == 'online' ? 'active-online' : '' ?>">ONLINE</button>
            <button onclick="updateStatus('busy')" class="btn-status <?= $current_status == 'busy' ? 'active-busy' : '' ?>">BUSY</button>
            <button onclick="updateStatus('offline')" class="btn-status <?= $current_status == 'offline' ? 'active-offline' : '' ?>">OFFLINE</button>
        </div>
    </div>

    <div class="card">
        <span class="card-title">Performance Today</span>
        <div class="stats-row">
            <div class="stat-item">
                <span class="stat-val"><?= $total_trips ?></span>
                <span class="stat-lbl">Total Trips</span>
            </div>
            <div class="stat-item">
                <span class="stat-val">₱<?= number_format($today_earnings, 2) ?></span>
                <span class="stat-lbl">Today's Earnings</span>
            </div>
        </div>
        <div class="lifetime-footer">
            LIFETIME EARNINGS: ₱<?= number_format($data['lifetime_earnings'] ?? 0, 2) ?>
        </div>
    </div>
</div>

<script>
function updateStatus(val) {
    const btns = document.querySelectorAll('.btn-status');
    btns.forEach(b => b.style.opacity = '0.5');

    fetch('api/update-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'status=' + val
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert("Error: " + data.message);
            btns.forEach(b => b.style.opacity = '1');
        }
    })
    .catch(err => {
        alert("Server error.");
        btns.forEach(b => b.style.opacity = '1');
    });
}
</script>

</body>
</html>