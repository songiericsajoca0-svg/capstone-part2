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

// Kunin ang toda kung saan ang admin na ito ang may-ari
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
    // Kung walang TODA, wala siyang makikitang drivers
    $toda_id_for_filter = 0;
    $toda_names = ['No TODA Assigned'];
}

// ============================================
// LOGIC FOR ADDING DRIVER (with toda assignment)
// ============================================

if (isset($_POST['add_driver'])) {
    $name     = mysqli_real_escape_string($conn, $_POST['name']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $contact  = mysqli_real_escape_string($conn, $_POST['contact']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = 'driver';
    $profile  = 'default.png';

    // Check if email exists
    $checkEmail = mysqli_query($conn, "SELECT email FROM users WHERE email = '$email'");

    if (mysqli_num_rows($checkEmail) > 0) {
        $error = "This email is already in use.";
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert into users table
            $sql = "INSERT INTO users (name, email, password, contact, profile, role) 
                    VALUES ('$name', '$email', '$password', '$contact', '$profile', '$role')";
            
            if (mysqli_query($conn, $sql)) {
                $new_driver_id = mysqli_insert_id($conn);
                
                // Assign driver to the admin's TODA
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
// GET DRIVER STATISTICS (FILTERED BY TODA)
// ============================================

// Get total drivers under this admin's TODA
$total_drivers_query = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM toda_drivers td 
    WHERE td.toda_id = $toda_id_for_filter
");
$total_drivers = $total_drivers_query ? $total_drivers_query->fetch_assoc()['cnt'] : 0;

// Get active bookings count for drivers under this TODA
$active_bookings_query = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM bookings b 
    WHERE b.toda_id = $toda_id_for_filter 
    AND b.status IN ('PENDING', 'CONFIRMED', 'IN_PROGRESS')
");
$active_bookings = $active_bookings_query ? $active_bookings_query->fetch_assoc()['cnt'] : 0;

// Get total earnings for drivers under this TODA
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

        .drivers-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        /* Stats Cards */
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
        .stat-card.active::before { background: #f59e0b; }
        .stat-card.earnings::before { background: #10b981; }

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
        .stat-card.active .stat-icon { background: #fed7aa; color: #f59e0b; }
        .stat-card.earnings .stat-icon { background: #d1fae5; color: #10b981; }

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
        .stat-card.active .stat-value { color: #f59e0b; }
        .stat-card.earnings .stat-value { color: #10b981; }

        /* Cards */
        .card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-3px);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1.2rem 1.5rem;
            color: white;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* TODA Info Banner */
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

        .toda-banner .toda-name {
            font-weight: bold;
            color: #4338ca;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .toda-banner .toda-name i {
            font-size: 1rem;
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
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group label i {
            margin-right: 0.3rem;
        }

        .form-control {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
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

        /* Alert Messages */
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
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* Table Styles - Fixed and Improved */
        .table-wrapper {
            overflow-x: auto;
            padding: 0;
        }

        .drivers-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .drivers-table th {
            background: #f8fafc;
            padding: 1rem 1rem;
            text-align: left;
            font-size: 0.7rem;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .drivers-table th i {
            margin-right: 0.5rem;
            font-size: 0.7rem;
        }

        .drivers-table td {
            padding: 1rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.85rem;
            vertical-align: middle;
        }

        .drivers-table tbody tr {
            transition: all 0.3s ease;
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
            font-size: 0.9rem;
        }

        .driver-email {
            font-size: 0.7rem;
            color: #9ca3af;
            margin-top: 0.2rem;
        }

        /* Stats badges in table */
        .stat-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
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
            cursor: pointer;
            border: none;
        }

        .btn-edit {
            background: #e0e7ff;
            color: #667eea;
        }

        .btn-edit:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #fee2e2;
            color: #ef4444;
        }

        .btn-delete:hover {
            background: #ef4444;
            color: white;
            transform: translateY(-2px);
        }

        .btn-view {
            background: #d1fae5;
            color: #10b981;
        }

        .btn-view:hover {
            background: #10b981;
            color: white;
            transform: translateY(-2px);
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

        .toda-badge {
            display: inline-block;
            background: #e0e7ff;
            color: #4338ca;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: bold;
        }

        /* Contact column */
        .contact-number {
            font-family: monospace;
            font-size: 0.8rem;
            color: #374151;
        }

        @media (max-width: 768px) {
            .drivers-container {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .drivers-table th, 
            .drivers-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .action-buttons {
                flex-direction: row;
                justify-content: center;
            }
            
            .toda-banner {
                flex-direction: column;
                text-align: center;
            }

            .driver-name {
                font-size: 0.8rem;
            }

            .driver-email {
                font-size: 0.65rem;
            }
        }

        @media (max-width: 480px) {
            .drivers-table th:nth-child(4),
            .drivers-table td:nth-child(4) {
                display: none;
            }
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="drivers-container">
    <!-- TODA Info Banner -->
    <div class="toda-banner">
        <div class="toda-name">
            <i class="fas fa-map-marker-alt"></i>
            Your TODA: <strong><?= htmlspecialchars(implode(', ', $toda_names)) ?></strong>
        </div>
        <div>
            <span class="toda-badge">
                <i class="fas fa-user-shield"></i> Admin: <?= htmlspecialchars($current_admin_name) ?>
            </span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?= $total_drivers ?></div>
            <div class="stat-label">Total Drivers in Your TODA</div>
        </div>
        
       
        
        <div class="stat-card earnings">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value">₱ <?= number_format($total_earnings, 0) ?></div>
            <div class="stat-label">Total TODA Earnings</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Add Driver Form - Left Side -->
        <div class="lg:col-span-4">
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-user-plus"></i> Register New Driver
                    </h3>
                </div>
                <div class="p-6">
                    <?php if($message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= $message ?>
                        </div>
                    <?php endif; ?>
                    <?php if($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" name="name" class="form-control" placeholder="Enter driver's full name" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="driver@example.com" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Contact Number</label>
                            <input type="text" name="contact" class="form-control" placeholder="09xx xxx xxxx" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Temporary Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Enter temporary password" required>
                        </div>

                        <button type="submit" name="add_driver" class="btn-submit">
                            <i class="fas fa-save"></i> Create Driver Account
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Drivers List - Right Side (FILTERED BY TODA) -->
        <div class="lg:col-span-8">
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-list"></i> Drivers List
                        <span class="text-xs opacity-80 ml-2">(Only drivers under your TODA)</span>
                    </h3>
                </div>
                <div class="table-wrapper">
                    <?php
                    // Get drivers only from this admin's TODA using JOIN
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
                                <th><i class="fas fa-image"></i> Profile</th>
                                <th><i class="fas fa-info-circle"></i> Driver Info</th>
                                <th><i class="fas fa-phone-alt"></i> Contact</th>
                                <th><i class="fas fa-chart-simple"></i> Stats</th>
                                <th><i class="fas fa-cog"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($res)):
                                $img_filename = $row['profile'] ?: 'default.png';
                                $img_path = "../uploads/drivers_profile/" . $img_filename;
                                if(!file_exists($img_path)) $img_path = "../uploads/drivers_profile/default.png";
                                
                                // Get driver stats
                                $driver_stats = $conn->query("
                                    SELECT 
                                        COUNT(*) as total_trips,
                                        COALESCE(SUM(fare_amount), 0) as total_earnings
                                    FROM bookings 
                                    WHERE driver_id = {$row['id']} AND status = 'COMPLETED'
                                ");
                                $stats = $driver_stats->fetch_assoc();
                            ?>
                            <tr>
                                <td>
                                    <img src="<?= $img_path ?>" class="driver-avatar" onerror="this.src='../uploads/drivers_profile/default.png'">
                                </td>
                                <td>
                                    <div class="driver-name"><?= htmlspecialchars($row['name']) ?></div>
                                    <div class="driver-email"><?= htmlspecialchars($row['email']) ?></div>
                                </td>
                                <td class="contact-number"><?= htmlspecialchars($row['contact']) ?></td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <span class="stat-badge stat-trips">
                                            <i class="fas fa-trip"></i> Trips: <?= $stats['total_trips'] ?? 0 ?>
                                        </span>
                                        <span class="stat-badge stat-earnings">
                                            <i class="fas fa-coins"></i> ₱ <?= number_format($stats['total_earnings'] ?? 0, 0) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="/edit_driver.php?id=<?= $row['id'] ?>" class="btn-icon btn-edit" title="Edit Driver">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="/view_driver_bookings.php?id=<?= $row['id'] ?>" class="btn-icon btn-view" title="View Bookings">
                                            <i class="fas fa-calendar-alt"></i>
                                        </a>
                                        <a href="/delete_driver.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this driver? This action cannot be undone.')" class="btn-icon btn-delete" title="Delete Driver">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
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
                            <p class="text-xs mt-1">Use the form on the left to add your first driver to this TODA.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>