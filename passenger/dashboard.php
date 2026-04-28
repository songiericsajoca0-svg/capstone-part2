<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'passenger') {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch counts
$pending = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE passenger_id = $user_id AND status = 'PENDING'")->fetch_assoc()['cnt'];
$active  = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE passenger_id = $user_id AND status IN ('ASSIGNED','PASSENGER PICKED UP','IN TRANSIT')")->fetch_assoc()['cnt'];
$history = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE passenger_id = $user_id AND status IN ('COMPLETED','CANCELLED')")->fetch_assoc()['cnt'];

// Fetch user data
$stmt = $conn->prepare("SELECT name, profile, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$profile_img = !empty($user_data['profile']) ? '../uploads/' . $user_data['profile'] : '../assets/default-avatar.png';

// Fetch recent bookings (last 3) with payment details - INCLUDING THE 'id' COLUMN
$recent_bookings = $conn->query("
    SELECT id, booking_code, status, pickup_landmark, dropoff_landmark, 
           pickup_time, created_at, fare_amount, payment_method, payment_status
    FROM bookings 
    WHERE passenger_id = $user_id 
    ORDER BY created_at DESC 
    LIMIT 3
");
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
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    /* Welcome Card */
    .welcome-card {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
        transition: transform 0.3s ease;
    }

    .welcome-card:hover {
        transform: translateY(-5px);
    }

    .welcome-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .welcome-header::before {
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

    .profile-section {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }

    .profile-image {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid rgba(255,255,255,0.3);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        transition: transform 0.3s ease;
    }

    .profile-image:hover {
        transform: scale(1.05);
    }

    .welcome-text h1 {
        font-size: 1.8rem;
        margin: 0;
        font-weight: bold;
    }

    .welcome-text p {
        margin: 0.5rem 0 0;
        opacity: 0.9;
    }

    .action-buttons {
        background: #f8f9fa;
        padding: 1rem 2rem;
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        border-top: 1px solid #e0e0e0;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: bold;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .btn-action-primary {
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
    }

    .btn-action-primary:hover {
        background: #667eea;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102,126,234,0.3);
    }

    .btn-action-secondary {
        background: white;
        color: #6c757d;
        border: 2px solid #dee2e6;
    }

    .btn-action-secondary:hover {
        background: #6c757d;
        color: white;
        border-color: #6c757d;
        transform: translateY(-2px);
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        transition: height 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.12);
    }

    .stat-card:hover::before {
        height: 6px;
    }

    .stat-card.pending::before { background: #f59e0b; }
    .stat-card.active::before { background: #10b981; }
    .stat-card.history::before { background: #ef4444; }

    .stat-icon {
        width: 70px;
        height: 70px;
        margin: 0 auto 1rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
    }

    .stat-card.pending .stat-icon { background: #fef3c7; color: #f59e0b; }
    .stat-card.active .stat-icon { background: #d1fae5; color: #10b981; }
    .stat-card.history .stat-icon { background: #fee2e2; color: #ef4444; }

    .stat-number {
        font-size: 3rem;
        font-weight: bold;
        margin: 0.5rem 0;
    }

    .stat-card.pending .stat-number { color: #f59e0b; }
    .stat-card.active .stat-number { color: #10b981; }
    .stat-card.history .stat-number { color: #ef4444; }

    .stat-label {
        font-size: 0.9rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Create Booking CTA - FIRE & FLAME EFFECT (PREMIUM) */
    .cta-section {
        text-align: center;
        margin-bottom: 2rem;
        position: relative;
    }

    .btn-create {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-weight: 700;
        font-size: 1.6rem;
        letter-spacing: 3px;
        text-transform: uppercase;
        background: linear-gradient(135deg, #1e1e2f, #2a2a40);
        color: #f5f5f5;
        padding: 18px 48px;
        border-radius: 16px;
        position: relative;
        overflow: hidden;
        transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        cursor: pointer;
        z-index: 1;
    }

    /* FIRE PARTICLES - using multiple pseudo-elements */
    .btn-create::before,
    .btn-create::after {
        content: '';
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        pointer-events: none;
        z-index: -1;
        transition: all 0.3s ease;
    }

    /* Main fire glow effect */
    .btn-create::before {
        background: radial-gradient(circle at center, rgba(255,100,0,0) 0%, rgba(255,50,0,0) 70%);
        opacity: 0;
        transition: opacity 0.4s ease;
    }

    /* Fire particles container */
    .btn-create .fire-particles {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        overflow: hidden;
        border-radius: 16px;
        z-index: 5;
    }

    .btn-create .particle {
        position: absolute;
        background: radial-gradient(circle, #ff6600, #ff3300, #ffcc00);
        border-radius: 50%;
        opacity: 0;
        filter: blur(1px);
        pointer-events: none;
        z-index: 10;
    }

    /* Hover effect - fire ignites */
    .btn-create:hover {
        transform: translateY(-3px) scale(1.02);
        letter-spacing: 5px;
        padding: 18px 54px;
        box-shadow: 0 0 25px rgba(255, 80, 0, 0.8), 0 10px 30px rgba(0, 0, 0, 0.3);
        animation: flamePulse 0.6s ease-in-out infinite alternate;
    }

    .btn-create:hover::before {
        opacity: 1;
        background: radial-gradient(circle at center, rgba(255,100,0,0.4) 0%, rgba(255,50,0,0.2) 50%, rgba(255,0,0,0) 80%);
    }

    /* Fire text effect on hover */
    .btn-create:hover span {
        animation: textFire 0.3s ease forwards;
        color: #fff5e0;
        text-shadow: 0 0 8px #ff6600, 0 0 15px #ff3300;
    }

    @keyframes flamePulse {
        0% {
            box-shadow: 0 0 15px rgba(255, 80, 0, 0.6), 0 10px 30px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 100, 0, 0.5);
        }
        100% {
            box-shadow: 0 0 35px rgba(255, 80, 0, 1), 0 10px 35px rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 150, 0, 0.9);
        }
    }

    @keyframes textFire {
        0% {
            text-shadow: 0 0 0px #ff6600;
            transform: scale(1);
        }
        100% {
            text-shadow: 0 0 12px #ff9900, 0 0 20px #ff4400;
            transform: scale(1.02);
        }
    }

    /* Gradient animation for background */
    .btn-create {
        background: linear-gradient(135deg, #1e1e2f, #2a2a40);
        background-size: 200% 200%;
        animation: gradientShift 3s ease infinite;
    }

    @keyframes gradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    /* Recent Bookings */
    .recent-section {
        background: white;
        border-radius: 24px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 1.5rem;
        color: #333;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .booking-list {
        display: grid;
        gap: 1rem;
    }

    .booking-item {
        background: #f8f9fa;
        border-radius: 16px;
        padding: 1rem;
        transition: all 0.3s ease;
        border-left: 4px solid;
    }

    .booking-item.pending { border-left-color: #f59e0b; }
    .booking-item.completed { border-left-color: #10b981; }
    .booking-item.cancelled { border-left-color: #ef4444; }

    .booking-item:hover {
        transform: translateX(5px);
        background: #f0f2f5;
    }

    .booking-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        flex-wrap: wrap;
    }

    .booking-code {
        font-weight: bold;
        color: #667eea;
        font-size: 0.9rem;
    }

    .booking-status {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
    }

    .status-pending { background: #fef3c7; color: #f59e0b; }
    .status-completed { background: #d1fae5; color: #10b981; }
    .status-cancelled { background: #fee2e2; color: #ef4444; }

    .booking-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.5rem;
        margin-top: 0.5rem;
        font-size: 0.85rem;
        color: #6c757d;
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Payment Info Styles */
    .payment-info {
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #e0e0e0;
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        font-size: 0.85rem;
    }

    .payment-method {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #6c757d;
    }

    .payment-status {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
    }

    .payment-paid { background: #d1fae5; color: #10b981; }
    .payment-unpaid { background: #fee2e2; color: #ef4444; }
    .payment-pending { background: #fef3c7; color: #f59e0b; }

    /* Trip Button Styles */
    .trip-button {
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #e0e0e0;
    }

    .btn-view-trip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.6rem 1.2rem;
        border-radius: 12px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: bold;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }

    .btn-view-trip:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    }

    .btn-view-trip svg {
        width: 18px;
        height: 18px;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #6c757d;
    }

    /* MOBILE RESPONSIVE */
    @media (max-width: 768px) {
        .profile-section {
            flex-direction: column;
            text-align: center;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .booking-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .btn-create {
            font-size: 1.2rem;
            padding: 14px 32px;
            letter-spacing: 2px;
        }

        .btn-create:hover {
            padding: 14px 38px;
            letter-spacing: 3px;
        }

        .welcome-text h1 {
            font-size: 1.3rem;
        }

        .profile-image {
            width: 60px;
            height: 60px;
        }

        .section-title {
            font-size: 1.2rem;
        }

        .booking-details {
            grid-template-columns: 1fr;
            gap: 0.4rem;
        }

        .payment-info {
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-start;
        }

        .dashboard-container {
            padding: 1rem 0.8rem;
        }

        .recent-section {
            padding: 1rem;
        }
    }

    @media (max-width: 480px) {
        .btn-create {
            font-size: 1rem;
            padding: 12px 24px;
        }

        .btn-create:hover {
            padding: 12px 28px;
        }

        .stat-number {
            font-size: 2rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
        }
    }
</style>

<div class="dashboard-container">
    <!-- Welcome Card -->
    <div class="welcome-card">
        <div class="welcome-header">
            <div class="profile-section">
                <img src="<?= htmlspecialchars($profile_img) ?>" alt="Profile" class="profile-image">
                <div class="welcome-text">
                    <h1>Welcome, <?= htmlspecialchars($user_data['name']) ?>! </h1>
                    <p>Ready for your next ride with GoTrike?</p>
                </div>
            </div>
        </div>
        
    </div>

    <!-- Create Booking CTA with FIRE & FLAME EFFECT -->
    <div class="cta-section">
        <a href="create-booking.php" class="btn-create" id="fireButton">
            <span>BOOK NOW!</span>
            <div class="fire-particles"></div>
        </a>
    </div>

    <!-- Recent Bookings -->
    <div class="recent-section">
        <div class="section-title">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Recent Bookings
        </div>
        
        <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
            <div class="booking-list">
                <?php while($booking = $recent_bookings->fetch_assoc()): ?>
                    <?php 
                        $status_class = '';
                        $status_text = '';
                        switch(strtolower($booking['status'])) {
                            case 'pending':
                                $status_class = 'pending';
                                $status_text = 'Pending';
                                break;
                            case 'completed':
                                $status_class = 'completed';
                                $status_text = 'Completed';
                                break;
                            case 'cancelled':
                                $status_class = 'cancelled';
                                $status_text = 'Cancelled';
                                break;
                            default:
                                $status_class = 'pending';
                                $status_text = $booking['status'];
                        }
                        
                        // Payment status styling
                        $payment_status = strtolower($booking['payment_status'] ?? 'pending');
                        $payment_class = '';
                        $payment_text = '';
                        
                        if ($payment_status == 'paid' || $payment_status == 'completed') {
                            $payment_class = 'payment-paid';
                            $payment_text = 'Paid';
                        } elseif ($payment_status == 'unpaid') {
                            $payment_class = 'payment-unpaid';
                            $payment_text = 'Unpaid';
                        } else {
                            $payment_class = 'payment-pending';
                            $payment_text = 'Pending';
                        }
                        
                        // Payment method display
                        $payment_method = $booking['payment_method'] ?? 'Not specified';
                        if ($payment_method == 'cash') $payment_method = 'Cash';
                        if ($payment_method == 'gcash') $payment_method = 'GCash';
                        if ($payment_method == 'credit_card') $payment_method = 'Credit Card';
                        
                        // Check if booking is not completed (show trip button for non-completed bookings)
                        // Only show for PENDING, ASSIGNED, PASSENGER PICKED UP, IN TRANSIT
                        // NOT for COMPLETED or CANCELLED
                        $show_trip_button = strtolower($booking['status']) !== 'completed' && strtolower($booking['status']) !== 'cancelled';
                    ?>
                    <div class="booking-item <?= $status_class ?>">
                        <div class="booking-header">
                            <span class="booking-code"><?= htmlspecialchars($booking['booking_code']) ?></span>
                            <span class="booking-status status-<?= strtolower($booking['status']) ?>">
                                <?= $status_text ?>
                            </span>
                        </div>
                        <div class="booking-details">
                            <div class="detail-item">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span>From: <?= htmlspecialchars($booking['pickup_landmark']) ?></span>
                            </div>
                            <div class="detail-item">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span>To: <?= htmlspecialchars($booking['dropoff_landmark']) ?></span>
                            </div>
                            <div class="detail-item">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span><?= date('M d, Y h:i A', strtotime($booking['pickup_time'] ?? $booking['created_at'])) ?></span>
                            </div>
                            <?php if($booking['fare_amount']): ?>
                            <div class="detail-item">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>₱ <?= number_format($booking['fare_amount'], 2) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Payment Information -->
                        <div class="payment-info">
                            <div class="payment-method">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                                <span><?= $payment_method ?></span>
                            </div>
                            <div class="payment-status <?= $payment_class ?>">
                                <?= $payment_text ?>
                            </div>
                        </div>
                        
                        <!-- View QR Button - Only show for incomplete bookings (not completed and not cancelled) -->
                        <?php if ($show_trip_button): ?>
                        <div class="trip-button">
                            <a href="qr.php?id=<?= $booking['id'] ?>" class="btn-view-trip">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                View Your Active Trip
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                <p class="text-lg">No bookings yet</p>
                <p class="text-sm mt-2">Create your first booking to start riding with GoTrike!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- FIRE PARTICLE EFFECTS JAVASCRIPT -->
<script>
(function() {
    const fireButton = document.getElementById('fireButton');
    if (!fireButton) return;
    
    let particleInterval = null;
    let activeParticles = [];
    
    // Function to create fire particles
    function createFireParticle(container, mouseX, mouseY) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        
        // Random size between 3px and 12px
        const size = Math.random() * 9 + 3;
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        
        // Random fire colors
        const colors = ['#ff6600', '#ff4400', '#ff9900', '#ffcc00', '#ff3300', '#ff5500'];
        const randomColor = colors[Math.floor(Math.random() * colors.length)];
        particle.style.background = `radial-gradient(circle, ${randomColor}, #ff2200)`;
        
        // Position relative to mouse or random position on button
        let posX, posY;
        if (mouseX && mouseY) {
            const rect = fireButton.getBoundingClientRect();
            posX = mouseX - rect.left;
            posY = mouseY - rect.top;
        } else {
            // Random position within button
            posX = Math.random() * fireButton.offsetWidth;
            posY = Math.random() * fireButton.offsetHeight;
        }
        
        particle.style.left = posX + 'px';
        particle.style.top = posY + 'px';
        
        // Random animation direction
        const angle = Math.random() * Math.PI * 2;
        const velocityX = (Math.cos(angle) * (Math.random() * 3 + 1)) * (Math.random() > 0.5 ? 1 : -1);
        const velocityY = (Math.sin(angle) * (Math.random() * 3 + 1)) * -1 - 1; // upward bias
        
        let opacity = 0.8;
        let life = 100;
        let x = posX;
        let y = posY;
        
        particle.style.opacity = opacity;
        container.appendChild(particle);
        
        // Animate the particle
        function animateParticle() {
            life -= 4;
            if (life <= 0) {
                if (particle.parentNode) particle.remove();
                const index = activeParticles.indexOf(animateParticle);
                if (index > -1) activeParticles.splice(index, 1);
                return;
            }
            
            x += velocityX;
            y += velocityY;
            opacity = Math.max(0, opacity - 0.02);
            
            particle.style.left = x + 'px';
            particle.style.top = y + 'px';
            particle.style.opacity = opacity;
            particle.style.transform = `scale(${0.8 + (life / 100) * 0.5}) rotate(${Math.random() * 360}deg)`;
            
            requestAnimationFrame(animateParticle);
        }
        
        requestAnimationFrame(animateParticle);
        activeParticles.push(animateParticle);
        
        // Auto remove after 1.5 seconds if stuck
        setTimeout(() => {
            if (particle.parentNode) particle.remove();
        }, 1500);
    }
    
    // Create multiple particles on hover
    function burstFireParticles(event) {
        const container = fireButton.querySelector('.fire-particles');
        if (!container) return;
        
        // Clear any existing interval
        if (particleInterval) clearInterval(particleInterval);
        
        // Get mouse position relative to button
        let mouseX = null, mouseY = null;
        if (event && event.type === 'mousemove') {
            const rect = fireButton.getBoundingClientRect();
            mouseX = event.clientX;
            mouseY = event.clientY;
        }
        
        // Create initial burst (10-20 particles)
        const particleCount = Math.floor(Math.random() * 15) + 12;
        for (let i = 0; i < particleCount; i++) {
            setTimeout(() => {
                createFireParticle(container, mouseX, mouseY);
            }, i * 30);
        }
        
        // Continuous particle generation while hovering
        particleInterval = setInterval(() => {
            if (!fireButton.matches(':hover')) {
                if (particleInterval) clearInterval(particleInterval);
                particleInterval = null;
                return;
            }
            // Generate 3-8 particles per interval
            const count = Math.floor(Math.random() * 6) + 3;
            for (let i = 0; i < count; i++) {
                createFireParticle(container, null, null);
            }
        }, 80);
    }
    
    // Stop particle generation
    function stopFireParticles() {
        if (particleInterval) {
            clearInterval(particleInterval);
            particleInterval = null;
        }
        // Fade out remaining particles naturally (they will remove themselves)
    }
    
    // Add event listeners
    fireButton.addEventListener('mouseenter', burstFireParticles);
    fireButton.addEventListener('mousemove', function(event) {
        // Add occasional sparks on mouse move within button
        if (Math.random() > 0.7) {
            const container = fireButton.querySelector('.fire-particles');
            if (container) {
                const rect = fireButton.getBoundingClientRect();
                const mouseX = event.clientX;
                const mouseY = event.clientY;
                createFireParticle(container, mouseX, mouseY);
            }
        }
    });
    fireButton.addEventListener('mouseleave', stopFireParticles);
    
    // Also trigger on hover from any child element
    fireButton.addEventListener('mouseover', function(e) {
        // Ensure button is target
    });
})();
</script>

<?php include '../includes/footer.php'; ?>