<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Siguraduhin na Driver lamang ang makaka-access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../dashboard.php");
    exit;
}

$driver_id = $_SESSION['user_id'];
$driver_name = $_SESSION['name'] ?? $_SESSION['full_name'] ?? 'Driver';

// Get driver stats - gamit ang driver_id para accurate
$total_completed = 0;
$total_cancelled = 0;
$total_passengers = 0;
$total_earnings = 0;

$stats_query = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'CANCELLED' THEN 1 END) as cancelled,
        COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN total_pax ELSE 0 END), 0) as total_pax,
        COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN fare_amount ELSE 0 END), 0) as total_earnings
    FROM bookings 
    WHERE driver_id = ?
");
$stats_query->bind_param("i", $driver_id);
$stats_query->execute();
$stats_result = $stats_query->get_result()->fetch_assoc();

$total_completed = $stats_result['completed'] ?? 0;
$total_cancelled = $stats_result['cancelled'] ?? 0;
$total_passengers = $stats_result['total_pax'] ?? 0;
$total_earnings = $stats_result['total_earnings'] ?? 0;

// Get payment statistics - gamit ang driver_id
$payment_stats_query = $conn->prepare("
    SELECT 
        payment_method,
        payment_status,
        COUNT(*) as count,
        COALESCE(SUM(fare_amount), 0) as total_amount
    FROM bookings 
    WHERE driver_id = ? AND status = 'COMPLETED'
    GROUP BY payment_method, payment_status
");
$payment_stats_query->bind_param("i", $driver_id);
$payment_stats_query->execute();
$payment_stats = $payment_stats_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate payment method totals
$cash_payments = 0;
$gcash_payments = 0;
$pending_payments = 0;
$paid_payments = 0;

foreach ($payment_stats as $stat) {
    if ($stat['payment_method'] == 'cash') {
        $cash_payments += $stat['total_amount'];
    } elseif ($stat['payment_method'] == 'gcash') {
        $gcash_payments += $stat['total_amount'];
    }
    
    if ($stat['payment_status'] == 'pending') {
        $pending_payments += $stat['total_amount'];
    } elseif ($stat['payment_status'] == 'paid') {
        $paid_payments += $stat['total_amount'];
    }
}

// Kunin ang listahan ng transactions (Completed & Cancelled) - gamit ang driver_id
// Directly get toda_id from bookings table
$history_query = $conn->prepare("
    SELECT 
        b.*, 
        u.name AS passenger_real_name,
        u.contact AS passenger_contact
    FROM bookings b 
    JOIN users u ON b.passenger_id = u.id 
    WHERE b.driver_id = ? 
    AND b.status IN ('COMPLETED', 'CANCELLED')
    ORDER BY b.created_at DESC 
    LIMIT 50
");
$history_query->bind_param("i", $driver_id);
$history_query->execute();
$history = $history_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper function para sa payment method display
function getPaymentMethodDisplay($method) {
    switch($method) {
        case 'cash':
            return '<span style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem;"><i class="fas fa-money-bill-wave"></i> Cash</span>';
        case 'gcash':
            return '<span style="background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem;"><i class="fab fa-gcash"></i> GCash</span>';
        default:
            return '<span style="background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem;">Not Specified</span>';
    }
}

function getPaymentStatusDisplay($status) {
    switch($status) {
        case 'paid':
            return '<span style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem;"><i class="fas fa-check-circle"></i> Paid</span>';
        case 'pending':
            return '<span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem;"><i class="fas fa-clock"></i> Pending</span>';
        case 'failed':
            return '<span style="background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem;"><i class="fas fa-times-circle"></i> Failed</span>';
        default:
            return '<span style="background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem;">Not Specified</span>';
    }
}

function getStatusDisplay($status) {
    switch($status) {
        case 'COMPLETED':
            return '<span style="background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: bold;"><i class="fas fa-check-circle"></i> COMPLETED</span>';
        case 'CANCELLED':
            return '<span style="background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: bold;"><i class="fas fa-times-circle"></i> CANCELLED</span>';
        case 'ACCEPTED':
            return '<span style="background: #dbeafe; color: #1e40af; padding: 4px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: bold;"><i class="fas fa-clock"></i> ACCEPTED</span>';
        case 'PASSENGER PICKED UP':
            return '<span style="background: #fed7aa; color: #9a3412; padding: 4px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: bold;"><i class="fas fa-car"></i> IN TRANSIT</span>';
        default:
            return '<span style="background: #f1f5f9; color: #475569; padding: 4px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: bold;">' . htmlspecialchars($status) . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Passenger Transactions | GoTrike</title>
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

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .transactions-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        /* Header Card */
        .header-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.5s ease;
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

        .header-card h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: bold;
            position: relative;
            z-index: 1;
        }

        .header-card p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

        .stat-card.completed .stat-icon { background: #d1fae5; color: #10b981; }
        .stat-card.cancelled .stat-icon { background: #fee2e2; color: #ef4444; }
        .stat-card.passengers .stat-icon { background: #e0e7ff; color: #667eea; }
        .stat-card.earnings .stat-icon { background: #fed7aa; color: #f59e0b; }

        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }

        .stat-label {
            font-size: 0.7rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card.completed .stat-value { color: #10b981; }
        .stat-card.cancelled .stat-value { color: #ef4444; }
        .stat-card.passengers .stat-value { color: #667eea; }
        .stat-card.earnings .stat-value { color: #f59e0b; }

        /* Payment Stats Section */
        .payment-stats-section {
            background: white;
            border-radius: 20px;
            padding: 1.2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .section-title {
            font-size: 1rem;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .payment-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .payment-item {
            text-align: center;
            padding: 0.8rem;
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .payment-item.cash {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        }

        .payment-item.gcash {
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
        }

        .payment-item.pending {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        }

        .payment-item.paid {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        }

        .payment-amount {
            font-size: 1.3rem;
            font-weight: bold;
            display: block;
        }

        .payment-label {
            font-size: 0.7rem;
            margin-top: 0.25rem;
            display: block;
        }

        /* Navigation Bar */
        .nav-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            background: white;
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .nav-bar h2 {
            margin: 0;
            font-size: 1.2rem;
            color: #2d3748;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #e2e8f0;
            transform: translateX(-3px);
        }

        /* Trip Card */
        .trip-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            position: relative;
            transition: all 0.3s ease;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .trip-card:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .status-tag {
            position: absolute;
            top: 1.2rem;
            right: 1.2rem;
        }

        .passenger-info {
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .passenger-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #667eea;
            display: block;
            margin-bottom: 0.25rem;
        }

        .passenger-contact {
            font-size: 0.7rem;
            color: #94a3b8;
            display: block;
        }

        .booking-code {
            font-size: 0.7rem;
            color: #94a3b8;
            letter-spacing: 0.5px;
        }

        .route-info {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 12px;
            border-radius: 14px;
            font-size: 0.85rem;
            margin: 12px 0;
        }

        .route-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 8px;
        }

        .route-item:last-child {
            margin-bottom: 0;
        }

        .route-icon {
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .notes-text {
            font-size: 0.8rem;
            color: #475569;
            font-style: italic;
            background: #fef9e3;
            padding: 8px 12px;
            border-radius: 12px;
            margin: 10px 0;
            border-left: 3px solid #f59e0b;
        }

        .meta-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }

        .payment-info {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .unit-badge {
            background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
            display: inline-block;
        }

        .trip-duration {
            margin-top: 10px;
            font-size: 0.7rem;
            color: #10b981;
            text-align: center;
            background: #f0fdf4;
            padding: 8px;
            border-radius: 12px;
        }
        
        /* Toda Badge */
        .toda-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .toda-info {
            margin-top: 5px;
            font-size: 0.7rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 24px;
            animation: fadeIn 0.5s ease;
        }

        .empty-state svg {
            margin-bottom: 1rem;
        }

        @media (max-width: 640px) {
            .transactions-container {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stat-value {
                font-size: 1.4rem;
            }
            
            .trip-card {
                padding: 1rem;
            }
            
            .status-tag {
                position: static;
                display: inline-block;
                margin-bottom: 0.5rem;
                text-align: right;
            }
            
            .meta-info {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .payment-info {
                justify-content: center;
                margin-top: 0.5rem;
            }
            
            .payment-summary {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>

<div class="transactions-container">
    <!-- Header Card -->
    <div class="header-card">
        <h1>📋 Transaction History</h1>
        <p>View all your completed and cancelled trips</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card completed">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?= number_format($total_completed) ?></div>
            <div class="stat-label">Completed Trips</div>
        </div>
        
        <div class="stat-card cancelled">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-value"><?= number_format($total_cancelled) ?></div>
            <div class="stat-label">Cancelled Trips</div>
        </div>
        
        <div class="stat-card passengers">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?= number_format($total_passengers) ?></div>
            <div class="stat-label">Total Passengers</div>
        </div>
        
        <div class="stat-card earnings">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-value">₱<?= number_format($total_earnings, 2) ?></div>
            <div class="stat-label">Total Earnings</div>
        </div>
    </div>

    <!-- Payment Statistics Section -->
    <div class="payment-stats-section">
        <div class="section-title">
            <i class="fas fa-credit-card"></i> Payment Summary
        </div>
        <div class="payment-summary">
            <div class="payment-item cash">
                <span class="payment-amount">₱<?= number_format($cash_payments, 2) ?></span>
                <span class="payment-label"><i class="fas fa-money-bill-wave"></i> Cash Payments</span>
            </div>
           
            <div class="payment-item paid">
                <span class="payment-amount">₱<?= number_format($paid_payments, 2) ?></span>
                <span class="payment-label"><i class="fas fa-check-circle"></i> Paid</span>
            </div>
            <div class="payment-item pending">
                <span class="payment-amount">₱<?= number_format($pending_payments, 2) ?></span>
                <span class="payment-label"><i class="fas fa-clock"></i> Pending</span>
            </div>
        </div>
    </div>

    <!-- Navigation Bar -->
    <div class="nav-bar">
        <h2>Recent Transactions</h2>
        <a href="driver_dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </div>

    <?php if (empty($history)): ?>
        <div class="empty-state">
            <i class="fas fa-receipt" style="font-size: 4rem; color: #cbd5e0;"></i>
            <h3 style="margin: 1rem 0 0.5rem;">No transactions found</h3>
            <p style="color: #6b7280;">You haven't completed any trips yet.</p>
            <a href="driver_dashboard.php" style="display: inline-block; margin-top: 1rem; padding: 0.5rem 1.5rem; background: #667eea; color: white; border-radius: 25px; text-decoration: none;">Go to Dashboard</a>
        </div>
    <?php else: ?>
        <?php foreach ($history as $row): ?>
            <div class="trip-card">
                <div class="status-tag">
                    <?= getStatusDisplay($row['status']) ?>
                </div>

                <div class="passenger-info">
                    <span class="passenger-name">👤 <?= htmlspecialchars($row['passenger_real_name']) ?></span>
                    <?php if (!empty($row['passenger_contact'])): ?>
                        <span class="passenger-contact"><i class="fas fa-phone"></i> <?= htmlspecialchars($row['passenger_contact']) ?></span>
                    <?php endif; ?>
                    <span class="booking-code">🔖 #<?= htmlspecialchars($row['booking_code']) ?></span>
                    
                    <!-- Display Toda ID from bookings table -->
                    <?php if (!empty($row['toda_name'])): ?>
                        <div class="toda-info">
                            <span class="toda-badge">
                                <i class="fas fa-building"></i> TODA NAME: <?= htmlspecialchars($row['toda_name']) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="route-info">
                    <div class="route-item">
                        <span class="route-icon" style="color: #10b981;"><i class="fas fa-circle"></i></span>
                        <span><strong>Pickup:</strong> <?= htmlspecialchars($row['pickup_landmark'] ?? 'Not specified') ?></span>
                    </div>
                    <div class="route-item">
                        <span class="route-icon" style="color: #ef4444;"><i class="fas fa-location-arrow"></i></span>
                        <span><strong>Dropoff:</strong> <?= htmlspecialchars($row['dropoff_landmark'] ?? 'Not specified') ?></span>
                    </div>
                </div>

                <?php if(!empty($row['notes'])): ?>
                    <div class="notes-text">
                        <i class="fas fa-quote-left"></i>
                        "<?= htmlspecialchars($row['notes']) ?>"
                    </div>
                <?php endif; ?>

                <div class="meta-info">
                    <div>
                        <div><strong>📅 Date:</strong> <?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                        <div><strong>⏰ Time:</strong> <?= date('h:i A', strtotime($row['created_at'])) ?></div>
                        <div><strong>🚲 Units:</strong> <span class="unit-badge"><?= htmlspecialchars($row['trike_units'] ?? 1) ?></span></div>
                        <div><strong>👥 Pax:</strong> <?= htmlspecialchars($row['total_pax'] ?? 1) ?></div>
                        <?php if(!empty($row['distance_km']) || !empty($row['distance'])): ?>
                            <div><strong>📏 Distance:</strong> <?= number_format($row['distance_km'] ?? $row['distance'] ?? 0, 1) ?> km</div>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: right;">
                        <div><strong>💰 Fare:</strong> <span style="color: #10b981; font-weight: bold;">₱<?= number_format($row['fare_amount'] ?? $row['fare'] ?? 0, 2) ?></span></div>
                        <div class="payment-info">
                            <strong>💳 Payment:</strong> 
                            <?= getPaymentMethodDisplay($row['payment_method'] ?? '') ?>
                            <?= getPaymentStatusDisplay($row['payment_status'] ?? '') ?>
                        </div>
                        <?php if(!empty($row['reference_number'])): ?>
                            <div><strong>🔢 Ref #:</strong> <?= htmlspecialchars($row['reference_number']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($row['status'] === 'COMPLETED' && !empty($row['pickup_time']) && !empty($row['dropoff_time'])): ?>
                    <div class="trip-duration">
                        <i class="fas fa-clock"></i>
                        Trip Duration: <?= date('h:i A', strtotime($row['pickup_time'])) ?> → <?= date('h:i A', strtotime($row['dropoff_time'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>