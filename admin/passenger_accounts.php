<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Only ADMIN can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

// ── Pagination & Search Setup ──────────────────────────────────────────────
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = 15;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? 'all';

// Base Filter: Passenger only
$role_filter = "role = 'passenger'";

// Add status filter if column exists
$check_status = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
$has_status = $check_status->num_rows > 0;
if ($has_status && $status_filter !== 'all') {
    $role_filter .= " AND status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}

// Search Logic
$search_sql = "";
$search_params = [];
$search_types = "";

if ($search !== '') {
    $search_sql = " AND (name LIKE ? OR email LIKE ? OR contact LIKE ?)";
    $like_val = "%$search%";
    $search_params = [$like_val, $like_val, $like_val];
    $search_types = "sss"; 
}

// 1. Get Total Count
$count_query = "SELECT COUNT(*) FROM users WHERE $role_filter $search_sql";
$count_stmt = $conn->prepare($count_query);

if ($search !== '') {
    $count_stmt->bind_param($search_types, ...$search_params);
}

$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_row()[0];
$total_pages   = max(1, ceil($total_records / $limit));

// 2. Get Passenger List
$query = "
    SELECT 
        id, name, email, contact, profile, role" . ($has_status ? ", status" : "") . "
    FROM users 
    WHERE $role_filter $search_sql 
    ORDER BY id DESC 
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$final_types = $search_types . "ii"; 
$final_params = array_merge($search_params, [$limit, $offset]);

$stmt->bind_param($final_types, ...$final_params);
$stmt->execute();
$passengers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get passenger statistics
$total_passengers = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'passenger'")->fetch_assoc()['cnt'];
$active_passengers = $has_status ? $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'passenger' AND status = 'active'")->fetch_assoc()['cnt'] : $total_passengers;
$inactive_passengers = $has_status ? $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'passenger' AND status = 'inactive'")->fetch_assoc()['cnt'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Management | GoTrike</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
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

        /* Keep Font Awesome icons with their original font */
        i, .fas, .far, .fab, .fa {
            font-family: "Font Awesome 6 Free" !important;
            font-weight: 400;
        }

        .fas, .fa-solid {
            font-weight: 900 !important;
        }

        .far {
            font-weight: 400 !important;
        }

        .fab, .fab i {
            font-family: "Font Awesome 6 Brands" !important;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .main-content {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            cursor: pointer;
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
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
        }

        .stat-card:hover::before {
            height: 6px;
        }

        .stat-card.total::before { background: #667eea; }
        .stat-card.active::before { background: #10b981; }
        .stat-card.inactive::before { background: #ef4444; }

        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-card.total .stat-icon { background: #e0e7ff; color: #667eea; }
        .stat-card.active .stat-icon { background: #d1fae5; color: #10b981; }
        .stat-card.inactive .stat-icon { background: #fee2e2; color: #ef4444; }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }

        .stat-label {
            font-size: 0.7rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card.total .stat-value { color: #667eea; }
        .stat-card.active .stat-value { color: #10b981; }
        .stat-card.inactive .stat-value { color: #ef4444; }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 24px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .page-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-header h2 i {
            color: #667eea;
        }

        .total-badge {
            background: #f1f5f9;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            color: #475569;
        }

        .total-badge i {
            margin-right: 0.3rem;
            color: #667eea;
        }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: bold;
            transition: all 0.3s ease;
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .filter-btn i {
            margin-right: 0.3rem;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        .filter-btn:hover:not(.active) {
            background: #f1f5f9;
            transform: translateY(-2px);
        }

        /* Search Container */
        .search-container {
            background: white;
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-input-wrapper {
            flex: 1;
            position: relative;
        }

        .search-input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-input {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            outline: none;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .btn-search {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.8rem 1.8rem;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: bold;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }

        .btn-clear {
            background: #f1f5f9;
            color: #ef4444;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 14px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-clear:hover {
            background: #fee2e2;
            transform: translateY(-2px);
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-size: 0.7rem;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        th i {
            margin-right: 0.5rem;
            font-size: 0.7rem;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 0.85rem;
        }

        tr {
            transition: all 0.3s ease;
        }

        tr:hover {
            background: #f8fafc;
        }

        .profile-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }

        .passenger-name {
            font-weight: bold;
            color: #1f2937;
        }

        .passenger-id {
            font-size: 0.7rem;
            color: #9ca3af;
            margin-top: 0.2rem;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: #e0e7ff;
            color: #667eea;
            padding: 0.25rem 0.8rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.7rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.7rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(102,126,234,0.3);
        }

        /* Pagination */
        .pagination {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .page-link {
            padding: 0.5rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            text-decoration: none;
            color: #667eea;
            background: white;
            transition: all 0.3s ease;
            min-width: 40px;
            text-align: center;
        }

        .page-link:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .page-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        .no-data-card {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 24px;
            color: #94a3b8;
        }

        .no-data-card i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                text-align: center;
            }
            
            .search-container {
                flex-direction: column;
            }
            
            .filter-tabs {
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="main-content">
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card total" onclick="window.location.href='?status=all&search=<?= urlencode($search) ?>'">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?= $total_passengers ?></div>
            <div class="stat-label">Total Passengers</div>
        </div>
        <div class="stat-card active" onclick="window.location.href='?status=active&search=<?= urlencode($search) ?>'">
            <div class="stat-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-value"><?= $active_passengers ?></div>
            <div class="stat-label">Active Passengers</div>
        </div>
        <?php if ($has_status): ?>
        <div class="stat-card inactive" onclick="window.location.href='?status=inactive&search=<?= urlencode($search) ?>'">
            <div class="stat-icon">
                <i class="fas fa-user-slash"></i>
            </div>
            <div class="stat-value"><?= $inactive_passengers ?></div>
            <div class="stat-label">Inactive Passengers</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <h2>
            <i class="fas fa-user-friends"></i>
            Passenger Accounts
        </h2>
        <div class="total-badge">
            <i class="fas fa-database"></i> Total: <?= $total_records ?> registered
        </div>
    </div>

    <!-- Filter Tabs -->
    <?php if ($has_status): ?>
    <div class="filter-tabs">
        <a href="?status=all&search=<?= urlencode($search) ?>" class="filter-btn <?= $status_filter == 'all' ? 'active' : '' ?>">
            <i class="fas fa-list"></i> All
        </a>
        <a href="?status=active&search=<?= urlencode($search) ?>" class="filter-btn <?= $status_filter == 'active' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Active
        </a>
        <a href="?status=inactive&search=<?= urlencode($search) ?>" class="filter-btn <?= $status_filter == 'inactive' ? 'active' : '' ?>">
            <i class="fas fa-times-circle"></i> Inactive
        </a>
    </div>
    <?php endif; ?>

    <!-- Search Form -->
    <form method="GET" class="search-container">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
        <div class="search-input-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" name="search" class="search-input" 
                   placeholder="Search by name, email, or contact number..." 
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn-search">
            <i class="fas fa-search"></i> Search
        </button>
        <?php if($search): ?>
            <a href="?status=<?= urlencode($status_filter) ?>" class="btn-clear">
                <i class="fas fa-times"></i> Clear
            </a>
        <?php endif; ?>
    </form>

    <?php if (empty($passengers)): ?>
        <div class="no-data-card">
            <i class="fas fa-user-slash"></i>
            <h3>No passengers found</h3>
            <p>We couldn't find any accounts matching your criteria.</p>
        </div>
    <?php else: ?>
        <div class="table-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-image"></i> Profile</th>
                            <th><i class="fas fa-info-circle"></i> Passenger Info</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-phone"></i> Contact</th>
                            <th><i class="fas fa-tag"></i> Role</th>
                            <?php if ($has_status): ?>
                            <th><i class="fas fa-circle"></i> Status</th>
                            <?php endif; ?>
                            <th><i class="fas fa-cog"></i> Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($passengers as $p): ?>
                        <tr>
                            <td>
                                <?php 
                                    $photo = !empty($p['profile']) && file_exists('../uploads/'.$p['profile']) 
                                             ? '../uploads/'.$p['profile'] 
                                             : '../assets/default-user.png';
                                ?>
                                <img src="<?= $photo ?>" class="profile-img" alt="User">
                            </td>
                            <td>
                                <div class="passenger-name"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="passenger-id"><i class="fas fa-id-card"></i> ID: #<?= $p['id'] ?></div>
                            </td>
                            <td style="color: #475569;"><?= htmlspecialchars($p['email']) ?></td>
                            <td style="color: #475569;"><?= htmlspecialchars($p['contact']) ?></td>
                            <td><span class="role-badge"><i class="fas fa-user"></i> <?= htmlspecialchars($p['role']) ?></span></td>
                            <?php if ($has_status): ?>
                            <td>
                                <span class="status-badge status-<?= $p['status'] ?? 'active' ?>">
                                    <i class="fas fa-<?= ($p['status'] ?? 'active') == 'active' ? 'circle' : 'circle-slash' ?>"></i>
                                    <?= ucfirst($p['status'] ?? 'Active') ?>
                                </span>
                            </td>
                            <?php endif; ?>
                            <td>
                                <a href="/view_passenger.php?id=<?= $p['id'] ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View Profile
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" 
                   class="page-link <?= $i == $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>