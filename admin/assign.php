<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Admin access only
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: /dashboard.php");
    exit;
}

$bid = (int)$_GET['id'];

// --- HANDLE ASSIGNMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver_id = (int)$_POST['driver_id'];

    if ($driver_id > 0) {
        // Kunin ang name ng driver base sa napiling ID
        $getName = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'driver'");
        $getName->bind_param("i", $driver_id);
        $getName->execute();
        $d_data = $getName->get_result()->fetch_assoc();
        
        if ($d_data) {
            $driver_name = $d_data['name'];

            // Update ang booking: I-link ang driver_id at driver_name
            $stmt = $conn->prepare("UPDATE bookings SET driver_id = ?, driver_name = ?, status = 'ASSIGNED' WHERE id = ? AND status = 'PENDING'");
            $stmt->bind_param("isi", $driver_id, $driver_name, $bid);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                header("Location: /dashboard.php?msg=Driver $driver_name assigned successfully");
                exit;
            } else {
                $error = "Failed to assign or booking is no longer pending.";
            }
        } else {
            $error = "Driver not found.";
        }
    } else {
        $error = "Please select a driver.";
    }
}

// --- FETCH ONLINE DRIVERS ---
$drivers_query = $conn->query("SELECT id, name FROM users WHERE role = 'driver' AND status = 'online' ORDER BY name ASC");

// --- FETCH BOOKING DETAILS ---
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->bind_param("i", $bid);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking || $booking['status'] !== 'PENDING') {
    header("Location: /dashboard.php?error=Cannot assign this booking");
    exit;
}

// Get fare from fare_amount field
$fare_amount = $booking['fare_amount'] ?? $booking['fare'] ?? 0;
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

    .assign-container {
        max-width: 700px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    /* Main Card */
    .main-card {
        background: white;
        border-radius: 28px;
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

    .booking-code {
        background: rgba(255,255,255,0.2);
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.8rem;
        margin-top: 0.5rem;
    }

    /* Content */
    .card-content {
        padding: 2rem;
    }

    /* Alert */
    .alert {
        padding: 1rem;
        border-radius: 14px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert-error {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        border-left: 4px solid #ef4444;
    }

    /* Form */
    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        font-weight: bold;
        color: #374151;
        margin-bottom: 0.5rem;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group label i {
        margin-right: 0.5rem;
        color: #667eea;
    }

    select {
        width: 100%;
        padding: 0.8rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 14px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        background: white;
        cursor: pointer;
    }

    select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }

    .info-note {
        font-size: 0.7rem;
        color: #10b981;
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    /* Button Group */
    .button-group {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .btn-primary {
        flex: 2;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 0.9rem;
        border-radius: 14px;
        font-size: 0.9rem;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102,126,234,0.4);
    }

    .btn-secondary {
        flex: 1;
        background: #f1f5f9;
        color: #475569;
        border: none;
        padding: 0.9rem;
        border-radius: 14px;
        font-size: 0.9rem;
        font-weight: bold;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }

    /* Booking Details */
    .details-section {
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e2e8f0;
    }

    .details-section h4 {
        font-size: 1rem;
        font-weight: bold;
        color: #374151;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .details-section h4 i {
        color: #667eea;
    }

    .details-card {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        padding: 1.2rem;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 0.6rem 0;
        border-bottom: 1px dashed #e2e8f0;
    }

    .detail-item:last-child {
        border-bottom: none;
    }

    .detail-icon {
        width: 32px;
        height: 32px;
        background: white;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #667eea;
        font-size: 0.9rem;
    }

    .detail-content {
        flex: 1;
    }

    .detail-label {
        font-size: 0.7rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-value {
        font-weight: bold;
        color: #1f2937;
        font-size: 0.9rem;
    }

    .fare-amount {
        color: #f59e0b;
        font-size: 1.1rem;
    }

    @media (max-width: 768px) {
        .assign-container {
            padding: 1rem;
        }
        
        .card-content {
            padding: 1.5rem;
        }
        
        .button-group {
            flex-direction: column;
        }
        
        .detail-item {
            flex-direction: column;
            text-align: center;
            gap: 0.3rem;
        }
        
        .detail-icon {
            margin: 0 auto;
        }
    }
</style>

<div class="assign-container">
    <div class="main-card">
        <div class="card-header">
            <h2>
                <i class="fas fa-user-plus"></i>
                Assign Driver
            </h2>
            <p>Assign an online driver to this booking</p>
            <div class="booking-code">
                <i class="fas fa-qrcode"></i> Booking Code: <strong><?= htmlspecialchars($booking['booking_code']) ?></strong>
            </div>
        </div>

        <div class="card-content">
            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-users"></i> Select Online Driver</label>
                    <select name="driver_id" required>
                        <option value="">-- Choose a driver --</option>
                        <?php if ($drivers_query->num_rows > 0): ?>
                            <?php while($row = $drivers_query->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>">
                                    🚗 <?= htmlspecialchars($row['name']) ?> (ID: #<?= $row['id'] ?>)
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="" disabled>⚠️ No drivers are currently online</option>
                        <?php endif; ?>
                    </select>
                    <div class="info-note">
                        <i class="fas fa-dot-circle"></i> Only drivers with <strong>status = 'online'</strong> are shown
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check-circle"></i> Confirm Assignment
                    </button>
                    <a href="/dashboard.php" class="btn-secondary">
                        <i class="fas fa-times-circle"></i> Cancel
                    </a>
                </div>
            </form>

            <div class="details-section">
                <h4>
                    <i class="fas fa-info-circle"></i>
                    Booking Details
                </h4>
                <div class="details-card">
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-location-dot"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Pickup Location</div>
                            <div class="detail-value"><?= htmlspecialchars($booking['pickup_landmark']) ?></div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-location-arrow"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Drop-off Location</div>
                            <div class="detail-value"><?= htmlspecialchars($booking['dropoff_landmark']) ?></div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Passengers</div>
                            <div class="detail-value"><?= $booking['total_pax'] ?? 1 ?> person(s)</div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-motorcycle"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Tricycle Units</div>
                            <div class="detail-value"><?= $booking['trike_units'] ?? 1 ?> unit(s)</div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Fare Amount</div>
                            <div class="detail-value fare-amount">
                                ₱<?= number_format($fare_amount, 2) ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($booking['notes'])): ?>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-pen"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Special Notes</div>
                            <div class="detail-value" style="font-style: italic;"><?= htmlspecialchars($booking['notes']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>