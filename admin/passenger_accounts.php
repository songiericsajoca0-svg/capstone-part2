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

// Base Filter: Passenger only
$role_filter = "role = 'passenger'";

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
        id, name, email, contact, profile, role
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Management | Admin</title>
    <?php include '../includes/header.php'; ?>
    
    <style>
        @font-face {
            font-family: 'NaruMonoDemo';
            src: url('../assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        body, input, button, table, th, td, div, h2, h3, p, a, span {
            font-family: 'NaruMonoDemo', monospace !important;
        }

        .main-content {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* UI Enhancements */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .search-container {
            background: #fff;
            padding: 1.25rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            outline: none;
            transition: border 0.2s;
        }

        .search-input:focus {
            border-color: #1e40af;
        }

        .table-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 16px;
            border-bottom: 2px solid #f1f5f9;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 0.9rem;
        }

        tr:hover {
            background-color: #fcfdfe;
        }

        .profile-img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }

        .role-badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: bold;
        }

        .btn-view {
            background: #1e40af;
            color: white !important;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8rem;
            transition: background 0.2s;
            display: inline-block;
        }

        .btn-view:hover {
            background: #1e3a8a;
        }

        .pagination {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            gap: 8px;
        }

        .page-link {
            padding: 10px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            color: #1e40af;
            background: #fff;
            transition: all 0.2s;
        }

        .page-link.active {
            background: #1e40af;
            color: #fff;
            border-color: #1e40af;
        }

        .no-data-card {
            text-align: center;
            padding: 4rem 2rem;
            background: #fff;
            border-radius: 12px;
            color: #64748b;
        }
    </style>
</head>
<body class="bg-gray-50">

<div class="main-content">
    <div class="page-header">
        <h2 style="color: #0f172a; font-size: 1.8rem; margin: 0;">Passenger Accounts</h2>
        <span style="color: #64748b; font-size: 0.9rem;">Total: <?= $total_records ?> registered</span>
    </div>

    <form method="GET" class="search-container">
        <input type="text" name="search" class="search-input" 
               placeholder="Search name, email, or contact number..." 
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary" style="padding: 10px 24px; border-radius: 8px;">Search</button>
        <?php if($search): ?>
            <a href="passenger_accounts.php" style="padding: 10px 15px; color: #ef4444; text-decoration: none; align-self: center;">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($passengers)): ?>
        <div class="no-data-card">
            <h3 style="margin-bottom: 0.5rem;">No passengers found</h3>
            <p>We couldn't find any accounts matching your criteria.</p>
        </div>
    <?php else: ?>
        <div class="table-card">
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 80px;">Profile</th>
                            <th>Passenger Name</th>
                            <th>Email Address</th>
                            <th>Contact</th>
                            <th>Account Role</th>
                            <th style="text-align: center;">Action</th>
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
                                <div style="font-weight: bold; color: #1e293b;"><?= htmlspecialchars($p['name']) ?></div>
                                <div style="font-size: 0.75rem; color: #94a3b8;">ID: #<?= $p['id'] ?></div>
                            </td>
                            <td style="color: #475569;"><?= htmlspecialchars($p['email']) ?></td>
                            <td style="color: #475569;"><?= htmlspecialchars($p['contact']) ?></td>
                            <td><span class="role-badge"><?= htmlspecialchars($p['role']) ?></span></td>
                            <td style="text-align: center;">
                                <a href="view_passenger.php?id=<?= $p['id'] ?>" class="btn-view">VIEW PROFILE</a>
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
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" 
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