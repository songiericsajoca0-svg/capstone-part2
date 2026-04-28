<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$current_admin_id = $_SESSION['user_id']; // Ito ay galing sa users table
$current_admin_name = $_SESSION['name'];

// ============================================
// GET THE TODA OWNED BY THIS ADMIN
// ============================================

// Kunin ang toda kung saan ang admin na ito ang may-ari (user_id = current admin id)
$admin_toda_query = $conn->query("
    SELECT id, toda_name 
    FROM todas 
    WHERE user_id = $current_admin_id AND role = 'admin'
");

$toda_ids = [];
$toda_names = [];

if ($admin_toda_query && $admin_toda_query->num_rows > 0) {
    while($toda = $admin_toda_query->fetch_assoc()) {
        $toda_ids[] = $toda['id'];
        $toda_names[] = $toda['toda_name'];
    }
} else {
    // Kung walang TODA na nakatalaga sa admin na ito, walang makikitang data
    $toda_ids = [0]; // Magreresulta sa walang data
    $toda_names = ['No TODA Assigned'];
}

$toda_ids_str = implode(',', $toda_ids);

// Kunin ang lahat ng drivers sa ilalim ng TODA ng admin na ito
$driver_ids = [];
$drivers_query = $conn->query("
    SELECT DISTINCT td.driver_id, td.driver_name 
    FROM toda_drivers td 
    WHERE td.toda_id IN ($toda_ids_str)
");

while($driver = $drivers_query->fetch_assoc()) {
    $driver_ids[] = $driver['driver_id'];
}

// Create the SQL filter by toda_id
$toda_filter = "AND b.toda_id IN ($toda_ids_str)";

// ============================================
// PDF DOWNLOAD HANDLER
// ============================================
if (isset($_GET['download_pdf'])) {
    require_once '../vendor/autoload.php';
    
    // Fetch filtered data for PDF
    $total_bookings = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM bookings b 
        WHERE 1=1 $toda_filter
    ")->fetch_assoc()['cnt'];
    
    $completed = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM bookings b 
        WHERE status = 'COMPLETED' $toda_filter
    ")->fetch_assoc()['cnt'];
    
    $cancelled = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM bookings b 
        WHERE status = 'CANCELLED' $toda_filter
    ")->fetch_assoc()['cnt'];
    
    $this_month_completed = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM bookings b 
        WHERE status = 'COMPLETED' 
        AND MONTH(updated_at) = MONTH(CURDATE()) 
        AND YEAR(updated_at) = YEAR(CURDATE())
        $toda_filter
    ")->fetch_assoc()['cnt'];
    
    // Get all completed bookings - FILTERED
    $all_completed = $conn->query("
        SELECT 
            b.booking_code, 
            b.status, 
            b.updated_at as dropoff_time,
            b.created_at as pickup_time,
            u.name AS passenger,
            b.pickup_landmark,
            b.dropoff_landmark,
            b.fare_amount,
            COALESCE(b.driver_name, d.name, 'Unassigned') as driver_name,
            b.total_pax,
            b.trike_units,
            b.distance,
            b.notes
        FROM bookings b 
        JOIN users u ON b.passenger_id = u.id 
        LEFT JOIN users d ON b.driver_id = d.id
        WHERE b.status = 'COMPLETED' $toda_filter
        ORDER BY b.updated_at DESC
    ");
    
    // Monthly stats - FILTERED
    $monthly_stats = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled_count,
            SUM(CASE WHEN status = 'COMPLETED' THEN fare_amount ELSE 0 END) as total_revenue
        FROM bookings b
        WHERE 1=1 $toda_filter
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    
    // Driver performance stats - FILTERED (only this admin's drivers)
    $driver_stats = $conn->query("
        SELECT 
            COALESCE(b.driver_name, u.name) as driver_name,
            COUNT(b.id) as total_trips,
            SUM(b.fare_amount) as total_earnings,
            AVG(b.distance) as avg_distance
        FROM bookings b
        LEFT JOIN users u ON b.driver_id = u.id
        WHERE b.status = 'COMPLETED' 
        AND b.driver_id IS NOT NULL
        AND b.toda_id IN ($toda_ids_str)
        GROUP BY b.driver_id
        ORDER BY total_trips DESC
        LIMIT 10
    ");
    
    $total_revenue = $conn->query("
        SELECT COALESCE(SUM(fare_amount), 0) as total 
        FROM bookings b 
        WHERE status = 'COMPLETED' $toda_filter
    ")->fetch_assoc()['total'];
    
    // Get logo path
    $logo_path = '../assets/images/logo2.png';
    $logo_base64 = '';
    if (file_exists($logo_path)) {
        $logo_data = base64_encode(file_get_contents($logo_path));
        $logo_base64 = 'data:image/png;base64,' . $logo_data;
    }
    
    $toda_display = !empty($toda_names) ? implode(', ', $toda_names) : 'No TODA assigned';
    $driver_count = count($driver_ids);
    
    // Create PDF content
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>GoTrike Report - ' . $current_admin_name . ' - ' . date('Y-m-d') . '</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                padding: 20px;
                color: #333;
                font-size: 10px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid #333;
            }
            .logo {
                max-width: 120px;
                margin-bottom: 10px;
            }
            .company-name {
                font-size: 28px;
                font-weight: bold;
                color: #2c3e50;
                margin: 10px 0 5px;
            }
            .header h1 {
                margin: 5px 0 0;
                color: #7f8c8d;
                font-size: 18px;
                font-weight: normal;
            }
            .header p {
                margin: 5px 0 0;
                color: #95a5a6;
                font-size: 10px;
            }
            .admin-info {
                background: #e8f4f8;
                padding: 10px;
                text-align: center;
                margin-bottom: 20px;
                border-radius: 5px;
                font-size: 9px;
                color: #2c3e50;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
                margin-bottom: 20px;
            }
            .stat-box {
                background: #f8f9fa;
                padding: 10px;
                text-align: center;
                border-radius: 8px;
                border: 1px solid #dee2e6;
            }
            .stat-box h4 {
                margin: 0 0 5px;
                font-size: 10px;
                color: #6c757d;
                text-transform: uppercase;
            }
            .stat-number {
                font-size: 22px;
                font-weight: bold;
                margin: 0;
                color: #2c3e50;
            }
            .section {
                margin-bottom: 20px;
                page-break-inside: avoid;
            }
            .section h3 {
                color: #34495e;
                border-left: 4px solid #3498db;
                padding-left: 10px;
                margin-bottom: 10px;
                margin-top: 20px;
                font-size: 14px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
                font-size: 8px;
            }
            th, td {
                border: 1px solid #dee2e6;
                padding: 6px;
                text-align: left;
                vertical-align: top;
            }
            th {
                background-color: #f8f9fa;
                font-weight: bold;
                font-size: 9px;
            }
            .footer {
                text-align: center;
                margin-top: 30px;
                padding-top: 15px;
                border-top: 1px solid #dee2e6;
                font-size: 8px;
                color: #6c757d;
            }
            .revenue-box {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 12px;
                border-radius: 8px;
                text-align: center;
                margin-bottom: 15px;
                color: white;
            }
            .revenue-box .amount {
                font-size: 24px;
                font-weight: bold;
            }
        </style>
    </head>
    <body>';
    
    // Header
    if ($logo_base64) {
        $html .= '<div class="header">
            <img src="' . $logo_base64 . '" class="logo" alt="GoTrike Logo">
            <div class="company-name">GoTrike</div>
            <h1>System Reports</h1>
            <p>Generated on: ' . date('F d, Y h:i A') . '</p>
        </div>';
    } else {
        $html .= '<div class="header">
            <div class="company-name">GoTrike</div>
            <h1>System Reports</h1>
            <p>Generated on: ' . date('F d, Y h:i A') . '</p>
        </div>';
    }
    
    $html .= '<div class="admin-info">
        <strong>Admin:</strong> ' . htmlspecialchars($current_admin_name) . ' | 
        <strong>TODA:</strong> ' . htmlspecialchars($toda_display) . ' |
        <strong>Drivers:</strong> ' . $driver_count . ' assigned |
        <strong>Report Scope:</strong> Your TODA only
    </div>';
    
    // Stats Grid
    $html .= '
        <div class="stats-grid">
            <div class="stat-box">
                <h4>Total Bookings</h4>
                <p class="stat-number">' . $total_bookings . '</p>
            </div>
            <div class="stat-box">
                <h4>Completed</h4>
                <p class="stat-number">' . $completed . '</p>
            </div>
            <div class="stat-box">
                <h4>Cancelled</h4>
                <p class="stat-number">' . $cancelled . '</p>
            </div>
        </div>
        
        <div class="revenue-box">
            <div class="amount">₱ ' . number_format($total_revenue, 2) . '</div>
            <div>Total Revenue from Completed Trips</div>
        </div>';
    
    // Monthly Performance
    $html .= '<div class="section">
        <h3>Monthly Performance (Last 12 Months)</h3>';
    
    if ($monthly_stats->num_rows > 0) {
        $html .= '<table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Total</th>
                    <th>Completed</th>
                    <th>Cancelled</th>
                    <th>Revenue (₱)</th>
                    <th>Completion Rate</th>
                </tr>
            </thead>
            <tbody>';
        while($row = $monthly_stats->fetch_assoc()) {
            $completion_rate = $row['total'] > 0 ? round(($row['completed_count'] / $row['total']) * 100, 1) : 0;
            $html .= '<tr>
                <td>' . date('F Y', strtotime($row['month'] . '-01')) . '</td>
                <td>' . $row['total'] . '</td>
                <td>' . $row['completed_count'] . '</td>
                <td>' . $row['cancelled_count'] . '</td>
                <td>₱ ' . number_format($row['total_revenue'] ?? 0, 2) . '</td>
                <td>' . $completion_rate . '%</td>
            </tr>';
        }
        $html .= '</tbody>
        </table>';
    } else {
        $html .= '<p>No monthly data available for your TODA.</p>';
    }
    
    $html .= '</div>';
    
    // Driver Performance
    if ($driver_stats && $driver_stats->num_rows > 0) {
        $html .= '<div class="section">
            <h3>Your Drivers Performance</h3>
            <table>
                <thead>
                    <tr>
                        <th>Driver Name</th>
                        <th>Total Trips</th>
                        <th>Total Earnings (₱)</th>
                        <th>Avg Distance (km)</th>
                    </tr>
                </thead>
                <tbody>';
        while($row = $driver_stats->fetch_assoc()) {
            $html .= '<tr>
                <td>' . htmlspecialchars($row['driver_name']) . '</td>
                <td>' . $row['total_trips'] . '</td>
                <td>₱ ' . number_format($row['total_earnings'] ?? 0, 2) . '</td>
                <td>' . number_format($row['avg_distance'] ?? 0, 2) . ' km\n                </tr>';
        }
        $html .= '</tbody>
        </table>
        </div>';
    } else {
        $html .= '<div class="section">
            <h3>Your Drivers Performance</h3>
            <p>No trip data available for your drivers yet.</p>
        </div>';
    }
    
    // All Completed Bookings
    $html .= '<div class="section">
        <h3>All Completed Bookings (Your TODA)</h3>';
    
    if ($all_completed && $all_completed->num_rows > 0) {
        $html .= '<table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Passenger</th>
                    <th>Pickup</th>
                    <th>Dropoff</th>
                    <th>Driver</th>
                    <th>Fare (₱)</th>
                    <th>Pax</th>
                    <th>Units</th>
                    <th>Distance</th>
                    <th>Completed Date</th>
                </tr>
            </thead>
            <tbody>';
        while($row = $all_completed->fetch_assoc()) {
            $html .= '<tr>
                <td>' . $row['booking_code'] . '</td>
                <td>' . htmlspecialchars($row['passenger']) . '</td>
                <td>' . htmlspecialchars(substr($row['pickup_landmark'] ?? 'N/A', 0, 30)) . '</td>
                <td>' . htmlspecialchars(substr($row['dropoff_landmark'] ?? 'N/A', 0, 30)) . '</td>
                <td>' . htmlspecialchars($row['driver_name']) . '</td>
                <td>₱ ' . number_format($row['fare_amount'] ?? 0, 2) . '</td>
                <td>' . ($row['total_pax'] ?? 'N/A') . '</td>
                <td>' . ($row['trike_units'] ?? 'N/A') . '</td>
                <td>' . ($row['distance'] ? number_format($row['distance'], 2) . ' km' : 'N/A') . '</td>
                <td>' . date('M d, Y', strtotime($row['dropoff_time'])) . '</td>
            </tr>';
        }
        $html .= '</tbody>
        </table>';
    } else {
        $html .= '<p>No completed bookings found for your TODA.</p>';
    }
    
    $html .= '
        </div>
        
        <div class="footer">
            <p>GoTrike - Your Trusted Tricycle Transport Service</p>
            <p>This report is for ' . htmlspecialchars($current_admin_name) . ' (' . htmlspecialchars($toda_display) . ')</p>
            <p>© ' . date('Y') . ' GoTrike. All rights reserved.</p>
        </div>
    </body>
    </html>';
    
    // Generate PDF
    $dompdf = new Dompdf\Dompdf();
    $dompdf->set_option('isRemoteEnabled', true);
    $dompdf->set_option('defaultFont', 'Helvetica');
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream("GoTrike_Report_" . $current_admin_name . "_" . date('Y-m-d') . ".pdf", array("Attachment" => true));
    exit();
}

// ============================================
// REGULAR PAGE DISPLAY - FILTERED BY TODA
// ============================================

// Main Statistics - FILTERED
$total_bookings_result = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM bookings b 
    WHERE 1=1 $toda_filter
");
$total_bookings = $total_bookings_result ? $total_bookings_result->fetch_assoc()['cnt'] : 0;

$completed_result = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM bookings b 
    WHERE status = 'COMPLETED' $toda_filter
");
$completed = $completed_result ? $completed_result->fetch_assoc()['cnt'] : 0;

$cancelled_result = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM bookings b 
    WHERE status = 'CANCELLED' $toda_filter
");
$cancelled = $cancelled_result ? $cancelled_result->fetch_assoc()['cnt'] : 0;

// This Month Statistics - FILTERED
$this_month_bookings_result = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM bookings b 
    WHERE MONTH(created_at) = MONTH(CURDATE()) 
    AND YEAR(created_at) = YEAR(CURDATE())
    $toda_filter
");
$this_month_bookings = $this_month_bookings_result ? $this_month_bookings_result->fetch_assoc()['cnt'] : 0;

$this_month_completed_result = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM bookings b 
    WHERE status = 'COMPLETED' 
    AND MONTH(updated_at) = MONTH(CURDATE()) 
    AND YEAR(updated_at) = YEAR(CURDATE())
    $toda_filter
");
$this_month_completed = $this_month_completed_result ? $this_month_completed_result->fetch_assoc()['cnt'] : 0;

$this_month_revenue_result = $conn->query("
    SELECT COALESCE(SUM(fare_amount), 0) as total 
    FROM bookings b 
    WHERE status = 'COMPLETED' 
    AND MONTH(updated_at) = MONTH(CURDATE()) 
    AND YEAR(updated_at) = YEAR(CURDATE())
    $toda_filter
");
$this_month_revenue = $this_month_revenue_result ? $this_month_revenue_result->fetch_assoc()['total'] : 0;

// Total Revenue - FILTERED
$total_revenue_result = $conn->query("
    SELECT COALESCE(SUM(fare_amount), 0) as total 
    FROM bookings b 
    WHERE status = 'COMPLETED' $toda_filter
");
$total_revenue = $total_revenue_result ? $total_revenue_result->fetch_assoc()['total'] : 0;

// Completion Rate
$completion_rate = $total_bookings > 0 ? round(($completed / $total_bookings) * 100) : 0;

// Recent completed bookings (last 15) - FILTERED
$recent = $conn->query("
    SELECT 
        b.booking_code, 
        b.status, 
        b.updated_at as dropoff_time,
        b.created_at as pickup_time,
        u.name AS passenger,
        b.pickup_landmark,
        b.dropoff_landmark,
        COALESCE(b.driver_name, d.name, 'Unassigned') as driver_name,
        b.fare_amount,
        b.total_pax,
        b.trike_units,
        b.distance,
        b.notes
    FROM bookings b 
    JOIN users u ON b.passenger_id = u.id 
    LEFT JOIN users d ON b.driver_id = d.id
    WHERE b.status = 'COMPLETED' $toda_filter
    ORDER BY b.updated_at DESC 
    LIMIT 15
");

// Monthly statistics for chart (last 6 months) - FILTERED
$monthly_stats = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'COMPLETED' THEN fare_amount ELSE 0 END) as revenue
    FROM bookings b
    WHERE 1=1 $toda_filter
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");

// Driver performance (only this admin's drivers) - FILTERED
$driver_performance = $conn->query("
    SELECT 
        COALESCE(b.driver_name, u.name) as driver_name,
        COUNT(b.id) as total_trips,
        SUM(b.fare_amount) as total_earnings,
        ROUND(AVG(b.distance), 2) as avg_distance
    FROM bookings b
    LEFT JOIN users u ON b.driver_id = u.id
    WHERE b.status = 'COMPLETED' 
    AND b.driver_id IS NOT NULL
    AND b.toda_id IN ($toda_ids_str)
    GROUP BY b.driver_id
    ORDER BY total_trips DESC
    LIMIT 10
");

// Get list of drivers for display
$my_drivers_list = [];
if (!empty($driver_ids)) {
    $driver_ids_str = implode(',', $driver_ids);
    $drivers_query = $conn->query("
        SELECT id, name FROM users 
        WHERE id IN ($driver_ids_str) AND role = 'driver'
    ");
    if ($drivers_query) {
        while($d = $drivers_query->fetch_assoc()) {
            $my_drivers_list[] = $d['name'];
        }
    }
}
$drivers_display = !empty($my_drivers_list) ? implode(', ', array_slice($my_drivers_list, 0, 5)) : 'No drivers assigned';
if (count($my_drivers_list) > 5) $drivers_display .= ' + ' . (count($my_drivers_list) - 5) . ' more';

?>
<?php include '../includes/header.php'; ?>

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

    .reports-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    .main-card {
        background: white;
        border-radius: 28px;
        overflow: hidden;
        box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1);
        animation: slideUp 0.5s ease;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

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
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .card-header p {
        margin: 0.5rem 0 0;
        opacity: 0.9;
        position: relative;
        z-index: 1;
    }

    .admin-badge {
        background: rgba(255,255,255,0.2);
        padding: 0.5rem 1rem;
        border-radius: 50px;
        display: inline-block;
        margin-top: 0.5rem;
        font-size: 0.8rem;
        position: relative;
        z-index: 1;
    }

    .header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem 2rem;
        background: white;
        border-bottom: 1px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .header-actions h2 {
        margin: 0;
        font-size: 1.3rem;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-pdf {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 50px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: bold;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-pdf:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
    }

    .stats-grid-3x3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        padding: 2rem;
        background: #f8fafc;
        margin: 0;
    }

    .stat-card {
        background: white;
        text-align: center;
        padding: 1.5rem;
        border-radius: 20px;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
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
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }

    .stat-card.blue::before { background: #3b82f6; }
    .stat-card.green::before { background: #10b981; }
    .stat-card.red::before { background: #ef4444; }
    .stat-card.purple::before { background: #8b5cf6; }
    .stat-card.orange::before { background: #f97316; }
    .stat-card.pink::before { background: #ec489a; }

    .stat-card h4 {
        margin: 0 0 0.5rem;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #6b7280;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .stat-card .stat-number {
        font-size: 2.2rem;
        margin: 0;
        font-weight: bold;
        line-height: 1;
    }

    .stat-card.blue .stat-number { color: #3b82f6; }
    .stat-card.green .stat-number { color: #10b981; }
    .stat-card.red .stat-number { color: #ef4444; }
    .stat-card.purple .stat-number { color: #8b5cf6; }
    .stat-card.orange .stat-number { color: #f97316; }
    .stat-card.pink .stat-number { color: #ec489a; }

    .stat-card .stat-sub {
        font-size: 0.7rem;
        color: #9ca3af;
        margin-top: 0.5rem;
    }

    .revenue-grid-3col {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        padding: 0 2rem 2rem 2rem;
    }

    .revenue-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 20px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .revenue-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(102,126,234,0.3);
    }

    .revenue-card h4 {
        margin: 0 0 0.5rem;
        font-size: 0.85rem;
        opacity: 0.9;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .revenue-card .amount {
        font-size: 1.8rem;
        font-weight: bold;
        margin: 0;
    }

    .revenue-card .stat-sub {
        font-size: 0.7rem;
        margin-top: 0.5rem;
        opacity: 0.8;
    }

    .two-columns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        padding: 0 2rem 2rem 2rem;
    }

    .chart-card, .performance-card {
        background: #f8fafc;
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid #e2e8f0;
    }

    .chart-card h3, .performance-card h3 {
        margin: 0 0 1rem;
        font-size: 1rem;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .monthly-stats {
        width: 100%;
    }

    .month-row {
        display: flex;
        align-items: center;
        margin-bottom: 0.75rem;
        gap: 0.5rem;
    }

    .month-name {
        width: 80px;
        font-size: 0.7rem;
        font-weight: bold;
        color: #4b5563;
    }

    .bar-container {
        flex: 1;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        height: 28px;
    }

    .bar-completed {
        height: 100%;
        background: linear-gradient(90deg, #10b981, #059669);
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 8px;
        color: white;
        font-size: 0.65rem;
        font-weight: bold;
    }

    .driver-table {
        width: 100%;
        font-size: 0.75rem;
    }

    .driver-table th, .driver-table td {
        padding: 0.6rem;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }

    .driver-table th {
        font-weight: bold;
        color: #6b7280;
        background: #f1f5f9;
    }

    .table-section {
        padding: 0 2rem 2rem 2rem;
    }

    .section-title {
        font-size: 1.2rem;
        font-weight: bold;
        margin-bottom: 1.5rem;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .recent-bookings-table {
        overflow-x: auto;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
    }

    .recent-bookings-table table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
    }

    .recent-bookings-table th {
        background: #f8fafc;
        padding: 0.875rem;
        text-align: left;
        font-weight: bold;
        color: #4b5563;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }

    .recent-bookings-table td {
        padding: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
    }

    .recent-bookings-table tr:hover {
        background: #f8fafc;
    }

    .driver-badge {
        background: #e3f2fd;
        padding: 0.25rem 0.5rem;
        border-radius: 20px;
        font-size: 0.7rem;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .booking-code {
        font-weight: bold;
        color: #667eea;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #6b7280;
    }

    .toda-info {
        background: #f0fdf4;
        border-left: 4px solid #10b981;
        padding: 0.75rem 1rem;
        margin: 0 2rem 1rem 2rem;
        border-radius: 12px;
        font-size: 0.75rem;
        color: #166534;
    }

    @media (max-width: 1200px) {
        .stats-grid-3x3 {
            grid-template-columns: repeat(2, 1fr);
        }
        .revenue-grid-3col {
            grid-template-columns: 1fr;
        }
        .two-columns {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .stats-grid-3x3 {
            grid-template-columns: 1fr;
        }
        .stats-grid-3x3 {
            padding: 1rem;
        }
        .revenue-grid-3col {
            padding: 0 1rem 1rem 1rem;
        }
        .two-columns {
            padding: 0 1rem 1rem 1rem;
        }
        .table-section {
            padding: 0 1rem 1rem 1rem;
        }
        .header-actions {
            padding: 1rem;
            flex-direction: column;
            text-align: center;
        }
        .toda-info {
            margin: 0 1rem 1rem 1rem;
        }
    }
</style>

<div class="reports-container">
    <div class="main-card">
        <div class="card-header">
            <h2>
                <i class="fas fa-chart-line"></i>
                System Reports
            </h2>
            <p>Comprehensive analytics and performance metrics</p>
            <div class="admin-badge">
                <i class="fas fa-user-shield"></i> <?= htmlspecialchars($current_admin_name) ?> 
                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars(implode(', ', $toda_names)) ?>
            </div>
        </div>

        <div class="header-actions">
            <h2>
                <i class="fas fa-chart-bar"></i>
                Overview Dashboard
            </h2>
            <button onclick="downloadPDF()" class="btn-pdf">
                <i class="fas fa-file-pdf"></i> Download PDF Report
            </button>
        </div>

        <!-- TODA Info Box -->
        <div class="toda-info">
            <i class="fas fa-info-circle"></i> 
            <strong>Your TODA:</strong> <?= htmlspecialchars(implode(', ', $toda_names)) ?> | 
            <strong>Assigned Drivers:</strong> <?= htmlspecialchars($drivers_display) ?> |
            <strong>Total Drivers:</strong> <?= count($my_drivers_list) ?>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid-3x3">
            <div class="stat-card blue" onclick="window.location.href='/my-bookings.php'">
                <h4><i class="fas fa-calendar-alt"></i> Total Bookings</h4>
                <div class="stat-number"><?= $total_bookings ?></div>
            </div>
            <div class="stat-card green" onclick="window.location.href='/my-bookings.php?status=COMPLETED'">
                <h4><i class="fas fa-check-circle"></i> Completed</h4>
                <div class="stat-number"><?= $completed ?></div>
            </div>
            <div class="stat-card red" onclick="window.location.href='/my-bookings.php?status=CANCELLED'">
                <h4><i class="fas fa-times-circle"></i> Cancelled</h4>
                <div class="stat-number"><?= $cancelled ?></div>
            </div>
            <div class="stat-card purple">
                <h4><i class="fas fa-calendar-month"></i> This Month Bookings</h4>
                <div class="stat-number"><?= $this_month_bookings ?></div>
                <div class="stat-sub">Total bookings this month</div>
            </div>
            <div class="stat-card orange">
                <h4><i class="fas fa-check-double"></i> Monthly Completed</h4>
                <div class="stat-number"><?= $this_month_completed ?></div>
                <div class="stat-sub">Completed trips this month</div>
            </div>
            <div class="stat-card pink">
                <h4><i class="fas fa-flag-checkered"></i> Completion Rate</h4>
                <div class="stat-number"><?= $completion_rate ?>%</div>
                <div class="stat-sub">Overall completion rate</div>
            </div>
        </div>

        <!-- Revenue Cards -->
        <div class="revenue-grid-3col">
            <div class="revenue-card">
                <h4><i class="fas fa-chart-line"></i> Total Revenue</h4>
                <div class="amount">₱ <?= number_format($total_revenue, 2) ?></div>
                <div class="stat-sub">All time from completed trips</div>
            </div>
            <div class="revenue-card">
                <h4><i class="fas fa-calendar-month"></i> This Month Revenue</h4>
                <div class="amount">₱ <?= number_format($this_month_revenue, 2) ?></div>
                <div class="stat-sub">From <?= $this_month_completed ?> completed trips</div>
            </div>
            <div class="revenue-card" style="background: linear-gradient(135deg, #868f96 0%, #596164 100%);">
                <h4><i class="fas fa-info-circle"></i> System Status</h4>
                <div class="amount">Active</div>
                <div class="stat-sub">System operational</div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="two-columns">
            <div class="chart-card">
                <h3><i class="fas fa-chart-simple"></i> Monthly Performance (Last 6 Months)</h3>
                <div class="monthly-stats">
                    <?php 
                    if ($monthly_stats && $monthly_stats->num_rows > 0):
                        $monthly_data = [];
                        while($row = $monthly_stats->fetch_assoc()) {
                            $monthly_data[] = $row;
                        }
                        $monthly_data = array_reverse($monthly_data);
                        foreach($monthly_data as $row): 
                            $total = $row['total'];
                            $completed_pct = $total > 0 ? round(($row['completed'] / $total) * 100) : 0;
                    ?>
                    <div class="month-row">
                        <div class="month-name"><?= date('M Y', strtotime($row['month'] . '-01')) ?></div>
                        <div class="bar-container">
                            <div class="bar-completed" style="width: <?= $completed_pct ?>%;">
                                <?= $completed_pct > 15 ? $row['completed'] . ' completed' : '' ?>
                            </div>
                        </div>
                        <div style="font-size: 0.65rem; min-width: 70px; text-align: right;">₱<?= number_format($row['revenue'], 0) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-state" style="padding: 1rem;">
                        <i class="fas fa-chart-line"></i>
                        <p>No monthly data available for your TODA.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="performance-card">
                <h3><i class="fas fa-trophy"></i> Your Top Performing Drivers</h3>
                <?php if ($driver_performance && $driver_performance->num_rows > 0): ?>
                <table class="driver-table">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Trips</th>
                            <th>Earnings</th>
                            <th>Avg Dist</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($row = $driver_performance->fetch_assoc()): ?>
                        <tr>
                            <td><i class="fas fa-user-circle"></i> <?= htmlspecialchars(substr($row['driver_name'], 0, 20)) ?></td>
                            <td><?= $row['total_trips'] ?></td>
                            <td>₱<?= number_format($row['total_earnings'], 0) ?></td>
                            <td><?= $row['avg_distance'] ?> km</

                             ?>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state" style="padding: 2rem;">
                    <i class="fas fa-user-slash"></i>
                    <p>No driver data available for your TODA yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Completed Bookings Table -->
        <div class="table-section">
            <div class="section-title">
                <i class="fas fa-history"></i>
                Recent Completed Bookings (Your TODA)
            </div>

            <?php if ($recent && $recent->num_rows > 0): ?>
                <div class="recent-bookings-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Passenger</th>
                                <th>Pickup</th>
                                <th>Dropoff</th>
                                <th>Driver</th>
                                <th>Fare</th>
                                <th>Pax</th>
                                <th>Units</th>
                                <th>Distance</th>
                                <th>Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while($row = $recent->fetch_assoc()): ?>
                            <tr>
                                <td class="booking-code"><?= $row['booking_code'] ?></td>
                                <td><?= htmlspecialchars(substr($row['passenger'], 0, 20)) ?></td>
                                <td class="landmark-text"><?= htmlspecialchars(substr($row['pickup_landmark'] ?? 'N/A', 0, 25)) ?></td>
                                <td class="landmark-text"><?= htmlspecialchars(substr($row['dropoff_landmark'] ?? 'N/A', 0, 25)) ?></td>
                                <td>
                                    <span class="driver-badge">
                                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars(substr($row['driver_name'], 0, 20)) ?>
                                    </span>
                                </td>
                                <td><strong>₱<?= number_format($row['fare_amount'] ?? 0, 2) ?></strong></td>
                                <td><?= $row['total_pax'] ?? 'N/A' ?></td>
                                <td><?= $row['trike_units'] ?? 'N/A' ?></td>
                                <td><?= $row['distance'] ? number_format($row['distance'], 2) . ' km' : 'N/A' ?></td>
                                <td><?= date('M d, Y', strtotime($row['dropoff_time'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No completed trips yet for your TODA.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function downloadPDF() {
    window.location.href = window.location.pathname + '?download_pdf=1';
}
</script>

<?php include '../includes/footer.php'; ?>