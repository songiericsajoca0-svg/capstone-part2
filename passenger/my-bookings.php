<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

$user_id = intval($_SESSION['user_id']);

// --- SEARCH LOGIC START ---
$search_code = $_GET['search_code'] ?? '';
$search_date = $_GET['search_date'] ?? '';
$status_filter = $_GET['status'] ?? '';

// FIXED: JOIN users table para makuha ang driver name mula sa users table gamit ang driver_id
$query = "SELECT b.*, u.name as driver_name_from_users 
          FROM bookings b
          LEFT JOIN users u ON b.driver_id = u.id AND u.role = 'driver'
          WHERE b.passenger_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search_code)) {
    $query .= " AND b.booking_code LIKE ?";
    $params[] = "%$search_code%";
    $types .= "s";
}

if (!empty($search_date)) {
    $query .= " AND DATE(b.created_at) = ?";
    $params[] = $search_date;
    $types .= "s";
}

if (!empty($status_filter)) {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result();

// Get counts for filters
$total_all = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE passenger_id = $user_id")->fetch_assoc()['cnt'];
$total_completed = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE passenger_id = $user_id AND status = 'COMPLETED'")->fetch_assoc()['cnt'];
$total_cancelled = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE passenger_id = $user_id AND status = 'CANCELLED'")->fetch_assoc()['cnt'];
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

    .bookings-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    /* Main Card */
    .main-card {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1);
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

    /* Header */
    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .card-header::before {
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

    .card-header h2 {
        margin: 0;
        font-size: 1.8rem;
        font-weight: bold;
        position: relative;
        z-index: 1;
    }

    .card-header p {
        margin: 0.5rem 0 0;
        opacity: 0.9;
        position: relative;
        z-index: 1;
    }

    /* Stats Badges - Tatlo lang */
    .stats-badges {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .stat-badge {
        flex: 1;
        min-width: 120px;
        padding: 1rem;
        border-radius: 12px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: white;
        border: 2px solid #e2e8f0;
    }

    .stat-badge:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    .stat-badge.active {
        border-color: #667eea;
        background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
    }

    .stat-badge .count {
        font-size: 1.8rem;
        font-weight: bold;
        color: #2d3748;
    }

    .stat-badge .label {
        font-size: 0.8rem;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 0.25rem;
    }

    /* Search Form */
    .search-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        margin: 0 2rem 2rem 2rem;
        padding: 1.5rem;
        border-radius: 16px;
    }

    .search-form {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-end;
    }

    .search-group {
        flex: 1;
        min-width: 180px;
    }

    .search-group label {
        display: block;
        font-weight: bold;
        margin-bottom: 0.5rem;
        color: #4b5563;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .search-input {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .search-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }

    .btn-search, .btn-reset {
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        font-size: 0.9rem;
    }

    .btn-search {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-search:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102,126,234,0.3);
    }

    .btn-reset {
        background: #6b7280;
        color: white;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-reset:hover {
        background: #4b5563;
        transform: translateY(-2px);
    }

    /* Table */
    .table-wrapper {
        overflow-x: auto;
        padding: 0 2rem 2rem 2rem;
    }

    .bookings-table {
        width: 100%;
        border-collapse: collapse;
    }

    .bookings-table th {
        text-align: left;
        padding: 1rem;
        background: #f8fafc;
        font-weight: bold;
        color: #4b5563;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }

    .bookings-table td {
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
    }

    .bookings-table tr:hover {
        background: #f8fafc;
    }

    .booking-code {
        font-weight: bold;
        color: #667eea;
        font-size: 0.9rem;
    }

    .route-info {
        font-size: 0.85rem;
    }

    .route-arrow {
        color: #9ca3af;
        margin: 0 0.25rem;
    }

    .status-badge {
        display: inline-block;
        padding: 0.3rem 0.8rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-pending { background: #fef3c7; color: #d97706; }
    .status-assigned { background: #e0e7ff; color: #7c3aed; }
    .status-passenger_picked_up { background: #fed7aa; color: #ea580c; }
    .status-in_transit { background: #dbeafe; color: #2563eb; }
    .status-completed { background: #d1fae5; color: #15803d; }
    .status-cancelled { background: #fee2e2; color: #b91c1c; }

    .driver-name {
        font-weight: 500;
        color: #2d3748;
    }

    .driver-unassigned {
        color: #9ca3af;
        font-style: italic;
    }

    .btn-view {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .btn-view:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102,126,234,0.3);
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #6b7280;
    }

    .empty-state svg {
        margin-bottom: 1rem;
    }

    @media (max-width: 768px) {
        .bookings-container {
            padding: 1rem;
        }
        
        .search-card {
            margin: 1rem;
            padding: 1rem;
        }
        
        .table-wrapper {
            padding: 0 1rem 1rem 1rem;
        }
        
        .bookings-table th, 
        .bookings-table td {
            padding: 0.75rem;
        }
        
        .btn-view {
            padding: 0.4rem 0.75rem;
            font-size: 0.7rem;
        }
    }
</style>

<div class="bookings-container">
    <div class="main-card">
        <div class="card-header">
            <h2>📋 My Bookings</h2>
            <p>View and manage all your tricycle booking requests</p>
        </div>

        <!-- Stats Badges - Tatlo lang -->
        <div style="padding: 2rem 2rem 0 2rem;">
            <div class="stats-badges">
                <div class="stat-badge <?= $status_filter === '' ? 'active' : '' ?>" onclick="window.location.href='my-bookings.php'">
                    <div class="count"><?= $total_all ?></div>
                    <div class="label">All Bookings</div>
                </div>
                <div class="stat-badge <?= $status_filter === 'COMPLETED' ? 'active' : '' ?>" onclick="window.location.href='my-bookings.php?status=COMPLETED'">
                    <div class="count"><?= $total_completed ?></div>
                    <div class="label">Completed</div>
                </div>
                <div class="stat-badge <?= $status_filter === 'CANCELLED' ? 'active' : '' ?>" onclick="window.location.href='my-bookings.php?status=CANCELLED'">
                    <div class="count"><?= $total_cancelled ?></div>
                    <div class="label">Cancelled</div>
                </div>
            </div>
        </div>

        <!-- Search Form -->
        <div class="search-card">
            <form method="GET" action="" class="search-form">
                <div class="search-group">
                    <label>🔍 Booking Code</label>
                    <input type="text" 
                           name="search_code" 
                           value="<?= htmlspecialchars($search_code) ?>" 
                           placeholder="Enter booking code..." 
                           class="search-input">
                </div>

                <div class="search-group">
                    <label>📅 Date</label>
                    <input type="date" 
                           name="search_date" 
                           value="<?= htmlspecialchars($search_date) ?>" 
                           class="search-input">
                </div>

                <?php if (!empty($status_filter)): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <?php endif; ?>

                <div style="display: flex; gap: 0.75rem;">
                    <button type="submit" class="btn-search">
                        <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Search
                    </button>
                    <a href="my-bookings.php<?= !empty($status_filter) ? '?status=' . urlencode($status_filter) : '' ?>" class="btn-reset">
                        <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Messages -->
        <?php if (isset($_GET['msg'])): ?>
            <?php 
            $msg_type = $_GET['type'] ?? 'success';
            $gradient = ($msg_type === 'success') ? 'linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)' : 'linear-gradient(135deg, #fee2e2 0%, #fecaca 100%)';
            $color = ($msg_type === 'success') ? '#065f46' : '#991b1b';
            ?>
            <div style="margin: 0 2rem 1.5rem 2rem; padding: 1rem; background: <?= $gradient ?>; color: <?= $color ?>; border-radius: 12px; text-align: center; font-weight: 500; animation: slideDown 0.3s ease;">
                <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="table-wrapper">
            <?php if ($bookings->num_rows > 0): ?>
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>Booking Code</th>
                            <th>Route</th>
                            <th>Status</th>
                            <th>Driver</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $bookings->fetch_assoc()): ?>
                            <?php
                            $status_key = strtolower(str_replace(' ', '_', $row['status']));
                            $status_color_class = 'status-' . $status_key;
                            // FIXED: Gamitin ang name mula sa users table (driver_name_from_users) hindi ang driver_name column
                            $driver_display = !empty($row['driver_id']) && !empty($row['driver_name_from_users']) 
                                ? $row['driver_name_from_users'] 
                                : null;
                            ?>
                            <tr>
                                <td class="booking-code"><?= htmlspecialchars($row['booking_code']) ?></td>
                                <td class="route-info">
                                    <?= htmlspecialchars($row['pickup_landmark']) ?> 
                                    <span class="route-arrow">→</span> 
                                    <?= htmlspecialchars($row['dropoff_landmark']) ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $status_color_class ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($driver_display): ?>
                                        <span class="driver-name">🚗 <?= htmlspecialchars($driver_display) ?></span>
                                    <?php else: ?>
                                        <span class="driver-unassigned">⟳ Searching...</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.85rem;">
                                    <?= date('M d, Y', strtotime($row['created_at'])) ?><br>
                                    <small style="color: #9ca3af;"><?= date('h:i A', strtotime($row['created_at'])) ?></small>
                                </td>
                                <td style="white-space: nowrap;">
                                    <a href="qr.php?id=<?= $row['id'] ?>" class="btn-view">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    <p class="text-lg mb-2">No bookings found</p>
                    <?php if (!empty($search_code) || !empty($search_date)): ?>
                        <p>No bookings match your search criteria.</p>
                        <a href="my-bookings.php<?= !empty($status_filter) ? '?status=' . urlencode($status_filter) : '' ?>" class="btn-reset" style="display: inline-flex; margin-top: 1rem;">
                            Clear Search
                        </a>
                    <?php else: ?>
                        <p>You don't have any bookings yet.</p>
                        <a href="create-booking.php" class="btn-search" style="display: inline-flex; margin-top: 1rem;">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Create Your First Booking
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>