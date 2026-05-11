<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../PHPMailer/src/Exception.php';

// 1. Siguraduhin na Driver ang naka-login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../login.php");
    exit;
}

$driver_id = $_SESSION['user_id'];
$message = '';
$error = '';
$upload_dir = "../uploads/drivers_profile/";

// Siguraduhin na exist ang folder
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Function to refresh driver data
function refreshDriverData($conn, $driver_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// 2. I-fetch ang data ng Driver
$user = refreshDriverData($conn, $driver_id);

if (!$user) {
    die("Driver record not found.");
}

$step = $_GET['step'] ?? $_POST['step'] ?? 'form';
$otp_error = '';
$otp_success = '';

// Function to send email OTP
function sendEmailOTP($recipient_email, $otp_code, $recipient_name = '') {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'songiericsajoca0@gmail.com';
        $mail->Password   = 'xlqoyuisayfzoply';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->setFrom('gotrike@gmail.com', 'GoTrike');
        $mail->addAddress($recipient_email, $recipient_name);
        
        $mail->isHTML(true);
        $mail->Subject = 'GoTrike Driver Verification OTP';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px;'>
                <h2 style='color: #1e40af;'>GoTrike Driver Verification</h2>
                <p>Hello Driver <strong>$recipient_name</strong>,</p>
                <p>Your verification OTP is:</p>
                <div style='background: #f1f5f9; padding: 15px; text-align: center; font-size: 28px; letter-spacing: 5px; font-weight: bold; border-radius: 8px;'>
                    $otp_code
                </div>
                <p style='color: #64748b; font-size: 12px; margin-top: 20px;'>Valid for 5 minutes only.</p>
            </div>
        ";
        $mail->AltBody = "GoTrike Driver Verification OTP: $otp_code\nValid for 5 minutes only.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Handle AJAX verification
if (isset($_POST['ajax_verify'])) {
    header('Content-Type: application/json');
    $otp_input = trim($_POST['otp_code'] ?? '');
    $verify_type = $_POST['verify_type'] ?? '';
    
    $response = ['success' => false, 'message' => ''];
    
    if (!isset($_SESSION['driver_temp_verify_otp'])) {
        $response['message'] = "No active OTP found. Please request a new one.";
    } elseif (time() > $_SESSION['driver_temp_verify_expiry']) {
        $response['message'] = "OTP has expired. Please request a new one.";
        unset($_SESSION['driver_temp_verify_otp']);
    } elseif ($otp_input != $_SESSION['driver_temp_verify_otp']) {
        $response['message'] = "Invalid OTP code. Please try again.";
    } else {
        // Update the correct field
        if ($verify_type === 'email') {
            $sql = "UPDATE users SET email_verified = 1 WHERE id = ?";
            $response['message'] = "Email verified successfully!";
        } else {
            $sql = "UPDATE users SET phone_verified = 1 WHERE id = ?";
            $response['message'] = "Phone number verified successfully!";
        }
        
        $up = $conn->prepare($sql);
        $up->bind_param("i", $driver_id);
        
        if ($up->execute()) {
            $response['success'] = true;
            unset($_SESSION['driver_temp_verify_otp'], $_SESSION['driver_temp_verify_type']);
        } else {
            $response['message'] = "Failed to verify. Please try again.";
        }
        $up->close();
    }
    
    echo json_encode($response);
    exit;
}

// 3. Handle Update Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_verify'])) {
    $action = $_POST['action'] ?? '';
    
    // Update Profile Info
    if ($action === 'update_profile') {
        $name    = trim($_POST['name']);
        $contact = trim($_POST['contact']);
        $new_pwd = $_POST['password'];
        $profile_name = $user['profile'];
        
        // Kunin ang bagong field values
        $body_number = trim($_POST['body_number'] ?? '');
        $plate_number = trim($_POST['plate_number'] ?? '');
        $toda_color = trim($_POST['toda_color'] ?? '');
        
        try {
            if (!empty($_POST['camera_image'])) {
                $img = $_POST['camera_image'];
                $img = str_replace('data:image/jpeg;base64,', '', $img);
                $img = str_replace(' ', '+', $img);
                $data = base64_decode($img);
                $profile_name = "driver_" . $driver_id . "_" . time() . ".jpg";
                file_put_contents($upload_dir . $profile_name, $data);
                $_SESSION['profile'] = $profile_name;
            } elseif (isset($_FILES['profile']) && $_FILES['profile']['error'] === 0) {
                $ext = pathinfo($_FILES['profile']['name'], PATHINFO_EXTENSION);
                $profile_name = "driver_" . $driver_id . "_" . time() . "." . $ext;
                move_uploaded_file($_FILES['profile']['tmp_name'], $upload_dir . $profile_name);
                $_SESSION['profile'] = $profile_name;
            }

            if (!empty($new_pwd)) {
                $hashed_pwd = password_hash($new_pwd, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name=?, contact=?, profile=?, password=?, body_number=?, plate_number=?, toda_color=? WHERE id=?");
                $stmt->bind_param("sssssssi", $name, $contact, $profile_name, $hashed_pwd, $body_number, $plate_number, $toda_color, $driver_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, contact=?, profile=?, body_number=?, plate_number=?, toda_color=? WHERE id=?");
                $stmt->bind_param("ssssssi", $name, $contact, $profile_name, $body_number, $plate_number, $toda_color, $driver_id);
            }
            $stmt->execute();

            $_SESSION['name'] = $name;
            $message = "Profile updated successfully!";
            
            // Refresh user data
            $user = refreshDriverData($conn, $driver_id);
        } catch (Exception $e) {
            $error = "Update failed: " . $e->getMessage();
        }
    }
    
    // Send Email Verification OTP
    elseif ($action === 'send_email_otp') {
        $otp_code = rand(100000, 999999);
        $_SESSION['driver_temp_verify_otp'] = $otp_code;
        $_SESSION['driver_temp_verify_expiry'] = time() + 300;
        $_SESSION['driver_temp_verify_type'] = 'email';
        
        if (sendEmailOTP($user['email'], $otp_code, $user['name'])) {
            $step = 'verify_otp';
            $otp_success = "Verification code sent to your email: " . substr($user['email'], 0, 3) . "***@" . explode('@', $user['email'])[1];
        } else {
            $error = "Failed to send verification email.";
        }
    }
    
    // Send Phone Verification OTP
    elseif ($action === 'send_phone_otp') {
        if (empty($user['contact'])) {
            $error = "Please add a phone number first before verifying.";
        } else {
            $otp_code = rand(100000, 999999);
            $_SESSION['driver_temp_verify_otp'] = $otp_code;
            $_SESSION['driver_temp_verify_expiry'] = time() + 300;
            $_SESSION['driver_temp_verify_type'] = 'phone';
            
            // Format phone number for SMS
            $phone = $user['contact'];
            $phone = preg_replace('/[^0-9]/', '', $phone);
            
            if (strlen($phone) == 10 && substr($phone, 0, 1) == '9') {
                $phone = '63' . $phone;
            } elseif (strlen($phone) == 11 && substr($phone, 0, 2) == '09') {
                $phone = '63' . substr($phone, 1);
            }
            $phone_international = '+' . $phone;
            
            if (function_exists('sendSMS') && sendSMS($phone_international, "GoTrike Driver Verification: Your OTP is $otp_code. Valid for 5 minutes.")) {
                $step = 'verify_otp';
                $otp_success = "Verification code sent to your phone number: " . $phone_international;
            } else {
                $error = "Failed to send SMS verification.";
            }
        }
    }
    
    // Resend OTP
    elseif ($action === 'resend_otp') {
        $verify_type = $_SESSION['driver_temp_verify_type'] ?? '';
        $otp_code = rand(100000, 999999);
        $_SESSION['driver_temp_verify_otp'] = $otp_code;
        $_SESSION['driver_temp_verify_expiry'] = time() + 300;
        
        if ($verify_type === 'email') {
            if (sendEmailOTP($user['email'], $otp_code, $user['name'])) {
                $otp_success = "New verification code sent to your email.";
                $step = 'verify_otp';
            } else {
                $otp_error = "Failed to resend verification email.";
            }
        } else {
            $phone = $user['contact'];
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($phone) == 10 && substr($phone, 0, 1) == '9') {
                $phone = '63' . $phone;
            } elseif (strlen($phone) == 11 && substr($phone, 0, 2) == '09') {
                $phone = '63' . substr($phone, 1);
            }
            $phone_international = '+' . $phone;
            
            if (function_exists('sendSMS') && sendSMS($phone_international, "GoTrike Driver Verification: Your OTP is $otp_code. Valid for 5 minutes.")) {
                $otp_success = "New verification code sent to your phone.";
                $step = 'verify_otp';
            } else {
                $otp_error = "Failed to resend SMS verification.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Profile | GoTrike</title>
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

        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --primary-light: #7f9cf5;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        body {
            background: var(--bg);
            margin: 0;
            color: #1e293b;
            min-height: 100vh;
        }

        .profile-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        /* Header Card */
        .header-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 28px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
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

        .profile-header-content {
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .profile-pic-wrapper {
            position: relative;
            display: inline-block;
        }

        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.3);
            background: white;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }

        .profile-pic:hover {
            transform: scale(1.05);
        }

        .profile-info h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: bold;
        }

        .profile-info p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
        }

        .driver-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.7rem;
            margin-top: 0.5rem;
        }

        /* Main Card */
        .main-card {
            background: white;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.1);
        }

        .card-section {
            padding: 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
            color: #667eea;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 10px;
        }

        .verified {
            background: #d1fae5;
            color: #065f46;
        }

        .unverified {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-verify {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.7rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16,185,129,0.3);
        }

        /* Photo Upload */
        .photo-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e2e8f0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .photo-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn-photo {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.7rem 1.2rem;
            border-radius: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-size: 0.85rem;
        }

        .btn-camera {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-camera:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }

        .btn-upload {
            background: #f1f5f9;
            color: #475569;
            border: 2px solid #e2e8f0;
        }

        .btn-upload:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        /* Camera Modal */
        .camera-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.95);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .camera-modal.active {
            display: flex;
        }

        .camera-content {
            background: #000;
            border-radius: 28px;
            padding: 1rem;
            max-width: 90%;
            width: 400px;
            position: relative;
        }

        .camera-content video {
            width: 100%;
            border-radius: 20px;
        }

        .camera-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            justify-content: center;
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .form-control[readonly] {
            background: #f8fafc;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .full-width {
            grid-column: span 2;
        }

        /* Buttons */
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 16px;
            font-size: 0.9rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102,126,234,0.4);
        }

        .btn-back {
            display: inline-block;
            text-align: center;
            width: 100%;
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            padding: 1rem;
            border-radius: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: bold;
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

        /* OTP Section */
        .otp-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 2rem;
            border-radius: 20px;
            text-align: center;
        }

        .otp-input-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .otp-digit {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.6rem;
            font-weight: 700;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            background: white;
            font-family: monospace;
        }

        .otp-digit:focus {
            border-color: #667eea;
            outline: none;
        }

        @media (max-width: 768px) {
            .profile-header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .full-width {
                grid-column: span 1;
            }
            
            .card-section {
                padding: 1.5rem;
            }
            
            .otp-digit {
                width: 45px;
                height: 55px;
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>

<div class="profile-container">
    <!-- Header Card -->
    <div class="header-card">
        <div class="profile-header-content">
            <?php 
                $profile_img = !empty($user['profile']) ? $upload_dir . $user['profile'] : "../assets/default-driver.jpg";
            ?>
            <div class="profile-pic-wrapper">
                <img src="<?= $profile_img ?>" alt="Profile" class="profile-pic" id="preview">
            </div>
            <div class="profile-info">
                <h2>🚗 <?= htmlspecialchars($user['name']) ?></h2>
                <p>Driver • ID: #<?= $user['id'] ?></p>
                <span class="driver-badge">✓ Active Driver</span>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 'verify_otp'): ?>
        <div class="main-card">
            <div class="card-section">
                <div class="otp-section">
                    <h3 style="color: #1e40af; margin-bottom: 1rem;">
                        <?= $_SESSION['driver_temp_verify_type'] === 'email' ? '📧 Verify Your Email Address' : '📱 Verify Your Phone Number' ?>
                    </h3>
                    <p style="color: #64748b; margin-bottom: 1rem;">
                        Enter the 6-digit verification code sent to your 
                        <?= $_SESSION['driver_temp_verify_type'] === 'email' ? 'email address' : 'phone number' ?>
                    </p>
                    
                    <?php if ($otp_success): ?>
                        <div class="alert alert-success">
                            ✅ <?= htmlspecialchars($otp_success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($otp_error): ?>
                        <div class="alert alert-error">
                            ❌ <?= htmlspecialchars($otp_error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="otp-input-group" id="otpGroup">
                        <?php for($i = 0; $i < 6; $i++): ?>
                            <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" id="otpCode" value="">
                    <div style="margin: 1rem 0;">
                        ⏱️ OTP expires in <span id="countdown">05:00</span>
                    </div>
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <button type="button" class="btn-verify" onclick="resendOTP()" style="background: #6b7280;">
                            🔄 Resend OTP
                        </button>
                        <button type="button" class="btn-verify" id="verifyBtn" onclick="verifyOTP()">
                            ✅ Verify & Complete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Main Form -->
        <form method="POST" enctype="multipart/form-data" class="main-card">
            <input type="hidden" name="action" value="update_profile">
            
            <!-- Photo Section -->
            <div class="card-section">
                <div class="section-title">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Profile Photo
                </div>
                <div class="photo-upload-area">
                    <img src="<?= $profile_img ?>" alt="Profile Preview" class="photo-preview" id="photoPreview">
                    <div class="photo-buttons">
                        <button type="button" class="btn-photo btn-camera" id="openCameraBtn">
                            📷 Take Photo
                        </button>
                        <label class="btn-photo btn-upload">
                            📸 Upload Photo
                            <input type="file" name="profile" id="fileInput" accept="image/*" style="display:none;">
                        </label>
                    </div>
                </div>
                <input type="hidden" name="camera_image" id="cameraImage">
            </div>

            <!-- Vehicle Information Section (NEW) -->
            <div class="card-section">
                <div class="section-title">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path>
                    </svg>
                    Vehicle Information
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Body Number</label>
                        <input type="text" name="body_number" value="<?= htmlspecialchars($user['body_number'] ?? '') ?>" class="form-control" placeholder="e.g., TRIKE-001">
                    </div>
                    <div class="form-group">
                        <label>Plate Number</label>
                        <input type="text" name="plate_number" value="<?= htmlspecialchars($user['plate_number'] ?? '') ?>" class="form-control" placeholder="e.g., ABC-1234">
                    </div>
                    <div class="form-group full-width">
                        <label>TODA Color</label>
                        <input type="text" name="toda_color" value="<?= htmlspecialchars($user['toda_color'] ?? '') ?>" class="form-control" placeholder="e.g., Red/White, Green/Black">
                    </div>
                </div>
            </div>

            <!-- Verification Status Section -->
            <div class="card-section">
                <div class="section-title">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Verification Status
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>📧 Email Address</label>
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                            <span><?= htmlspecialchars($user['email']) ?></span>
                            <?php if ($user['email_verified'] == 1): ?>
                                <span class="verification-badge verified">✓ Verified</span>
                            <?php else: ?>
                                <span class="verification-badge unverified">⚠ Not Verified</span>
                                <button type="button" class="btn-verify" onclick="sendEmailVerification()">
                                    Verify Now
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>📞 Phone Number</label>
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                            <span><?= htmlspecialchars($user['contact'] ?? 'Not set') ?></span>
                            <?php if ($user['phone_verified'] == 1): ?>
                                <span class="verification-badge verified">✓ Verified</span>
                            <?php else: ?>
                                <span class="verification-badge unverified">⚠ Not Verified</span>
                                <?php if (!empty($user['contact'])): ?>
                                    <button type="button" class="btn-verify" onclick="sendPhoneVerification()">
                                        Verify Now
                                    </button>
                                <?php else: ?>
                                    <small style="color: #ef4444;">(Please add a phone number first)</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="card-section">
                <div class="section-title">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Account Information
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact" value="<?= htmlspecialchars($user['contact']) ?>" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" value="<?= strtoupper($user['role']) ?>" class="form-control" readonly>
                    </div>
                    <div class="form-group full-width">
                        <label>Change Password (Optional)</label>
                        <input type="password" name="password" placeholder="Leave blank if no changes" class="form-control">
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    💾 Update Profile
                </button>
                <a href="dashboard.php" class="btn-back">
                    ← Return to Dashboard
                </a>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- Camera Modal -->
<div id="cameraModal" class="camera-modal">
    <div class="camera-content">
        <video id="video" autoplay playsinline></video>
        <canvas id="canvas" style="display:none;"></canvas>
        <div class="camera-buttons">
            <button type="button" class="btn-photo btn-camera" id="captureBtn">Capture</button>
            <button type="button" class="btn-photo btn-upload" id="closeCameraBtn">Cancel</button>
        </div>
    </div>
</div>

<script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const cameraModal = document.getElementById('cameraModal');
    const openCameraBtn = document.getElementById('openCameraBtn');
    const closeCameraBtn = document.getElementById('closeCameraBtn');
    const captureBtn = document.getElementById('captureBtn');
    const cameraImageInput = document.getElementById('cameraImage');
    const photoPreview = document.getElementById('photoPreview');
    const fileInput = document.getElementById('fileInput');
    let stream = null;

    // Open Camera
    openCameraBtn.addEventListener('click', async () => {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: "user" }, 
                audio: false 
            });
            video.srcObject = stream;
            cameraModal.classList.add('active');
        } catch (err) {
            alert('Unable to access camera. Please check permissions.');
        }
    });

    // Close Camera
    function closeCamera() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        cameraModal.classList.remove('active');
    }

    closeCameraBtn.addEventListener('click', closeCamera);

    // Capture Photo
    captureBtn.addEventListener('click', () => {
        const context = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        const dataUrl = canvas.toDataURL('image/jpeg');
        cameraImageInput.value = dataUrl;
        photoPreview.src = dataUrl;
        
        closeCamera();
        
        alert('Photo captured successfully!');
    });

    // File Upload
    fileInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                photoPreview.src = ev.target.result;
                cameraImageInput.value = "";
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && cameraModal.classList.contains('active')) {
            closeCamera();
        }
    });

    // Verification Functions
    function sendEmailVerification() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'send_email_otp';
        form.appendChild(actionInput);
        
        document.body.appendChild(form);
        form.submit();
    }

    function sendPhoneVerification() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'send_phone_otp';
        form.appendChild(actionInput);
        
        document.body.appendChild(form);
        form.submit();
    }

    function verifyOTP() {
        let otpValue = '';
        const inputs = document.querySelectorAll('.otp-digit');
        inputs.forEach(input => { otpValue += input.value; });
        
        if (otpValue.length !== 6) {
            alert('Please enter the 6-digit OTP code');
            return;
        }
        
        const verifyType = '<?= $_SESSION['driver_temp_verify_type'] ?? 'phone' ?>';
        
        const verifyBtn = document.getElementById('verifyBtn');
        const originalText = verifyBtn.innerHTML;
        verifyBtn.innerHTML = '⏳ Verifying...';
        verifyBtn.disabled = true;
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'ajax_verify=1&otp_code=' + otpValue + '&verify_type=' + verifyType
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                window.location.reload();
            } else {
                alert('❌ ' + data.message);
                verifyBtn.innerHTML = originalText;
                verifyBtn.disabled = false;
            }
        })
        .catch(error => {
            alert('Error: ' + error);
            verifyBtn.innerHTML = originalText;
            verifyBtn.disabled = false;
        });
    }

    function setupOtpInputs() {
        const inputs = document.querySelectorAll('.otp-digit');
        if (!inputs.length) return;
        
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
                if (e.target.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && e.target.value.length === 0 && index > 0) {
                    inputs[index - 1].focus();
                }
            });
            
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').slice(0, 6);
                const pasteDigits = pasteData.replace(/[^0-9]/g, '').split('');
                
                pasteDigits.forEach((digit, i) => {
                    if (inputs[i]) inputs[i].value = digit;
                });
                
                if (pasteDigits.length === 6) {
                    verifyOTP();
                }
            });
        });
    }

    function resendOTP() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'resend_otp';
        form.appendChild(actionInput);
        
        document.body.appendChild(form);
        form.submit();
    }

    <?php if ($step === 'verify_otp'): ?>
    setupOtpInputs();

    let timeLeft = 300;
    <?php 
    if (isset($_SESSION['driver_temp_verify_expiry'])) {
        $expiry = $_SESSION['driver_temp_verify_expiry'];
    } else {
        $expiry = time() + 300;
    }
    ?>
    let expiryTime = <?= $expiry ?>;
    let currentTime = Math.floor(Date.now() / 1000);
    timeLeft = Math.max(0, expiryTime - currentTime);

    const countdownElement = document.getElementById('countdown');
    let countdownInterval = setInterval(function() {
        if (timeLeft <= 0) {
            if(countdownElement) {
                countdownElement.textContent = 'Expired!';
                countdownElement.style.color = '#ef4444';
            }
            clearInterval(countdownInterval);
            return;
        }
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        if(countdownElement) countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        timeLeft--;
    }, 1000);
    <?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>