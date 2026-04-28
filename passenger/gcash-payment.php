<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$booking = $conn->query("SELECT * FROM bookings WHERE id = $booking_id AND passenger_id = {$_SESSION['user_id']}")->fetch_assoc();

if (!$booking) {
    header("Location: dashboard.php");
    exit;
}

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reference_number'])) {
    $reference = trim($_POST['reference_number']);
    $stmt = $conn->prepare("UPDATE bookings SET payment_reference = ?, payment_status = 'paid', payment_date = NOW() WHERE id = ? AND payment_status = 'pending'");
    $stmt->bind_param("si", $reference, $booking_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // Generate QR code after payment
        $qr_content = "BOOKING INFO\nCode: {$booking['booking_code']}\nFrom: {$booking['pickup_landmark']}\nTo: {$booking['dropoff_landmark']}\nPax: {$booking['total_pax']}\nTrikes: {$booking['trike_units']}\nTotal Fare: PHP " . number_format($booking['fare_amount'], 2);
        $qr_path = "../qr-code/{$booking['booking_code']}.png";
        if(!file_exists('../qr-code')) mkdir('../qr-code', 0777, true);
        require_once '../vendor/phpqrcode/qrlib.php';
        QRcode::png($qr_content, $qr_path, QR_ECLEVEL_L, 5);
        
        $_SESSION['payment_success'] = true;
        header("Location: qr.php?id=$booking_id");
        exit;
    } else {
        $error = "Payment verification failed. Please contact support.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GCash Payment - GoTrike</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .payment-container {
            max-width: 550px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .payment-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .payment-header {
            background: linear-gradient(135deg, #00B4D8 0%, #0077B6 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .payment-header h2 {
            margin: 0;
            font-size: 2rem;
        }
        
        .payment-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
        }
        
        .payment-body {
            padding: 2rem;
        }
        
        .booking-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding: 0.25rem 0;
            border-bottom: 1px dashed #dee2e6;
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        
        .info-value {
            color: #212529;
            font-weight: 500;
        }
        
        .qr-section {
            text-align: center;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 16px;
            margin: 1.5rem 0;
        }
        
        .amount {
            font-size: 2.5rem;
            color: #00B4D8;
            font-weight: bold;
            margin: 1rem 0;
        }
        
        .amount small {
            font-size: 1rem;
            color: #6c757d;
        }
        
        .gcash-number {
            background: linear-gradient(135deg, #e6f7ff 0%, #f0f9ff 100%);
            padding: 1.2rem;
            border-radius: 12px;
            text-align: center;
            margin: 1.5rem 0;
            border: 2px solid #00B4D8;
        }
        
        .gcash-number h4 {
            margin: 0 0 0.5rem 0;
            color: #0077B6;
            font-size: 1.1rem;
        }
        
        .gcash-number .number {
            font-size: 2rem;
            font-weight: bold;
            color: #00B4D8;
            letter-spacing: 2px;
            font-family: monospace;
        }
        
        .gcash-qr {
            width: 220px;
            height: 220px;
            margin: 1.5rem auto;
            background: white;
            padding: 1rem;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .gcash-qr img {
            width: 100%;
            height: auto;
            object-fit: contain;
        }
        
        .reference-form {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #e0e0e0;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #495057;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #00B4D8;
            box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.1);
        }
        
        .btn-verify {
            width: 100%;
            background: linear-gradient(135deg, #00B4D8 0%, #0077B6 100%);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 180, 216, 0.4);
        }
        
        .btn-verify:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
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
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .instruction-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-size: 0.9rem;
        }
        
        .instruction-box ol {
            margin: 0.5rem 0 0 1rem;
            padding: 0;
        }
        
        .instruction-box li {
            margin: 0.5rem 0;
        }
        
        .copy-btn {
            background: #00B4D8;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        
        .copy-btn:hover {
            background: #0077B6;
        }
        
        @media (max-width: 768px) {
            .payment-container {
                margin: 1rem auto;
            }
            
            .payment-body {
                padding: 1.5rem;
            }
            
            .gcash-number .number {
                font-size: 1.5rem;
            }
            
            .amount {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="payment-container">
    <div class="payment-card">
        <div class="payment-header">
            <h2>💳 GCash Payment</h2>
            <p>Complete your booking with GCash</p>
        </div>
        
        <div class="payment-body">
            <div class="booking-info">
                <div class="info-row">
                    <span class="info-label">🎫 Booking Code:</span>
                    <span class="info-value"><?= htmlspecialchars($booking['booking_code']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">📍 From:</span>
                    <span class="info-value"><?= htmlspecialchars($booking['pickup_landmark']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">📍 To:</span>
                    <span class="info-value"><?= htmlspecialchars($booking['dropoff_landmark']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">👥 Passengers:</span>
                    <span class="info-value"><?= $booking['total_pax'] ?> pax (<?= $booking['trike_units'] ?> tricycle(s))</span>
                </div>
                <div class="info-row">
                    <span class="info-label">📅 Pickup Time:</span>
                    <span class="info-value"><?= date('F d, Y h:i A', strtotime($booking['pickup_time'])) ?></span>
                </div>
            </div>
            
            <div class="qr-section">
                <h3>💰 Amount to Pay:</h3>
                <div class="amount">₱<?= number_format($booking['fare_amount'], 2) ?></div>
                
                <div class="gcash-number">
                    <h4>📱 Send Payment to:</h4>
                    <div class="number">0993 591 5712</div>
                    <small>Account Name: GoTrike Transport Services</small>
                    <br>
                    <button class="copy-btn" onclick="copyGCashNumber()">📋 Copy Number</button>
                </div>
                
                <div class="gcash-qr">
                    <img src="../assets/images/gcash-qr.jpg" alt="GCash QR Code" onerror="this.onerror=null; this.src='../assets/images/gcash-placeholder.png'; this.alt='QR Code not found';">
                </div>
                <p><small>📱 Scan this QR code using GCash app to pay instantly</small></p>
            </div>
            
            <div class="instruction-box">
                <strong>📝 How to Pay via GCash:</strong>
                <ol>
                    <li>Open GCash app and tap "Pay QR"</li>
                    <li>Scan the QR code above or send to <strong>0993 591 5712</strong></li>
                    <li>Enter the exact amount: <strong>₱<?= number_format($booking['fare_amount'], 2) ?></strong></li>
                    <li>Add your <strong>Booking Code: <?= $booking['booking_code'] ?></strong> as reference</li>
                    <li>Complete the payment and copy the Reference Number</li>
                    <li>Enter the Reference Number below to verify your payment</li>
                </ol>
            </div>
            
            <div class="reference-form">
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">⚠️ <?= $error ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['payment_success'])): ?>
                    <div class="alert alert-success">✅ Payment successful! Redirecting...</div>
                <?php endif; ?>
                
                <form method="POST" id="paymentForm">
                    <div class="form-group">
                        <label>🔢 GCash Reference Number</label>
                        <input type="text" name="reference_number" id="reference_number" class="form-control" placeholder="Enter the reference number from GCash" required autocomplete="off">
                        <small>Example: 123456789012 or find it in your GCash transaction history</small>
                    </div>
                    <button type="submit" class="btn-verify">✅ Verify Payment</button>
                </form>
                
                <div style="margin-top: 1.5rem; text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <small>⚠️ <strong>Important:</strong> After submitting your reference number, please wait for admin confirmation. Your QR code will be generated once payment is verified.</small>
                </div>
                
                <div style="margin-top: 1rem; text-align: center;">
                    <a href="dashboard.php" style="color: #00B4D8; text-decoration: none;">← Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyGCashNumber() {
    const gcashNumber = "09935915712";
    navigator.clipboard.writeText(gcashNumber).then(function() {
        const btn = document.querySelector('.copy-btn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '✅ Copied!';
        setTimeout(function() {
            btn.innerHTML = originalText;
        }, 2000);
    }).catch(function(err) {
        alert('Failed to copy number. Please copy manually: ' + gcashNumber);
    });
}

// Add validation for reference number
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const reference = document.getElementById('reference_number').value.trim();
    if (reference.length < 10) {
        e.preventDefault();
        alert('Please enter a valid GCash reference number (at least 10 characters)');
    }
});

// Auto-redirect if payment was successful
<?php if (isset($_SESSION['payment_success'])): ?>
    setTimeout(function() {
        window.location.href = 'qr.php?id=<?= $booking_id ?>';
    }, 3000);
<?php unset($_SESSION['payment_success']); endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>