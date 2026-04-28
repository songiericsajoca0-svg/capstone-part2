<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../dashboard.php");
    exit;
}

$driver_name = $_SESSION['name'] ?? $_SESSION['name'] ?? 'Driver';
$driver_id = $_SESSION['user_id'];

// Create points table if not exists
$conn->query("
CREATE TABLE IF NOT EXISTS driver_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    total_points INT DEFAULT 0,
    earned_points INT DEFAULT 0,
    claimed_points INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_driver (driver_id),
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Create points history table
$conn->query("
CREATE TABLE IF NOT EXISTS points_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    points INT NOT NULL,
    type ENUM('EARNED', 'CLAIMED') NOT NULL,
    reference_id INT NULL,
    reference_type VARCHAR(50) NULL,
    item_name VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Initialize driver points if not exists
$init_points = $conn->prepare("
    INSERT IGNORE INTO driver_points (driver_id, total_points, earned_points, claimed_points) 
    SELECT id, 0, 0, 0 FROM users WHERE id = ? AND role = 'driver'
");
$init_points->bind_param("i", $driver_id);
$init_points->execute();

// Get driver profile information (including driver_toda)
$profile_query = $conn->prepare("
    SELECT 
        id,
        name,
        email,
        contact,
        profile,
        role,
        created_at,
        status,
        driver_toda
    FROM users 
    WHERE id = ? AND role = 'driver'
");
$profile_query->bind_param("i", $driver_id);
$profile_query->execute();
$driver_profile = $profile_query->get_result()->fetch_assoc();

// Get driver points
$points_query = $conn->prepare("
    SELECT total_points, earned_points, claimed_points 
    FROM driver_points WHERE driver_id = ?
");
$points_query->bind_param("i", $driver_id);
$points_query->execute();
$points_data = $points_query->get_result()->fetch_assoc();

$current_points = $points_data['total_points'] ?? 0;
$earned_points_total = $points_data['earned_points'] ?? 0;
$claimed_points_total = $points_data['claimed_points'] ?? 0;

// Get completed trips count from bookings
$completed_query = $conn->prepare("
    SELECT COUNT(*) as completed_count 
    FROM bookings 
    WHERE driver_id = ? AND status = 'COMPLETED'
");
$completed_query->bind_param("i", $driver_id);
$completed_query->execute();
$completed_trips = $completed_query->get_result()->fetch_assoc()['completed_count'];

// Calculate points from completed trips (1 point per completed trip)
$expected_points = $completed_trips;
$needs_sync = ($current_points != $expected_points);

// Sync points if needed
if ($needs_sync && $expected_points > $current_points) {
    $points_to_add = $expected_points - $current_points;
    $update_points = $conn->prepare("
        UPDATE driver_points 
        SET total_points = total_points + ?, earned_points = earned_points + ? 
        WHERE driver_id = ?
    ");
    $update_points->bind_param("iii", $points_to_add, $points_to_add, $driver_id);
    $update_points->execute();
    
    // Add to history
    $add_history = $conn->prepare("
        INSERT INTO points_history (driver_id, points, type, reference_type) 
        VALUES (?, ?, 'EARNED', 'TRIP_COMPLETION')
    ");
    $add_history->bind_param("ii", $driver_id, $points_to_add);
    $add_history->execute();
    
    $current_points = $expected_points;
}

// Get driver stats
$stats_query = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as completed_count,
        COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN fare_amount ELSE 0 END), 0) as total_earnings
    FROM bookings 
    WHERE driver_id = ?
");
$stats_query->bind_param("i", $driver_id);
$stats_query->execute();
$stats_result = $stats_query->get_result()->fetch_assoc();
$total_trips = $stats_result['completed_count'];
$total_earnings = $stats_result['total_earnings'];

// Get active trip count
$active_query = $conn->prepare("
    SELECT COUNT(*) as cnt FROM bookings 
    WHERE driver_id = ? AND status IN ('ACCEPTED', 'PASSENGER PICKED UP')
");
$active_query->bind_param("i", $driver_id);
$active_query->execute();
$active_count = $active_query->get_result()->fetch_assoc()['cnt'];

// Points Rewards Catalogue
$rewards = [
    ['name' => 'Cap', 'points' => 30, 'image' => 'cap.png', 'stock' => 50],
    ['name' => 'Tumbler', 'points' => 100, 'image' => 'tumbler.png', 'stock' => 30],
    ['name' => 'Payong', 'points' => 100, 'image' => 'payong.png', 'stock' => 30],
    ['name' => 'Shirt', 'points' => 100, 'image' => 'shirt.png', 'stock' => 25],
    ['name' => 'Power Bank', 'points' => 150, 'image' => 'power.png', 'stock' => 15],
    ['name' => 'Wireless Speaker', 'points' => 180, 'image' => 'speaker.png', 'stock' => 10]
];

$profile_image = !empty($driver_profile['profile']) ? '../uploads/drivers_profile/' . $driver_profile['profile'] : '../assets/images/default-avatar.png';

// Get TODA name if driver_toda exists (assuming you have a todas table)
$toda_name = '';
if (!empty($driver_profile['driver_toda'])) {
    $toda_query = $conn->prepare("SELECT toda_name FROM todas WHERE id = ?");
    $toda_query->bind_param("i", $driver_profile['driver_toda']);
    $toda_query->execute();
    $toda_result = $toda_query->get_result();
    if ($toda_row = $toda_result->fetch_assoc()) {
        $toda_name = $toda_row['toda_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard | GoTrike</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --primary-light: #7f9cf5;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        body {
            background: var(--bg);
            margin: 0;
            color: #1e293b;
            min-height: 100vh;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        /* Header Card */
        .header-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 28px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
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

        .dashboard-header-content {
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .driver-avatar {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 3px solid rgba(255,255,255,0.3);
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .driver-avatar:hover {
            transform: scale(1.05);
        }

        .driver-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .driver-info h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: bold;
        }

        .driver-info p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
        }

        .driver-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.7rem;
            margin-top: 0.5rem;
        }
        
        .toda-badge {
            display: inline-block;
            background: rgba(255,255,255,0.3);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.7rem;
            margin-top: 0.5rem;
            margin-left: 0.5rem;
            font-weight: bold;
        }
        
        .toda-badge i {
            margin-right: 4px;
        }

        /* Points Card */
        .points-card {
            background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            border-radius: 20px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .points-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .points-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .points-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }

        .points-text h3 {
            margin: 0;
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .points-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 0;
            line-height: 1;
        }

        .points-sub {
            font-size: 0.7rem;
            opacity: 0.8;
        }

        .redeem-btn {
            background: white;
            color: #f59e0b;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .redeem-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.2rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 0.8rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-card.trips .stat-icon { background: #e0e7ff; color: #667eea; }
        .stat-card.earnings .stat-icon { background: #fed7aa; color: #f59e0b; }
        .stat-card.points-stat .stat-icon { background: #d1fae5; color: #10b981; }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }

        .stat-label {
            font-size: 0.7rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Rewards Dropdown Section */
        .rewards-section {
            background: white;
            border-radius: 28px;
            margin-bottom: 2rem;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .rewards-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .rewards-header:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46a0 100%);
        }

        .rewards-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dropdown-icon {
            transition: transform 0.3s ease;
            font-size: 1.2rem;
        }

        .dropdown-icon.open {
            transform: rotate(180deg);
        }

        .rewards-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
            background: #f8fafc;
        }

        .rewards-content.open {
            max-height: 800px;
        }

        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem;
        }

        .reward-item {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        .reward-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .reward-item.claimable {
            border-color: #10b981;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        }

        .reward-item.not-claimable {
            opacity: 0.6;
        }

        .reward-image {
            width: 100px;
            height: 100px;
            margin: 0 auto 0.8rem;
            background: #f1f5f9;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .reward-image:hover {
            transform: scale(1.05);
        }

        .reward-image img {
            max-width: 80%;
            max-height: 80%;
            object-fit: contain;
        }

        .reward-name {
            font-weight: bold;
            margin: 0.5rem 0;
        }

        .reward-points {
            color: #f59e0b;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .claim-status {
            font-size: 0.7rem;
            margin-top: 0.5rem;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            display: inline-block;
        }

        .claim-status.available {
            background: #d1fae5;
            color: #065f46;
        }

        .claim-status.unavailable {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Active Trip Banner */
        .active-trip-banner {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 20px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .active-trip-banner:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .view-trip-btn {
            background: #f59e0b;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: bold;
            cursor: pointer;
        }

        /* Main Card */
        .main-card {
            background: white;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.1);
        }

        .card-section {
            padding: 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
            color: #667eea;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .booking-card {
            background: #f8fafc;
            border-radius: 20px;
            margin-bottom: 1rem;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .booking-header {
            padding: 1rem 1.2rem;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .booking-footer {
            padding: 0.8rem 1.2rem;
            background: white;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 0.8rem;
        }

        .btn-accept {
            flex: 1;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 0.7rem;
            border-radius: 12px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-decline {
            flex: 1;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            padding: 0.7rem;
            border-radius: 12px;
            font-weight: bold;
            cursor: pointer;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }

        .refresh-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            width: 55px;
            height: 55px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .refresh-btn:hover {
            transform: rotate(180deg);
        }

        /* Profile Modal */
        .profile-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .profile-modal-content {
            background: white;
            border-radius: 28px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .profile-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 28px 28px 0 0;
            position: relative;
        }

        .profile-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
        }

        .profile-modal-body {
            padding: 2rem;
        }

        .profile-image-large {
            width: 150px;
            height: 150px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #667eea;
        }

        .profile-image-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .edit-profile-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 12px;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
        }
        
        .profile-info-item {
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .profile-info-label {
            font-size: 0.7rem;
            color: #6b7280;
            margin-bottom: 0.2rem;
        }

        /* Image View Modal */
        .image-view-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 3000;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }

        .image-view-content {
            max-width: 90%;
            max-height: 90%;
            text-align: center;
        }

        .image-view-content img {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .image-view-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
            background: none;
            border: none;
            z-index: 3001;
        }

        .image-view-close:hover {
            color: #f59e0b;
        }

        @media (max-width: 768px) {
            .dashboard-header-content {
                flex-direction: column;
                text-align: center;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .rewards-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Header Card -->
    <div class="header-card">
        <div class="dashboard-header-content">
            <div class="driver-avatar" onclick="showProfileModal()">
                <?php if (!empty($driver_profile['profile']) && file_exists('../uploads/drivers_profile/' . $driver_profile['profile'])): ?>
                    <img src="<?= htmlspecialchars($profile_image) ?>" alt="Driver Profile">
                <?php else: ?>
                    <div class="avatar-placeholder">🚗</div>
                <?php endif; ?>
            </div>
            <div class="driver-info">
                <h2>GoTrike Driver</h2>
                <p>Welcome, <?= htmlspecialchars($driver_profile['name'] ?? $driver_name) ?></p>
                <div>
                    <span class="driver-badge">✓ Online & Ready</span>
                    <?php if (!empty($toda_name)): ?>
                    <span class="toda-badge">
                        <i>🚕</i> <?= htmlspecialchars($toda_name) ?>
                    </span>
                    <?php elseif (!empty($driver_profile['driver_toda'])): ?>
                    <span class="toda-badge">
                        <i>🚕</i> TODA ID: <?= htmlspecialchars($driver_profile['driver_toda']) ?>
                    </span>
                    <?php else: ?>
                    <span class="toda-badge">
                        <i>⚠️</i> No TODA Assigned
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Points Card -->
    <div class="points-card" onclick="toggleRewards()">
        <div class="points-info">
            <div class="points-icon">⭐</div>
            <div class="points-text">
                <h3>YOUR POINTS</h3>
                <div class="points-value" id="currentPoints"><?= number_format($current_points) ?></div>
                <div class="points-sub">Earned: <?= number_format($earned_points_total) ?> | Claimed: <?= number_format($claimed_points_total) ?></div>
            </div>
        </div>
        <button class="redeem-btn" onclick="event.stopPropagation(); toggleRewards()">🎁 Redeem Now</button>
    </div>

    <!-- Active Trip Banner -->
    <?php if ($active_count > 0): ?>
    <div class="active-trip-banner" onclick="window.location.href='active-trip.php'">
        <div>
            <strong>🚗 You have an active trip!</strong>
            <p style="margin:0; font-size:0.8rem;">Tap here to view your current trip</p>
        </div>
        <button class="view-trip-btn">View Trip →</button>
    </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card trips">
            <div class="stat-icon">📋</div>
            <div class="stat-value" id="completedCount"><?= $total_trips ?></div>
            <div class="stat-label">Completed Trips</div>
        </div>
        <div class="stat-card earnings">
            <div class="stat-icon">💰</div>
            <div class="stat-value">₱<?= number_format($total_earnings, 0) ?></div>
            <div class="stat-label">Total Earnings</div>
        </div>
        <div class="stat-card points-stat">
            <div class="stat-icon">⭐</div>
            <div class="stat-value"><?= number_format($current_points) ?></div>
            <div class="stat-label">Points per Trip: 1</div>
        </div>
    </div>

    <!-- Rewards Dropdown Section -->
    <div class="rewards-section">
        <div class="rewards-header" onclick="toggleRewards()">
            <h3>🎁 Premium Rewards Catalogue 🎁</h3>
            <span class="dropdown-icon" id="dropdownIcon">▼</span>
        </div>
        <div class="rewards-content" id="rewardsContent">
            <div class="rewards-grid" id="rewardsGrid">
                <?php foreach ($rewards as $reward): ?>
                <div class="reward-item <?= $current_points >= $reward['points'] ? 'claimable' : 'not-claimable' ?>">
                    <div class="reward-image" onclick="event.stopPropagation(); viewFullImage('<?= $reward['image'] ?>', '<?= addslashes($reward['name']) ?>')">
                        <img src="../assets/images/<?= htmlspecialchars($reward['image']) ?>" 
                             alt="<?= htmlspecialchars($reward['name']) ?>"
                             onerror="this.src='../assets/images/default-item.png'">
                    </div>
                    <div class="reward-name"><?= htmlspecialchars($reward['name']) ?></div>
                    <div class="reward-points">⭐ <?= number_format($reward['points']) ?> points</div>
                    <span class="claim-status <?= $current_points >= $reward['points'] ? 'available' : 'unavailable' ?>">
                        <?= $current_points >= $reward['points'] ? '✓ Available' : '❌ Need ' . number_format($reward['points'] - $current_points) . ' more' ?>
                    </span>
                    <button class="redeem-btn" style="margin-top: 10px; padding: 5px 10px; font-size: 0.7rem;" 
                            onclick="event.stopPropagation(); claimReward('<?= addslashes($reward['name']) ?>', <?= $reward['points'] ?>, '<?= $reward['image'] ?>')">
                        🎁 Claim
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; padding: 0 1.5rem 1.5rem; font-size: 0.7rem; color: #94a3b8;">
                💡 1 completed trip = 1 point. Keep driving to earn more points!
            </div>
        </div>
    </div>

    <!-- Bookings Section -->
    <div class="main-card">
        <div class="card-section">
            <div class="section-title">
                📋 Available Bookings (PENDING)
                <span style="background:#667eea; color:white; padding:2px 8px; border-radius:20px; font-size:0.7rem; margin-left:auto;" id="bookingCount">0</span>
            </div>
            <div id="bookingsList">
                <div class="loading">Loading bookings...</div>
            </div>
        </div>
    </div>
    
    <button class="refresh-btn" onclick="refreshAll()">⟳</button>
</div>

<!-- Profile Modal -->
<div id="profileModal" class="profile-modal" onclick="closeModalOnOutsideClick(event)">
    <div class="profile-modal-content">
        <div class="profile-modal-header">
            <h3>Driver Profile</h3>
            <button class="profile-modal-close" onclick="closeProfileModal()">×</button>
        </div>
        <div class="profile-modal-body">
            <div class="profile-image-large">
                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Driver Profile">
            </div>
            <div class="profile-info-item">
                <div class="profile-info-label">Full Name</div>
                <div><?= htmlspecialchars($driver_profile['name'] ?? 'N/A') ?></div>
            </div>
            <div class="profile-info-item">
                <div class="profile-info-label">Email</div>
                <div><?= htmlspecialchars($driver_profile['email'] ?? 'N/A') ?></div>
            </div>
            <div class="profile-info-item">
                <div class="profile-info-label">Contact</div>
                <div><?= htmlspecialchars($driver_profile['contact'] ?? 'N/A') ?></div>
            </div>
            <div class="profile-info-item">
                <div class="profile-info-label">TODA Affiliation</div>
                <div><?= !empty($toda_name) ? htmlspecialchars($toda_name) : ( !empty($driver_profile['driver_toda']) ? 'TODA ID: ' . htmlspecialchars($driver_profile['driver_toda']) : 'Not Assigned' ) ?></div>
            </div>
            <div class="profile-info-item">
                <div class="profile-info-label">Member Since</div>
                <div><?= date('F d, Y', strtotime($driver_profile['created_at'])) ?></div>
            </div>
            <button class="edit-profile-btn" onclick="editProfile()">Edit Profile</button>
        </div>
    </div>
</div>

<!-- Image View Modal -->
<div id="imageViewModal" class="image-view-modal" onclick="closeImageViewModal()">
    <button class="image-view-close" onclick="closeImageViewModal()">×</button>
    <div class="image-view-content" onclick="event.stopPropagation()">
        <img id="fullImageView" src="" alt="">
        <p id="imageViewCaption" style="color: white; margin-top: 1rem; font-size: 1rem;"></p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    let autoRefreshInterval;
    let currentPoints = <?= $current_points ?>;
    
    function toggleRewards() {
        const content = document.getElementById('rewardsContent');
        const icon = document.getElementById('dropdownIcon');
        content.classList.toggle('open');
        icon.classList.toggle('open');
    }
    
    function viewFullImage(imageName, itemName) {
        const modal = document.getElementById('imageViewModal');
        const img = document.getElementById('fullImageView');
        const caption = document.getElementById('imageViewCaption');
        
        img.src = '../assets/images/' + imageName;
        caption.innerHTML = itemName;
        modal.style.display = 'flex';
        
        // Handle image loading error
        img.onerror = function() {
            img.src = '../assets/images/default-item.png';
        };
    }
    
    function closeImageViewModal() {
        document.getElementById('imageViewModal').style.display = 'none';
    }
    
    function claimReward(itemName, pointsRequired, imageName) {
        if (currentPoints >= pointsRequired) {
            Swal.fire({
                title: '🎁 CLAIM YOUR REWARD! 🎁',
                html: `
                    <div style="text-align: center;">
                        <div style="display: flex; justify-content: center; margin-bottom: 1rem;">
                            <img src="../assets/images/${imageName}" 
                                 style="width: 150px; height: 150px; object-fit: contain; border-radius: 16px; cursor: pointer; box-shadow: 0 5px 20px rgba(0,0,0,0.2);" 
                                 onclick="window.open('../assets/images/${imageName}', '_blank')"
                                 onerror="this.src='../assets/images/default-item.png'">
                        </div>
                        <h2 style="color: #10b981; margin: 0.5rem 0;">${itemName}</h2>
                        <p style="font-size: 1.1rem;">You are about to claim this reward for <strong style="color: #f59e0b;">${pointsRequired} points</strong>!</p>
                        <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); padding: 1rem; border-radius: 16px; margin: 1rem 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                <span>⭐ <strong>Current Points:</strong> ${currentPoints}</span>
                                <span>➡️</span>
                                <span>⭐ <strong>Points After Claim:</strong> ${currentPoints - pointsRequired}</span>
                            </div>
                        </div>
                        <div style="background: #dbeafe; padding: 1rem; border-radius: 16px; margin-top: 0.5rem;">
                            <p style="margin: 0; color: #1e40af;">
                                📍 <strong>Go to your Admin/Toda to claim this item!</strong>
                            </p>
                            <p style="margin: 0.5rem 0 0; font-size: 0.7rem; color: #3b82f6;">
                                Click the image to view full size
                            </p>
                        </div>
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: '✅ Yes, Claim!',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                width: '500px'
            }).then((result) => {
                if (result.isConfirmed) {
                    processClaim(itemName, pointsRequired);
                }
            });
        } else {
            Swal.fire({
                title: '❌ Not Enough Points',
                html: `
                    <div style="text-align: center;">
                        <div style="font-size: 4rem;">😢</div>
                        <p>You need <strong style="color: #f59e0b;">${pointsRequired - currentPoints} more points</strong> to claim this ${itemName}!</p>
                        <div style="background: #fef3c7; padding: 1rem; border-radius: 12px; margin: 1rem 0;">
                            💡 <strong>1 completed trip = 1 point</strong>
                        </div>
                        <p>Complete <strong>${pointsRequired - currentPoints}</strong> more trip(s) to earn enough points!</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: 'Got it!',
                confirmButtonColor: '#667eea'
            });
        }
    }
    
    function processClaim(itemName, pointsRequired) {
        Swal.fire({
            title: 'Processing...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        fetch('driver_claim_reward.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'item_name=' + encodeURIComponent(itemName) + '&points=' + pointsRequired
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentPoints = data.remaining_points;
                document.getElementById('currentPoints').textContent = currentPoints.toLocaleString();
                
                Swal.fire({
                    title: '🎉 CLAIM SUCCESSFUL! 🎉',
                    html: `
                        <div style="text-align: center;">
                            <div style="font-size: 4rem;">🎁✨</div>
                            <h2 style="color: #10b981; margin: 0.5rem 0;">Congratulations!</h2>
                            <p>You have successfully claimed <strong style="color: #f59e0b;">${itemName}</strong>!</p>
                            <div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); padding: 1rem; border-radius: 16px; margin: 1rem 0;">
                                <span>⭐ <strong>Remaining Points:</strong> ${currentPoints}</span>
                            </div>
                            <div style="background: #fef3c7; padding: 1rem; border-radius: 16px; margin-top: 0.5rem;">
                                <p style="margin: 0; color: #92400e; font-size: 1.1rem;">
                                    📍 <strong>Go to your Admin/Toda to claim your ${itemName}!</strong>
                                </p>
                                <p style="margin: 0.5rem 0 0; font-size: 0.7rem; color: #b45309;">
                                    Show this confirmation to your Admin
                                </p>
                            </div>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonText: 'Got it!',
                    confirmButtonColor: '#10b981'
                });
                
                updateRewardsUI();
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message || 'Failed to claim reward',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                title: 'Error',
                text: 'Network error: ' + error.message,
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
        });
    }
    
    function updateRewardsUI() {
        const rewards = <?= json_encode($rewards) ?>;
        const grid = document.getElementById('rewardsGrid');
        
        let html = '';
        for (let reward of rewards) {
            const isClaimable = currentPoints >= reward.points;
            html += `
                <div class="reward-item ${isClaimable ? 'claimable' : 'not-claimable'}">
                    <div class="reward-image" onclick="event.stopPropagation(); viewFullImage('${reward.image}', '${reward.name.replace(/'/g, "\\'")}')">
                        <img src="../assets/images/${reward.image}" alt="${reward.name}"
                             onerror="this.src='../assets/images/default-item.png'">
                    </div>
                    <div class="reward-name">${reward.name}</div>
                    <div class="reward-points">⭐ ${reward.points.toLocaleString()} points</div>
                    <span class="claim-status ${isClaimable ? 'available' : 'unavailable'}">
                        ${isClaimable ? '✓ Available' : '❌ Need ' + (reward.points - currentPoints) + ' more'}
                    </span>
                    <button class="redeem-btn" style="margin-top: 10px; padding: 5px 10px; font-size: 0.7rem;" 
                            onclick="event.stopPropagation(); claimReward('${reward.name.replace(/'/g, "\\'")}', ${reward.points}, '${reward.image}')">
                        🎁 Claim
                    </button>
                </div>
            `;
        }
        grid.innerHTML = html;
    }
    
    function loadBookings() {
        fetch('driver_get_bookings.php')
            .then(res => res.json())
            .then(data => {
                const container = document.getElementById('bookingsList');
                const countSpan = document.getElementById('bookingCount');
                countSpan.textContent = data.count;
                
                if (!data.success || data.bookings.length === 0) {
                    container.innerHTML = '<div class="empty-state">📭 No PENDING bookings at this time</div>';
                    return;
                }
                
                let html = '';
                for (let booking of data.bookings) {
                    html += `
                        <div class="booking-card">
                            <div class="booking-header">
                                <div><strong>👤 ${escapeHtml(booking.passenger_name || 'Guest')}</strong><br><small>#${escapeHtml(booking.booking_code)}</small></div>
                                <span style="background:#fef3c7; padding:2px 8px; border-radius:12px;">PENDING</span>
                            </div>
                            <div style="padding:1rem;">
                                <div>📍 ${escapeHtml(booking.pickup_landmark || 'Not specified')}</div>
                                <small>PICKUP</small>
                                <div>🏁 ${escapeHtml(booking.dropoff_landmark || 'Not specified')}</div>
                                <small>DROP OFF</small>
                                <div style="margin-top:0.5rem;">👥 ${booking.total_pax || 1} pax | 💰 ₱${parseFloat(booking.fare_amount || 0).toFixed(2)}</div>
                            </div>
                            <div class="booking-footer">
                                <button class="btn-accept" onclick="acceptBooking(${booking.id}, '${escapeHtml(booking.booking_code)}')">✅ Accept</button>
                                <button class="btn-decline" onclick="declineBooking(${booking.id})">✖ Decline</button>
                            </div>
                        </div>
                    `;
                }
                container.innerHTML = html;
            })
            .catch(error => {
                document.getElementById('bookingsList').innerHTML = '<div class="empty-state">⚠️ Error loading bookings</div>';
            });
    }
    
   function loadStats() {
    fetch('driver_get_stats.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('completedCount').textContent = data.completed_count;
                // ❌ REMOVE location.reload()
            }
        })
}
    
    function refreshAll() {
        location.reload();
    }
    
    function acceptBooking(bookingId, bookingCode) {
        Swal.fire({
            title: 'Accept Booking?',
            text: `Accept #${bookingCode}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Yes, Accept'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                
                fetch('driver_accept_booking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'booking_id=' + bookingId
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Accepted!', text: 'Redirecting...', timer: 1500, showConfirmButton: false })
                            .then(() => { window.location.href = 'active-trip.php?id=' + bookingId; });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                    }
                })
                .catch(error => Swal.fire({ icon: 'error', title: 'Error', text: error.message }));
            }
        });
    }
    
    function declineBooking(bookingId) {
        Swal.fire({
            title: 'Decline?',
            text: 'This will remain PENDING for others',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, Decline'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ icon: 'info', title: 'Declined', timer: 1500, showConfirmButton: false });
            }
        });
    }
    
    function showProfileModal() { document.getElementById('profileModal').style.display = 'flex'; }
    function closeProfileModal() { document.getElementById('profileModal').style.display = 'none'; }
    function closeModalOnOutsideClick(event) { if (event.target === document.getElementById('profileModal')) closeProfileModal(); }
    function editProfile() { window.location.href = 'profile.php'; }
    function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
    
    loadBookings();
    loadStats();
    autoRefreshInterval = setInterval(() => { loadBookings(); loadStats(); }, 10000);
</script>

</body>
</html>