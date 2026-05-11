<?php
// 1. Setup Connections and Auth
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'admin') header("Location: ../index.php");

$current_admin_id = $_SESSION['user_id'];
$current_admin_name = $_SESSION['name'];

$message = "";
$error = "";

// ============================================
// GET THE TODA OWNED BY THIS ADMIN
// ============================================

$admin_toda_query = $conn->query("
    SELECT id, toda_name 
    FROM todas 
    WHERE user_id = $current_admin_id AND role = 'admin'
");

$toda_ids = [];
$toda_names = [];
$toda_id_for_filter = 0;

if ($admin_toda_query && $admin_toda_query->num_rows > 0) {
    $toda = $admin_toda_query->fetch_assoc();
    $toda_ids[] = $toda['id'];
    $toda_names[] = $toda['toda_name'];
    $toda_id_for_filter = $toda['id'];
} else {
    $toda_id_for_filter = 0;
    $toda_names = ['No TODA Assigned'];
}

// ============================================
// LOGIC FOR ADDING DRIVER
// ============================================

if (isset($_POST['add_driver'])) {
    $name     = mysqli_real_escape_string($conn, $_POST['name']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $contact  = mysqli_real_escape_string($conn, $_POST['contact']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = 'driver';
    $profile  = 'default.png';

    $checkEmail = mysqli_query($conn, "SELECT email FROM users WHERE email = '$email'");

    if (mysqli_num_rows($checkEmail) > 0) {
        $error = "This email is already in use.";
    } else {
        mysqli_begin_transaction($conn);
        
        try {
            $sql = "INSERT INTO users (name, email, password, contact, profile, role) 
                    VALUES ('$name', '$email', '$password', '$contact', '$profile', '$role')";
            
            if (mysqli_query($conn, $sql)) {
                $new_driver_id = mysqli_insert_id($conn);
                
                $assign_sql = "INSERT INTO toda_drivers (toda_id, driver_id, driver_name) 
                               VALUES ('$toda_id_for_filter', '$new_driver_id', '$name')";
                
                if (mysqli_query($conn, $assign_sql)) {
                    mysqli_commit($conn);
                    $message = "Driver account successfully created and assigned to " . implode(', ', $toda_names) . "!";
                } else {
                    throw new Exception("Failed to assign driver to TODA");
                }
            } else {
                throw new Exception("Failed to create driver account");
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error: " . $e->getMessage();
        }
    }
}

// ============================================
// GET DRIVER STATISTICS
// ============================================

$total_drivers_query = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM toda_drivers td 
    WHERE td.toda_id = $toda_id_for_filter
");
$total_drivers = $total_drivers_query ? $total_drivers_query->fetch_assoc()['cnt'] : 0;

$total_earnings_query = $conn->query("
    SELECT COALESCE(SUM(b.fare_amount), 0) as total 
    FROM bookings b 
    WHERE b.toda_id = $toda_id_for_filter 
    AND b.status = 'COMPLETED'
");
$total_earnings = $total_earnings_query ? $total_earnings_query->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Drivers - <?= htmlspecialchars(implode(', ', $toda_names)) ?> - GoTrike</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
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

        i, .fas, .far, .fab, .fa {
            font-family: "Font Awesome 6 Free" !important;
            font-weight: 400;
        }

        .fas, .fa-solid {
            font-weight: 900 !important;
        }

        body {
            background: linear-gradient(135deg, #f0f2f5 0%, #e2e8f0 100%);
            min-height: 100vh;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 1rem 2rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .dashboard-header h1 {
            font-size: 1.5rem;
            margin: 0;
        }

        .admin-badge {
            background: rgba(255,255,255,0.2);
            padding: 0.3rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.3rem 0.8rem;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 0.8rem;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }

        .logout-btn {
            background: #ef4444;
            padding: 0.3rem 1rem;
            border-radius: 8px;
        }

        .logout-btn:hover {
            background: #dc2626;
        }

        .drivers-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* Stats Cards */
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
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1f2937;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.7rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0.3rem;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 1.5rem;
            color: white;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* TODA Banner */
        .toda-banner {
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .toda-name {
            font-weight: bold;
            color: #4338ca;
            font-size: 0.9rem;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: bold;
            color: #6b7280;
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }

        /* Alert */
        .alert {
            padding: 0.8rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* Table - FIXED */
        .table-responsive {
            overflow-x: auto;
        }

        .drivers-table {
            width: 100%;
            border-collapse: collapse;
        }

        .drivers-table th {
            background: #f8fafc;
            padding: 1rem 1rem;
            text-align: left;
            font-size: 0.7rem;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
        }

        .drivers-table td {
            padding: 1rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.85rem;
            vertical-align: middle;
        }

        .drivers-table tbody tr {
            cursor: pointer;
            transition: background 0.2s;
        }

        .drivers-table tbody tr:hover {
            background: #f8fafc;
        }

        .driver-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }

        .driver-name {
            font-weight: bold;
            color: #1f2937;
        }

        .driver-email {
            font-size: 0.7rem;
            color: #9ca3af;
            margin-top: 0.2rem;
        }

        .stat-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .stat-trips {
            background: #e0e7ff;
            color: #4338ca;
        }

        .stat-earnings {
            background: #d1fae5;
            color: #065f46;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-start;
            flex-wrap: wrap;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .btn-edit {
            background: #e0e7ff;
            color: #667eea;
        }

        .btn-edit:hover {
            background: #667eea;
            color: white;
        }

        .btn-delete {
            background: #fee2e2;
            color: #ef4444;
        }

        .btn-delete:hover {
            background: #ef4444;
            color: white;
        }

        .btn-view {
            background: #d1fae5;
            color: #10b981;
        }

        .btn-view:hover {
            background: #10b981;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Grid Layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        @media (min-width: 992px) {
            .two-columns {
                grid-template-columns: 380px 1fr;
            }
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 28px;
            max-width: 500px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
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

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1rem;
        }

        .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .driver-detail-row {
            display: flex;
            padding: 0.7rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .driver-detail-label {
            width: 110px;
            font-weight: bold;
            color: #6b7280;
            font-size: 0.7rem;
            text-transform: uppercase;
        }

        .driver-detail-value {
            flex: 1;
            color: #1f2937;
            font-size: 0.85rem;
        }

        .detail-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
        }

        .detail-header {
    text-align: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

        .detail-name {
            font-size: 1.1rem;
            font-weight: bold;
            margin-top: 0.5rem;
        }

        .detail-badge {
            background: #e0e7ff;
            color: #4338ca;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            font-size: 0.65rem;
            margin-left: 0.5rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .text-gray { color: #9ca3af; }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>


<div class="drivers-container">
    <!-- TODA Banner -->
    <div class="toda-banner">
        <span class="toda-name"><i class="fas fa-building"></i> Your TODA: <strong><?= htmlspecialchars(implode(', ', $toda_names)) ?></strong></span>
        <span><i class="fas fa-user-shield"></i> Admin: <?= htmlspecialchars($current_admin_name) ?></span>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $total_drivers ?></div>
                <div class="stat-label">TOTAL DRIVERS IN YOUR TODA</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-info">
                <div class="stat-value">₱ <?= number_format($total_earnings, 0) ?></div>
                <div class="stat-label">TOTAL TODA EARNINGS</div>
            </div>
        </div>
    </div>

    <!-- Two Columns -->
    <div class="two-columns">
        <!-- Left: Add Driver Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Register New Driver</h3>
            </div>
            <div class="card-body">
                <?php if($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $message ?></div>
                <?php endif; ?>
                <?php if($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> FULL NAME</label>
                        <input type="text" name="name" class="form-control" placeholder="Enter driver's full name" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> EMAIL ADDRESS</label>
                        <input type="email" name="email" class="form-control" placeholder="driver@example.com" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> CONTACT NUMBER</label>
                        <input type="text" name="contact" class="form-control" placeholder="09xx xxx xxxx" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> TEMPORARY PASSWORD</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter temporary password" required>
                    </div>
                    <button type="submit" name="add_driver" class="btn-submit">
                        <i class="fas fa-save"></i> Create Driver Account
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Drivers List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Drivers List <span style="font-size: 0.7rem; opacity: 0.8;">(Click any row to view full details)</span></h3>
            </div>
            <div class="table-responsive">
                <?php
                $res = mysqli_query($conn, "
                    SELECT u.* 
                    FROM users u
                    INNER JOIN toda_drivers td ON u.id = td.driver_id
                    WHERE u.role = 'driver' 
                    AND td.toda_id = $toda_id_for_filter
                    ORDER BY u.id DESC
                ");
                
                if($res && mysqli_num_rows($res) > 0):
                ?>
                <table class="drivers-table">
                    <thead>
                        <tr>
                            <th>PROFILE</th>
                            <th>DRIVER INFO</th>
                            <th>CONTACT</th>
                            <th>STATS</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($res)):
                            $img_filename = $row['profile'] ?: 'default.png';
                            $img_path = "../uploads/drivers_profile/" . $img_filename;
                            if(!file_exists($img_path)) $img_path = "../uploads/drivers_profile/default.png";
                            
                            $driver_stats = $conn->query("
                                SELECT 
                                    COUNT(*) as total_trips,
                                    COALESCE(SUM(fare_amount), 0) as total_earnings
                                FROM bookings 
                                WHERE driver_id = {$row['id']} AND status = 'COMPLETED'
                            ");
                            $stats = $driver_stats->fetch_assoc();
                        ?>
                        <tr onclick="openDriverModal(<?= $row['id'] ?>)" style="cursor: pointer;">
                            <td><img src="<?= $img_path ?>" class="driver-avatar" onerror="this.src='../uploads/drivers_profile/default.png'"></td>
                            <td>
                                <div class="driver-name"><?= htmlspecialchars($row['name']) ?></div>
                                <div class="driver-email"><?= htmlspecialchars($row['email']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($row['contact']) ?></td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <span class="stat-badge stat-trips"><i class="fas fa-trip"></i> Trips: <?= $stats['total_trips'] ?? 0 ?></span>
                                    <span class="stat-badge stat-earnings"><i class="fas fa-coins"></i> ₱ <?= number_format($stats['total_earnings'] ?? 0, 0) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons" onclick="event.stopPropagation()">
                                    <a href="edit_driver.php?id=<?= $row['id'] ?>" class="btn-icon btn-edit" title="Edit Driver"><i class="fas fa-edit"></i></a>
                                    <a href="view_driver_bookings.php?id=<?= $row['id'] ?>" class="btn-icon btn-view" title="View Bookings"><i class="fas fa-calendar-alt"></i></a>
                                    <a href="delete_driver.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this driver?')" class="btn-icon btn-delete" title="Delete Driver"><i class="fas fa-trash-alt"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <p>No drivers found in <?= htmlspecialchars(implode(', ', $toda_names)) ?>.</p>
                        <p style="font-size: 0.7rem;">Use the form on the left to add your first driver.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Driver Details Modal -->
<div id="driverModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-id-card"></i> Driver Details</h3>
            <button class="modal-close" onclick="closeDriverModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="driverModalBody">
            <div style="text-align: center; padding: 2rem;">
                <div class="spinner"></div>
                <p>Loading driver details...</p>
            </div>
        </div>
    </div>
</div>

<script>
function openDriverModal(driverId) {
    const modal = document.getElementById('driverModal');
    const modalBody = document.getElementById('driverModalBody');
    
    modalBody.innerHTML = `<div style="text-align: center; padding: 2rem;"><div class="spinner"></div><p>Loading driver details...</p></div>`;
    modal.classList.add('active');
    
    fetch(`ajax_get_driver_details.php?id=${driverId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response:', data); // For debugging
            
            if (data.success) {
                const d = data.driver;
                const todaName = data.toda_name || 'Not assigned';
                let profileImg = '../uploads/drivers_profile/default.png';
                
                if (d.profile && d.profile !== 'default.png' && d.profile !== '') {
                    profileImg = '../uploads/drivers_profile/' + d.profile;
                }
                
                // I-check kung may laman ang total_trips at total_earnings
                const totalTrips = d.total_trips || 0;
                const totalEarnings = d.total_earnings || 0;
                
                modalBody.innerHTML = `
                    <div class="detail-header">
                        <img src="${profileImg}" class="detail-avatar" onerror="this.src='../uploads/drivers_profile/default.png'">
                        <div class="detail-name">
                            ${escapeHtml(d.name)}
                            ${d.body_number ? `<span class="detail-badge">🔢 ${escapeHtml(d.body_number)}</span>` : ''}
                        </div>
                        <div style="font-size: 0.7rem; color: #667eea; margin-top: 0.3rem;"><i class="fas fa-id-card"></i> ${escapeHtml(d.role || 'Driver')}</div>
                    </div>
                    <div class="driver-detail-row"><div class="driver-detail-label"><i class="fas fa-envelope"></i> Email</div><div class="driver-detail-value">${escapeHtml(d.email)}</div></div>
                    <div class="driver-detail-row"><div class="driver-detail-label"><i class="fas fa-phone"></i> Contact</div><div class="driver-detail-value">${escapeHtml(d.contact)}</div></div>
                    <div class="driver-detail-row"><div class="driver-detail-label"><i class="fas fa-building"></i> TODA</div><div class="driver-detail-value">${escapeHtml(todaName)}</div></div>
                    <div class="driver-detail-row"><div class="driver-detail-label"><i class="fas fa-grip-lines"></i> Body Number</div><div class="driver-detail-value">${d.body_number ? escapeHtml(d.body_number) : '<span class="text-gray">Not set</span>'}</div></div>
                    <div class="driver-detail-row"><div class="driver-detail-label"><i class="fas fa-plate"></i> Plate Number</div><div class="driver-detail-value">${d.plate_number ? escapeHtml(d.plate_number) : '<span class="text-gray">Not set</span>'}</div></div>
                    <div class="driver-detail-row"><div class="driver-detail-label"><i class="fas fa-palette"></i> TODA Color</div><div class="driver-detail-value">${d.toda_color ? `<span style="display: inline-block; width: 14px; height: 14px; background: ${escapeHtml(d.toda_color)}; border-radius: 3px; margin-right: 6px; vertical-align: middle;"></span> ${escapeHtml(d.toda_color)}` : '<span class="text-gray">Not set</span>'}</div></div>
                    <div class="driver-detail-row"><div class="driver-detail-label"><i class="fas fa-chart-line"></i> Total Trips</div><div class="driver-detail-value"><i class="fas fa-trip"></i> ${totalTrips} completed ${totalTrips == 1 ? 'trip' : 'trips'}</div></div>
                    <div class="driver-detail-row"><div class="driver-detail-label"><i class="fas fa-coins"></i> Total Earnings</div><div class="driver-detail-value"><i class="fas fa-coins"></i> ₱ ${totalEarnings.toLocaleString()}</div></div>
                    <div class="driver-detail-row"><div class="driver-detail-label"><i class="fas fa-calendar-alt"></i> Member Since</div><div class="driver-detail-value"><i class="fas fa-calendar-alt"></i> ${d.created_at ? new Date(d.created_at).toLocaleDateString() : 'N/A'}</div></div>
                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: center; gap: 0.75rem; flex-wrap: wrap;">
                    </div>
                `;
            } else {
                modalBody.innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: #ef4444;">
                        <i class="fas fa-exclamation-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p>${data.message || 'Failed to load driver details.'}</p>
                        <button onclick="closeDriverModal()" class="btn-submit" style="margin-top: 1rem; width: auto; padding: 0.5rem 1.5rem;">Close</button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #ef4444;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p>Error loading driver details.</p>
                    <p style="font-size: 0.7rem;">${error.message}</p>
                    <button onclick="closeDriverModal()" class="btn-submit" style="margin-top: 1rem; width: auto; padding: 0.5rem 1.5rem;">Close</button>
                </div>
            `;
        });
}

function closeDriverModal() {
    document.getElementById('driverModal').classList.remove('active');
}

window.onclick = function(event) {
    const modal = document.getElementById('driverModal');
    if (event.target === modal) closeDriverModal();
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>