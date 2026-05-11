<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// 1. Siguraduhin na Driver lamang ang makaka-access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../login.php");
    exit;
}

$driver_id = $_SESSION['user_id'];
$driver_name = $_SESSION['name'] ?? '';
$today_date = date('Y-m-d');

// 2. Kunin ang active booking (assigned to this driver)
$active_booking = null;
$active_query = $conn->prepare("
    SELECT b.*, u.name AS passenger_name 
    FROM bookings b 
    JOIN users u ON b.passenger_id = u.id 
    WHERE b.driver_id = ? AND b.status IN ('ASSIGNED', 'PASSENGER PICKED UP', 'IN TRANSIT')
    ORDER BY b.created_at DESC 
    LIMIT 1
");
$active_query->bind_param("i", $driver_id);
$active_query->execute();
$active_result = $active_query->get_result();
if ($active_result->num_rows > 0) {
    $active_booking = $active_result->fetch_assoc();
}

// 3. Kunin ang pending booking count para sa notification
$pending_query = $conn->prepare("
    SELECT COUNT(*) as pending_count 
    FROM bookings 
    WHERE status = 'PENDING' 
    AND driver_id IS NULL
");
$pending_query->execute();
$pending_result = $pending_query->get_result();
$pending_count = $pending_result->fetch_assoc()['pending_count'] ?? 0;

// 4. Get ALL completed trips from bookings table (for lifetime stats)
$lifetime_query = $conn->prepare("
    SELECT COUNT(*) as total_trips, SUM(fare_amount) as total_earnings
    FROM bookings 
    WHERE driver_id = ? AND status = 'COMPLETED'
");
$lifetime_query->bind_param("i", $driver_id);
$lifetime_query->execute();
$lifetime_stats = $lifetime_query->get_result()->fetch_assoc();

$total_lifetime_trips = $lifetime_stats['total_trips'] ?? 0;
$lifetime_earnings = $lifetime_stats['total_earnings'] ?? 0;

// 5. Get today's completed trips from bookings table (real-time)
$today_completed_query = $conn->prepare("
    SELECT COUNT(*) as today_trips, SUM(fare_amount) as today_earnings
    FROM bookings 
    WHERE driver_id = ? 
    AND status = 'COMPLETED' 
    AND DATE(created_at) = ?
");
$today_completed_query->bind_param("is", $driver_id, $today_date);
$today_completed_query->execute();
$today_completed_stats = $today_completed_query->get_result()->fetch_assoc();

$today_trips = $today_completed_stats['today_trips'] ?? 0;
$today_earnings = $today_completed_stats['today_earnings'] ?? 0;

// 6. Update or insert driver_stats table with today's stats
$update_stats_query = $conn->prepare("
    INSERT INTO driver_stats (driver_id, today_trips, today_earnings, lifetime_trips, lifetime_earnings, last_update_date, last_update)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
    today_trips = VALUES(today_trips),
    today_earnings = VALUES(today_earnings),
    lifetime_trips = VALUES(lifetime_trips),
    lifetime_earnings = VALUES(lifetime_earnings),
    last_update = NOW()
");
$update_stats_query->bind_param("iiddis", 
    $driver_id, 
    $today_trips, 
    $today_earnings, 
    $total_lifetime_trips, 
    $lifetime_earnings, 
    $today_date
);
$update_stats_query->execute();

// 7. Check if we need to reset today's stats (new day)
$check_reset_query = $conn->prepare("
    SELECT last_update_date FROM driver_stats 
    WHERE driver_id = ? ORDER BY last_update_date DESC LIMIT 1
");
$check_reset_query->bind_param("i", $driver_id);
$check_reset_query->execute();
$last_date_result = $check_reset_query->get_result()->fetch_assoc();

// If last update date is not today, today's stats will automatically be from today's completed trips
if ($last_date_result && $last_date_result['last_update_date'] != $today_date) {
    // Today's stats are fresh from today's completed trips
    $today_trips = $today_completed_stats['today_trips'] ?? 0;
    $today_earnings = $today_completed_stats['today_earnings'] ?? 0;
}

// 8. Get driver status
$status_query = $conn->prepare("SELECT status, profile FROM users WHERE id = ?");
$status_query->bind_param("i", $driver_id);
$status_query->execute();
$user_data = $status_query->get_result()->fetch_assoc();

$current_status = $user_data['status'] ?? 'offline';
$profile_pic = !empty($user_data['profile']) 
               ? '../uploads/drivers_profile/' . $user_data['profile'] 
               : '../assets/default-driver.png';

// 9. Calculate average per trip for today
$avg_per_trip = $today_trips > 0 ? $today_earnings / $today_trips : 0;

// 10. Get last booking check timestamp from session or create new
if (!isset($_SESSION['last_booking_check'])) {
    $_SESSION['last_booking_check'] = date('Y-m-d H:i:s');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard | GoTrike</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        @font-face {
            font-family: 'NaruMonoDemo';
            src: url('../assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
        }

        * {
            font-family: 'NaruMonoDemo', monospace !important;
        }

        i, .fas, .far, .fab, .fa {
            font-family: "Font Awesome 6 Free" !important;
            font-weight: 400;
        }

        .fas, .fa-solid {
            font-weight: 900 !important;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --online: #10b981;
            --busy: #f59e0b;
            --offline: #94a3b8;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .dashboard-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        /* Header Card - Centered */
        .header-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 28px;
            text-align: center;
            box-shadow: 0 20px 40px -15px rgba(102,126,234,0.3);
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .header-card::before {
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

        .profile-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
        }

        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid rgba(255,255,255,0.3);
            object-fit: cover;
            background: white;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }

        .profile-pic:hover {
            transform: scale(1.05);
        }

        .driver-name {
            margin: 0;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .indicator-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 10px;
            background: rgba(255,255,255,0.2);
        }

        .dot { height: 8px; width: 8px; border-radius: 50%; display: inline-block; }
        .dot-online { background-color: var(--online); }
        .dot-busy { background-color: var(--busy); }
        .dot-offline { background-color: var(--offline); }

        /* Active Booking Card */
        .active-booking-card {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 24px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #f59e0b;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        .active-booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .active-badge {
            background: #f59e0b;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .booking-code {
            font-weight: bold;
            color: #92400e;
        }

        .booking-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem;
            margin-bottom: 1rem;
        }

        .booking-detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
        }

        .booking-detail-item i {
            color: #f59e0b;
            width: 20px;
        }

        .btn-action {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-scan, .btn-transit, .btn-complete {
            flex: 1;
            padding: 0.7rem;
            border: none;
            border-radius: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.75rem;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-scan {
            background: #10b981;
            color: white;
        }

        .btn-scan:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-transit {
            background: #3b82f6;
            color: white;
        }

        .btn-transit:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .btn-complete {
            background: #8b5cf6;
            color: white;
        }

        .btn-complete:hover {
            background: #7c3aed;
            transform: translateY(-2px);
        }

        /* Status Card */
        .status-card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .card-title {
            font-size: 0.7rem;
            font-weight: bold;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
            text-align: center;
            display: block;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.8rem;
        }

        .btn-status {
            border: 2px solid #e2e8f0;
            padding: 10px;
            border-radius: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.7rem;
            background: #f8fafc;
            color: #64748b;
        }

        .btn-status.active-online { background: #d1fae5; color: #065f46; border-color: #10b981; }
        .btn-status.active-busy { background: #fef3c7; color: #92400e; border-color: #f59e0b; }
        .btn-status.active-offline { background: #f1f5f9; color: #475569; border-color: #94a3b8; }
        .btn-status:hover { transform: translateY(-2px); }

        /* Stats Card */
        .stats-card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .date-badge {
            background: #eef2ff;
            color: #667eea;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stats-row.two-cols {
            grid-template-columns: 1fr 1fr;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .stat-val {
            font-size: 1.3rem;
            font-weight: bold;
            color: #667eea;
            display: block;
        }

        .stat-lbl {
            font-size: 0.6rem;
            color: #94a3b8;
            text-transform: uppercase;
            margin-top: 5px;
        }

        .info-note {
            background: #fef9e3;
            border-left: 3px solid #f59e0b;
            padding: 8px 12px;
            border-radius: 12px;
            font-size: 0.65rem;
            color: #92400e;
            margin-top: 1rem;
        }

        .no-booking {
            text-align: center;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 20px;
            color: #94a3b8;
        }

        .refresh-indicator {
            text-align: center;
            font-size: 0.6rem;
            color: #94a3b8;
            margin-top: 1rem;
        }
        
        /* Notification Badge */
        .notification-badge {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ef4444;
            color: white;
            border-radius: 50px;
            padding: 8px 16px;
            font-size: 0.75rem;
            font-weight: bold;
            z-index: 9999;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            animation: bounce 0.5s ease;
        }
        
        .notification-badge:hover {
            transform: scale(1.05);
            background: #dc2626;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        /* Toast Notification */
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
            z-index: 10000;
            animation: slideIn 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 280px;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .toast-notification i {
            font-size: 1.5rem;
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .toast-message {
            font-size: 0.75rem;
            opacity: 0.9;
        }
        
        .toast-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 8px;
        }

        @media (max-width: 480px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            .booking-details {
                grid-template-columns: 1fr;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .stats-row.two-cols {
                grid-template-columns: 1fr;
            }
            
            .toast-notification {
                left: 20px;
                right: 20px;
                min-width: auto;
            }
        }
    </style>
</head>
<body>

<!-- Notification Badge for Pending Bookings -->
<?php if ($pending_count > 0): ?>
<div class="notification-badge" onclick="checkPendingBookings()">
    <i class="fas fa-bell"></i> <?= $pending_count ?> New Booking<?= $pending_count > 1 ? 's' : '' ?> Available!
</div>
<?php endif; ?>

<div class="dashboard-container">
    <!-- Header Card - Centered Profile -->
    <div class="header-card">
        <div class="profile-wrapper">
            <img src="<?= $profile_pic ?>" class="profile-pic" alt="Driver Profile">
            <h2 class="driver-name"><?= htmlspecialchars($driver_name) ?></h2>
            <div class="indicator-pill">
                <span class="dot dot-<?= $current_status ?>"></span>
                <?= strtoupper($current_status) ?>
            </div>
        </div>
    </div>

    <!-- Active Booking Alert -->
    <?php if ($active_booking): ?>
    <div class="active-booking-card">
        <div class="active-booking-header">
            <span class="active-badge">
                <i class="fas fa-bell"></i> ACTIVE TRIP
            </span>
            <span class="booking-code">
                <i class="fas fa-qrcode"></i> <?= htmlspecialchars($active_booking['booking_code']) ?>
            </span>
        </div>
        
        <div class="booking-details">
            <div class="booking-detail-item">
                <i class="fas fa-user"></i>
                <span><strong>Passenger:</strong> <?= htmlspecialchars($active_booking['passenger_name']) ?></span>
            </div>
            <div class="booking-detail-item">
                <i class="fas fa-location-dot"></i>
                <span><strong>Pickup:</strong> <?= htmlspecialchars($active_booking['pickup_landmark']) ?></span>
            </div>
            <div class="booking-detail-item">
                <i class="fas fa-location-arrow"></i>
                <span><strong>Dropoff:</strong> <?= htmlspecialchars($active_booking['dropoff_landmark']) ?></span>
            </div>
            <div class="booking-detail-item">
                <i class="fas fa-money-bill-wave"></i>
                <span><strong>Fare:</strong> ₱<?= number_format($active_booking['fare_amount'] ?? 0, 2) ?></span>
            </div>
        </div>
        
        <div class="btn-action">
            <?php if ($active_booking['status'] == 'ASSIGNED'): ?>
                <a href="scanner.php?booking_id=<?= $active_booking['id'] ?>" class="btn-scan">
                    <i class="fas fa-camera"></i> Scan Now
                </a>
            <?php elseif ($active_booking['status'] == 'PASSENGER PICKED UP'): ?>
                <a href="update_booking_status.php?id=<?= $active_booking['id'] ?>&status=IN TRANSIT" 
                   class="btn-transit" onclick="return confirm('Start trip?')">
                    <i class="fas fa-truck-moving"></i> Start Trip
                </a>
            <?php elseif ($active_booking['status'] == 'IN TRANSIT'): ?>
                <a href="update_booking_status.php?id=<?= $active_booking['id'] ?>&status=COMPLETED" 
                   class="btn-complete" onclick="return confirm('Complete this trip?')">
                    <i class="fas fa-flag-checkered"></i> Complete Trip
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="status-card">
        <div class="no-booking">
            <i class="fas fa-clock" style="font-size: 2rem; opacity: 0.5;"></i>
            <p style="margin-top: 0.5rem;">No active trips at the moment</p>
            <p style="font-size: 0.7rem; color: #94a3b8;">Once assigned, trip details will appear here</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Status Card -->
    <div class="status-card">
        <span class="card-title"><i class="fas fa-toggle-on"></i> Availability Status</span>
        <div class="status-grid">
            <button onclick="updateStatus('online')" class="btn-status <?= $current_status == 'online' ? 'active-online' : '' ?>">
                <i class="fas fa-circle"></i> ONLINE
            </button>
            <button onclick="updateStatus('busy')" class="btn-status <?= $current_status == 'busy' ? 'active-busy' : '' ?>">
                <i class="fas fa-clock"></i> BUSY
            </button>
            <button onclick="updateStatus('offline')" class="btn-status <?= $current_status == 'offline' ? 'active-offline' : '' ?>">
                <i class="fas fa-power-off"></i> OFFLINE
            </button>
        </div>
    </div>

    <!-- Stats Card -->
    <div class="stats-card">
        <div style="text-align: center;">
            <span class="date-badge">
                <i class="fas fa-calendar"></i> <?= date('F d, Y', strtotime($today_date)) ?>
            </span>
        </div>
        
        <span class="card-title"><i class="fas fa-chart-line"></i> Today's Performance (Real-time)</span>
        <div class="stats-row">
            <div class="stat-item">
                <span class="stat-val" id="todayTrips"><?= $today_trips ?></span>
                <span class="stat-lbl">Trips Today</span>
            </div>
            <div class="stat-item">
                <span class="stat-val" id="todayEarnings">₱<?= number_format($today_earnings, 2) ?></span>
                <span class="stat-lbl">Today's Earnings</span>
            </div>
            <div class="stat-item">
                <span class="stat-val" id="avgPerTrip">₱<?= number_format($avg_per_trip, 2) ?></span>
                <span class="stat-lbl">Avg. per Trip</span>
            </div>
        </div>
        
        <span class="card-title" style="margin-top: 1rem;"><i class="fas fa-trophy"></i> Lifetime Statistics (Accumulated)</span>
        <div class="stats-row two-cols">
            <div class="stat-item">
                <span class="stat-val" id="lifetimeTrips"><?= $total_lifetime_trips ?></span>
                <span class="stat-lbl">Total Trips</span>
            </div>
            <div class="stat-item">
                <span class="stat-val" id="lifetimeEarnings">₱<?= number_format($lifetime_earnings, 2) ?></span>
                <span class="stat-lbl">Lifetime Earnings</span>
            </div>
        </div>
        
        <div class="info-note">
            <i class="fas fa-info-circle"></i> Today's stats are calculated from completed trips today. They automatically reset at midnight and add to your lifetime totals.
        </div>
        
        <div class="info-note" style="background: #e0e7ff; border-left-color: #667eea; margin-top: 0.5rem;">
            <i class="fas fa-bell"></i> You will be notified when a new booking is assigned to you.
        </div>
        
        <div class="refresh-indicator">
            <i class="fas fa-sync-alt"></i> Auto-refreshing every 15 seconds for real-time updates
        </div>
    </div>
</div>

<script>
let lastBookingCheck = <?= time() * 1000 ?>; // milliseconds
let audio = new Audio();
let notificationSound = false;

// Function to play notification sound
function playNotificationSound() {
    try {
        // Create a simple beep using Web Audio API
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (AudioContext) {
            const audioCtx = new AudioContext();
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            
            oscillator.frequency.value = 880;
            gainNode.gain.value = 0.3;
            
            oscillator.start();
            gainNode.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + 1);
            oscillator.stop(audioCtx.currentTime + 0.5);
        }
    } catch(e) {
        console.log("Sound not supported");
    }
}

// Function to show toast notification
function showToast(title, message, icon = 'fas fa-bell') {
    // Remove existing toast
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.innerHTML = `
        <i class="${icon}"></i>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
    `;
    
    // Add click to open bookings page
    toast.onclick = (e) => {
        if (!e.target.classList.contains('toast-close')) {
            window.location.href = 'view_bookings.php';
        }
    };
    
    document.body.appendChild(toast);
    
    // Auto remove after 10 seconds
    setTimeout(() => {
        if (toast && toast.parentElement) {
            toast.remove();
        }
    }, 10000);
}

// Function to check for new pending bookings
function checkPendingBookings() {
    fetch('api/check_pending_bookings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'last_check=' + lastBookingCheck
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.new_bookings > 0) {
            // Play notification sound
            playNotificationSound();
            
            // Show browser notification
            if (Notification.permission === "granted") {
                new Notification("New Booking Available! 🚕", {
                    body: `There ${data.new_bookings === 1 ? 'is' : 'are'} ${data.new_bookings} new pending booking${data.new_bookings > 1 ? 's' : ''}. Click to view.`,
                    icon: "../assets/images/logo2.png",
                    badge: "../assets/images/logo2.png",
                    tag: "new-booking",
                    requireInteraction: true
                });
            }
            
            // Show custom toast notification
            showToast(
                "📢 New Booking Alert!", 
                `${data.new_bookings} new passenger${data.new_bookings > 1 ? 's are' : ' is'} waiting for a ride. Click to view bookings.`,
                'fas fa-taxi'
            );
            
            // Update or create notification badge
            updateNotificationBadge(data.total_pending);
            
            // Play sound effect if enabled
            if (localStorage.getItem('notification_sound') !== 'false') {
                // You can add an actual audio file here
                // audio.src = '../assets/sounds/notification.mp3';
                // audio.play();
            }
            
            // Update last check time
            lastBookingCheck = data.current_time;
        } else if (data.total_pending > 0) {
            // Update badge even without new bookings
            updateNotificationBadge(data.total_pending);
        }
    })
    .catch(err => console.log("Check pending error:", err));
}

// Function to update notification badge
function updateNotificationBadge(count) {
    let badge = document.querySelector('.notification-badge');
    
    if (count > 0) {
        if (badge) {
            badge.innerHTML = `<i class="fas fa-bell"></i> ${count} New Booking${count > 1 ? 's' : ''} Available!`;
            badge.style.display = 'block';
        } else {
            // Create badge if it doesn't exist
            const newBadge = document.createElement('div');
            newBadge.className = 'notification-badge';
            newBadge.onclick = () => window.location.href = 'view_bookings.php';
            newBadge.innerHTML = `<i class="fas fa-bell"></i> ${count} New Booking${count > 1 ? 's' : ''} Available!`;
            document.body.appendChild(newBadge);
        }
    } else if (badge) {
        badge.style.display = 'none';
    }
}

// Function to update status
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

// Real-time stats update function
function updateRealTimeStats() {
    fetch('api/get_realtime_stats.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'driver_id=<?= $driver_id ?>'
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            // Update today's stats
            document.getElementById('todayTrips').textContent = data.today_trips;
            document.getElementById('todayEarnings').textContent = '₱' + parseFloat(data.today_earnings).toFixed(2);
            document.getElementById('avgPerTrip').textContent = '₱' + parseFloat(data.avg_per_trip).toFixed(2);
            
            // Update lifetime stats
            document.getElementById('lifetimeTrips').textContent = data.lifetime_trips;
            document.getElementById('lifetimeEarnings').textContent = '₱' + parseFloat(data.lifetime_earnings).toFixed(2);
            
            // Check if there's a new booking assigned
            if(data.has_new_booking) {
                location.reload();
            }
        }
    })
    .catch(err => console.log("Stats update error:", err));
}

// Check for pending bookings every 10 seconds
setInterval(function() {
    checkPendingBookings();
}, 10000);

// Auto-refresh stats every 15 seconds for real-time updates
setInterval(function() {
    updateRealTimeStats();
}, 15000);

// Request notification permission on page load
if (Notification.permission !== "granted" && Notification.permission !== "denied") {
    Notification.requestPermission();
}

// Initial checks
updateRealTimeStats();
checkPendingBookings();

// Optional: Add sound toggle setting
const soundEnabled = localStorage.getItem('notification_sound');
if (soundEnabled === null) {
    localStorage.setItem('notification_sound', 'true');
}
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>