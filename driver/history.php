<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Siguraduhin na Driver lamang ang makaka-access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../dashboard.php");
    exit;
}

// Gamitin ang driver_name mula sa session para i-filter ang bookings
$driver_session_name = $_SESSION['name'] ?? '';

// 1. Kunin ang listahan ng transactions (Completed & Cancelled)
// Gagamit tayo ng JOIN para makuha ang Name ng passenger mula sa users table
$query = "
    SELECT b.*, u.name AS passenger_real_name 
    FROM bookings b 
    JOIN users u ON b.passenger_id = u.id 
    WHERE b.driver_name = ? 
    AND b.status IN ('COMPLETED', 'CANCELLED')
    ORDER BY b.created_at DESC 
    LIMIT 50
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $driver_session_name);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Passenger Transactions</title>
    <?php include '../includes/header.php'; ?>
    
    <style>
        @font-face {
            font-family: 'NaruMonoDemo';
            src: url('../assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
        }

        body, div, h2, h3, p, span, strong, table, td, th {
            font-family: 'NaruMonoDemo', monospace !important;
        }

        body { background-color: #f8fafc; color: #1e293b; }

        .container { max-width: 700px; margin: 2rem auto; padding: 0 1rem; }

        .trip-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .status-tag {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 0.7rem;
            padding: 4px 12px;
            border-radius: 6px;
            font-weight: bold;
        }

        .status-completed { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        .passenger-info { margin-bottom: 1rem; }
        .passenger-name { font-size: 1.1rem; color: #1e40af; display: block; }
        
        .route-info {
            background: #f1f5f9;
            padding: 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin: 10px 0;
        }

        .meta-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }

        .unit-badge {
            background: #334155;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
        }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="margin:0;">Transaction History</h2>
        <a href="dashboard.php" style="text-decoration:none; font-size: 0.8rem; color:#64748b;">← Back</a>
    </div>

    <?php if (empty($history)): ?>
        <div style="text-align:center; padding: 3rem; background:white; border-radius:20px;">
            <p style="color:#94a3b8;">No passenger transactions found.</p>
        </div>
    <?php else: ?>
        <?php foreach ($history as $row): ?>
            <div class="trip-card">
                <span class="status-tag <?= $row['status'] === 'COMPLETED' ? 'status-completed' : 'status-cancelled' ?>">
                    <?= $row['status'] ?>
                </span>

                <div class="passenger-info">
                    <span style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">Passenger</span>
                    <strong class="passenger-name"><?= htmlspecialchars($row['passenger_real_name']) ?></strong>
                    <small style="color:#64748b;">Code: <?= $row['booking_code'] ?></small>
                </div>

                <div class="route-info">
                    <div style="margin-bottom: 5px;">
                        <span style="color:#10b981;">●</span> <strong>Pickup:</strong> <?= htmlspecialchars($row['pickup_landmark']) ?>
                    </div>
                    <div>
                        <span style="color:#ef4444;">▼</span> <strong>Dropoff:</strong> <?= htmlspecialchars($row['dropoff_landmark']) ?>
                    </div>
                </div>

                <?php if(!empty($row['notes'])): ?>
                    <p style="font-size: 0.8rem; color: #475569; font-style: italic;">
                        "<?= htmlspecialchars($row['notes']) ?>"
                    </p>
                <?php endif; ?>

                <div class="meta-info">
                    <div>
                        <i class="far fa-calendar"></i> <?= date('M d, Y', strtotime($row['created_at'])) ?><br>
                        <i class="far fa-clock"></i> <?= date('h:i A', strtotime($row['created_at'])) ?>
                    </div>
                    <div style="text-align: right;">
                        Unit: <span class="unit-badge"><?= htmlspecialchars($row['trike_units']) ?></span><br>
                        Pax: <strong><?= htmlspecialchars($row['total_pax']) ?></strong>
                    </div>
                </div>

                <?php if($row['status'] === 'COMPLETED'): ?>
                    <div style="margin-top: 10px; font-size: 0.7rem; color: #10b981; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 8px;">
                        Trip Duration: <?= $row['pickup_time'] ?> to <?= $row['dropoff_time'] ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>