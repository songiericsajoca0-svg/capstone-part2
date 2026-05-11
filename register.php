<?php
session_start();
require_once 'includes/config.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

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
        $mail->Subject = 'GoTrike Email Verification OTP';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px;'>
                <h2 style='color: #1e40af;'>GoTrike Verification</h2>
                <p>Hello <strong>$recipient_name</strong>,</p>
                <p>Your email verification OTP is:</p>
                <div style='background: #f1f5f9; padding: 15px; text-align: center; font-size: 28px; letter-spacing: 5px; font-weight: bold; border-radius: 8px;'>
                    $otp_code
                </div>
                <p style='color: #64748b; font-size: 12px; margin-top: 20px;'>Valid for 5 minutes only. Do not share this OTP with anyone.</p>
                <hr style='border-color: #e2e8f0;'>
                <p style='color: #64748b; font-size: 11px;'>GoTrike - Your Trusted Ride</p>
            </div>
        ";
        $mail->AltBody = "GoTrike Email Verification OTP: $otp_code\nValid for 5 minutes only. Do not share with anyone.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Error: {$mail->ErrorInfo}");
        return false;
    }
}

$step = $_POST['step'] ?? 'form';
$error = '';
$msg_otp = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $password_raw = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $otp_input = trim($_POST['otp_code'] ?? '');
    $otp_method = $_POST['otp_method'] ?? 'sms';
    $verify_other = isset($_POST['verify_other']);
    $skip_other = isset($_POST['skip_other']);
    $resend_otp = isset($_POST['resend_otp']);
    $resend_secondary = isset($_POST['resend_secondary']);

    // Normalize phone number
    $phone = $contact;
    if (strpos($phone, '09') === 0) {
        $phone = '+63' . substr($phone, 1);
    } elseif (strpos($phone, '9') === 0) {
        $phone = '+63' . $phone;
    }

    // STEP 1: SEND OTP
    if ($step === 'form') {
        // Check if email or contact already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR contact = ?");
        $checkStmt->bind_param("ss", $email, $contact);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            $emailCheck = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $emailCheck->bind_param("s", $email);
            $emailCheck->execute();
            $emailCheck->store_result();
            
            if ($emailCheck->num_rows > 0) {
                $error = "This email address is already registered.";
            } else {
                $error = "This contact number is already registered.";
            }
            $emailCheck->close();
            $step = 'form';
        } 
        elseif ($password_raw !== $confirm_password) {
            $error = "Passwords do not match.";
            $step = 'form';
        } 
        elseif (strlen($password_raw) < 6) {
            $error = "Password must be at least 6 characters long.";
            $step = 'form';
        } 
        else {
            // Generate OTP
            $otp_code = rand(100000, 999999);
            
            // Store in session
            $_SESSION['reg_temp_otp'] = $otp_code;
            $_SESSION['reg_temp_otp_method'] = $otp_method;
            $_SESSION['reg_otp_expiry'] = time() + 300;
            $_SESSION['reg_temp_name'] = $name;
            $_SESSION['reg_temp_email'] = $email;
            $_SESSION['reg_temp_contact'] = $contact;
            $_SESSION['reg_temp_password'] = $password_raw;
            $_SESSION['reg_temp_phone'] = $phone;
            $_SESSION['reg_email_verified'] = false;
            $_SESSION['reg_phone_verified'] = false;
            $_SESSION['reg_asked_optional'] = false;
            
            // Send OTP
            if ($otp_method === 'sms') {
                $sms_message = "GoTrike OTP: $otp_code\nValid for 5 minutes only.";
                if (function_exists('sendSMS') && sendSMS($phone, $sms_message)) {
                    $msg_otp = "OTP sent to your phone number: " . substr($phone, -10);
                    $step = 'verify_otp';
                } else {
                    $error = "Failed to send SMS OTP. Please try again.";
                    $step = 'form';
                }
            } else {
                if (sendEmailOTP($email, $otp_code, $name)) {
                    $msg_otp = "OTP sent to your email: " . substr($email, 0, 3) . "***@" . explode('@', $email)[1];
                    $step = 'verify_otp';
                } else {
                    $error = "Failed to send Email OTP. Please try again.";
                    $step = 'form';
                }
            }
        }
        $checkStmt->close();
    }
    // Resend OTP
    elseif ($resend_otp && isset($_SESSION['reg_temp_otp_method'])) {
        $otp_code = rand(100000, 999999);
        $_SESSION['reg_temp_otp'] = $otp_code;
        $_SESSION['reg_otp_expiry'] = time() + 300;
        
        if ($_SESSION['reg_temp_otp_method'] === 'sms') {
            if (function_exists('sendSMS') && sendSMS($_SESSION['reg_temp_phone'], "GoTrike OTP: $otp_code\nValid for 5 minutes only.")) {
                $msg_otp = "New OTP sent to your phone number";
                $step = 'verify_otp';
            } else {
                $error = "Failed to resend SMS OTP.";
                $step = 'verify_otp';
            }
        } else {
            if (sendEmailOTP($_SESSION['reg_temp_email'], $otp_code, $_SESSION['reg_temp_name'])) {
                $msg_otp = "New OTP sent to your email";
                $step = 'verify_otp';
            } else {
                $error = "Failed to resend Email OTP.";
                $step = 'verify_otp';
            }
        }
    }
    // STEP 2: VERIFY OTP
    elseif ($step === 'verify_otp') {
        if (!isset($_SESSION['reg_temp_otp'])) {
            $error = "No active OTP found. Please start again.";
            $step = 'form';
        } 
        elseif (time() > $_SESSION['reg_otp_expiry']) {
            $error = "OTP has expired. Please request a new one.";
            unset($_SESSION['reg_temp_otp'], $_SESSION['reg_otp_expiry']);
            $step = 'form';
        }
        elseif ($otp_input != $_SESSION['reg_temp_otp']) {
            $error = "Invalid OTP code. Please try again.";
            $step = 'verify_otp';
        }
        else {
            // OTP verified - set verified flags
            if ($_SESSION['reg_temp_otp_method'] === 'sms') {
                $_SESSION['reg_phone_verified'] = true;
            } else {
                $_SESSION['reg_email_verified'] = true;
            }
            
            // Ask for optional verification
            if (!isset($_SESSION['reg_asked_optional']) || $_SESSION['reg_asked_optional'] == false) {
                $_SESSION['reg_asked_optional'] = true;
                $step = 'ask_optional';
            }
        }
    }
    // STEP 3: ASK FOR OPTIONAL VERIFICATION
    elseif ($step === 'ask_optional') {
        if ($verify_other) {
            // User wants to verify other method
            $otp_code = rand(100000, 999999);
            $_SESSION['reg_temp_otp_secondary'] = $otp_code;
            $_SESSION['reg_secondary_expiry'] = time() + 300;
            
            if ($_SESSION['reg_temp_otp_method'] === 'sms') {
                if (sendEmailOTP($_SESSION['reg_temp_email'], $otp_code, $_SESSION['reg_temp_name'])) {
                    $msg_otp = "Verification OTP sent to your email";
                    $step = 'verify_secondary';
                } else {
                    $error = "Failed to send email verification.";
                    $step = 'ask_optional';
                }
            } else {
                if (function_exists('sendSMS') && sendSMS($_SESSION['reg_temp_phone'], "GoTrike OTP: $otp_code\nValid for 5 minutes only.")) {
                    $msg_otp = "Verification OTP sent to your phone number";
                    $step = 'verify_secondary';
                } else {
                    $error = "Failed to send SMS verification.";
                    $step = 'ask_optional';
                }
            }
        } elseif ($skip_other) {
            // User skipped - REGISTER IMMEDIATELY
            $step = 'do_registration';
        }
    }
    // Resend secondary OTP
    elseif ($resend_secondary && isset($_SESSION['reg_temp_otp_method'])) {
        $otp_code = rand(100000, 999999);
        $_SESSION['reg_temp_otp_secondary'] = $otp_code;
        $_SESSION['reg_secondary_expiry'] = time() + 300;
        
        if ($_SESSION['reg_temp_otp_method'] === 'sms') {
            if (sendEmailOTP($_SESSION['reg_temp_email'], $otp_code, $_SESSION['reg_temp_name'])) {
                $msg_otp = "New verification OTP sent to your email";
                $step = 'verify_secondary';
            } else {
                $error = "Failed to resend verification email.";
                $step = 'verify_secondary';
            }
        } else {
            if (function_exists('sendSMS') && sendSMS($_SESSION['reg_temp_phone'], "GoTrike OTP: $otp_code\nValid for 5 minutes only.")) {
                $msg_otp = "New verification OTP sent to your phone number";
                $step = 'verify_secondary';
            } else {
                $error = "Failed to resend verification SMS.";
                $step = 'verify_secondary';
            }
        }
    }
    // STEP 4: VERIFY SECONDARY OTP
    elseif ($step === 'verify_secondary') {
        if (!isset($_SESSION['reg_temp_otp_secondary'])) {
            $error = "No active OTP found.";
            $step = 'ask_optional';
        }
        elseif (time() > $_SESSION['reg_secondary_expiry']) {
            $error = "OTP has expired. Please try again.";
            unset($_SESSION['reg_temp_otp_secondary']);
            $step = 'ask_optional';
        }
        elseif ($otp_input != $_SESSION['reg_temp_otp_secondary']) {
            $error = "Invalid OTP code. Please try again.";
            $step = 'verify_secondary';
        }
        else {
            // Secondary OTP verified - mark both as verified
            $_SESSION['reg_email_verified'] = true;
            $_SESSION['reg_phone_verified'] = true;
            unset($_SESSION['reg_temp_otp_secondary']);
            // REGISTER IMMEDIATELY
            $step = 'do_registration';
        }
    }
    // STEP 5: DO REGISTRATION
    elseif ($step === 'do_registration') {
        // Check if we have data
        if (!isset($_SESSION['reg_temp_name']) || !isset($_SESSION['reg_temp_email'])) {
            $error = "Registration data missing. Please start over.";
            $step = 'form';
        } else {
            // Hash password
            $hashed_password = password_hash($_SESSION['reg_temp_password'], PASSWORD_DEFAULT);
            
            // Set verification flags
            $email_verified = isset($_SESSION['reg_email_verified']) && $_SESSION['reg_email_verified'] ? 1 : 0;
            $phone_verified = isset($_SESSION['reg_phone_verified']) && $_SESSION['reg_phone_verified'] ? 1 : 0;
            
            $sql = "INSERT INTO users (name, email, contact, password, role, email_verified, phone_verified, status, created_at) 
                    VALUES (?, ?, ?, ?, 'passenger', ?, ?, 'offline', NOW())";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("ssssii", 
                    $_SESSION['reg_temp_name'], 
                    $_SESSION['reg_temp_email'], 
                    $_SESSION['reg_temp_contact'], 
                    $hashed_password,
                    $email_verified,
                    $phone_verified
                );
                
                if ($stmt->execute()) {
                    // Clear session
                    session_destroy();
                    
                    header("Location: login.php?msg=" . urlencode("Registration successful! Please login."));
                    exit;
                } else {
                    $error = "Failed to create account. Error: " . $stmt->error;
                    $step = 'form';
                }
                $stmt->close();
            } else {
                $error = "Database error: " . $conn->error;
                $step = 'form';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Register | GoTrike</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #1e40af;
            --secondary: #3b82f6;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --error: #ef4444;
            --success: #10b981;
            --border: #e2e8f0;
            --bg-gradient: linear-gradient(135deg, #0ea5e9 0%, #ffffff 50%, #1e3a8a 100%);
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-gradient);
            background-attachment: fixed;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 16px;
        }
        .register-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            padding: 24px 20px;
            border-radius: 32px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 600px;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            text-align: center;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 16px;
            transition: color 0.2s;
        }
        .back-home:active { color: var(--primary); }
        h2 {
            color: var(--text-main);
            font-weight: 800;
            font-size: 1.8rem;
            margin: 0;
            letter-spacing: -0.03em;
        }
        .subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 24px;
        }
        .alert {
            padding: 12px 14px;
            border-radius: 16px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fef2f2;
            color: var(--error);
            border: 1px solid #fee2e2;
            text-align: left;
            word-break: break-word;
        }
        .alert-info {
            background: #f0fdf4;
            color: var(--success);
            border: 1px solid #dcfce7;
        }
        .form-grid {
            display: grid;
            gap: 18px;
            text-align: left;
        }
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: var(--text-main);
        }
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-wrapper i.icon-left {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            width: 18px;
            z-index: 1;
            pointer-events: none;
        }
        .eye-toggle {
            position: absolute;
            right: 14px;
            cursor: pointer;
            color: var(--text-muted);
            background: none;
            border: none;
            padding: 0;
            display: flex;
            align-items: center;
            z-index: 1;
        }
        input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--border);
            border-radius: 16px;
            font-size: 1rem;
            transition: all 0.2s;
            background: #f8fafc;
            font-family: inherit;
            padding-left: 44px;
        }
        input:focus {
            outline: none;
            border-color: var(--secondary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }
        .password-match-warning {
            font-size: 0.75rem;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .match-success { color: var(--success); }
        .match-error { color: var(--error); }
        
        .otp-method-section {
            background: #f8fafc;
            border-radius: 20px;
            padding: 16px;
            margin-top: 8px;
        }
        .method-title {
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .method-options {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .method-option {
            flex: 1;
            min-width: 100px;
        }
        .method-option input { display: none; }
        .method-option label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: white;
            border: 2px solid var(--border);
            border-radius: 16px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s;
            margin: 0;
        }
        .method-option input:checked + label {
            border-color: var(--secondary);
            background: #eff6ff;
            color: var(--primary);
        }
        
        .otp-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 30px 20px;
            border-radius: 24px;
            text-align: center;
            border: 2px solid var(--secondary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .otp-header { margin-bottom: 20px; }
        .otp-header h3 {
            color: var(--primary);
            font-size: 1.2rem;
            margin: 0 0 6px 0;
        }
        .otp-header p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin: 0;
        }
        .otp-display {
            background: white;
            padding: 15px;
            border-radius: 16px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: var(--text-main);
            font-weight: 600;
        }
        .otp-input-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .otp-digit {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.6rem;
            font-weight: 700;
            padding: 0;
            border: 2px solid var(--border);
            border-radius: 14px;
            background: white;
            font-family: monospace;
        }
        .otp-digit:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            outline: none;
        }
        .optional-verify-banner {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 25px;
            border-radius: 24px;
            text-align: center;
            border: 2px solid #f59e0b;
        }
        .optional-verify-banner h3 {
            margin: 0 0 10px 0;
            color: #92400e;
            font-size: 1.2rem;
        }
        .optional-verify-banner p {
            margin: 0 0 20px 0;
            color: #78350f;
            font-size: 0.95rem;
        }
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .btn-verify, .btn-skip {
            padding: 12px 28px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.95rem;
        }
        .btn-verify {
            background: #10b981;
            color: white;
        }
        .btn-skip {
            background: #6b7280;
            color: white;
        }
        .btn-register {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 24px;
            font-family: inherit;
        }
        .btn-register:active { transform: scale(0.97); }
        .btn-resend {
            background: rgba(255,255,255,0.9);
            color: var(--primary);
            margin-top: 16px;
            padding: 12px 20px;
            font-size: 0.9rem;
            width: auto;
            display: inline-flex;
            border: 1px solid var(--secondary);
        }
        .footer-text {
            margin-top: 24px;
            font-size: 0.85rem;
            color: var(--text-muted);
            text-align: center;
        }
        .footer-text a {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 700;
        }
        .timer-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 15px;
            background: rgba(255,255,255,0.8);
            display: inline-block;
            padding: 5px 15px;
            border-radius: 50px;
        }
        @media (max-width: 480px) {
            .otp-digit {
                width: 45px;
                height: 55px;
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>

<div class="register-card">
    <a href="index.php" class="back-home"><i data-lucide="chevron-left" size="18"></i> Back to Home</a>
    <h2>Create Account</h2>
    <p class="subtitle">Join GoTrike and start your journey.</p>

    <?php if(!empty($error)): ?>
        <div class="alert"><i data-lucide="alert-circle" size="18"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if(!empty($msg_otp)): ?>
        <div class="alert alert-info"><i data-lucide="shield-check" size="18"></i> <?php echo htmlspecialchars($msg_otp); ?></div>
    <?php endif; ?>

    <form method="POST" id="regForm">
        <input type="hidden" name="step" id="step" value="<?php echo $step; ?>">
        
        <?php if($step === 'form'): ?>
        <div class="form-grid">
            <div>
                <label>Full Name</label>
                <div class="input-wrapper">
                    <i data-lucide="user" class="icon-left"></i>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" placeholder="John Doe" required>
                </div>
            </div>
            <div>
                <label>Email Address</label>
                <div class="input-wrapper">
                    <i data-lucide="mail" class="icon-left"></i>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="name@email.com" required>
                </div>
            </div>
            <div>
                <label>Contact Number</label>
                <div class="input-wrapper">
                    <i data-lucide="phone" class="icon-left"></i>
                    <input type="tel" name="contact" value="<?php echo htmlspecialchars($_POST['contact'] ?? ''); ?>" placeholder="09123456789" required>
                </div>
            </div>
            
            <div class="otp-method-section">
                <div class="method-title">
                    <i data-lucide="shield" size="16"></i>
                    <span>Receive OTP via:</span>
                </div>
                <div class="method-options">
                    <div class="method-option">
                        <input type="radio" name="otp_method" id="method_sms" value="sms" checked>
                        <label for="method_sms">
                            <i data-lucide="smartphone" size="16"></i>
                            SMS 
                        </label>
                    </div>
                    <div class="method-option">
                        <input type="radio" name="otp_method" id="method_email" value="email">
                        <label for="method_email">
                            <i data-lucide="mail" size="16"></i>
                            Email 
                        </label>
                    </div>
                </div>
            </div>
            
            <div>
                <label>Password</label>
                <div class="input-wrapper">
                    <i data-lucide="lock" class="icon-left"></i>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                    <button type="button" class="eye-toggle" onclick="togglePassword()"><i data-lucide="eye" id="eyeIcon" size="18"></i></button>
                </div>
            </div>
            <div>
                <label>Confirm Password</label>
                <div class="input-wrapper">
                    <i data-lucide="lock" class="icon-left"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                    <button type="button" class="eye-toggle" onclick="toggleConfirmPassword()"><i data-lucide="eye" id="confirmEyeIcon" size="18"></i></button>
                </div>
                <div id="passwordMatchWarning" class="password-match-warning"></div>
            </div>
        </div>
        
        <button type="submit" class="btn-register">
            <span>Send OTP & Register</span>
            <i data-lucide="arrow-right" size="18"></i>
        </button>
        
        <?php elseif($step === 'verify_otp'): ?>
        <div class="otp-section">
            <div class="otp-header">
                <h3>🔐 Enter OTP Code</h3>
                <p>We sent a 6-digit code to your 
                    <?php echo $_SESSION['reg_temp_otp_method'] === 'sms' ? 'phone number' : 'email address'; ?>
                </p>
            </div>
            <div class="otp-display">
                <?php echo $_SESSION['reg_temp_otp_method'] === 'sms' ? '📱 ' . htmlspecialchars($_SESSION['reg_temp_phone']) : '✉️ ' . htmlspecialchars($_SESSION['reg_temp_email']); ?>
            </div>
            <div class="otp-input-group" id="otpGroup">
                <?php for($i=0; $i<6; $i++): ?>
                <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                <?php endfor; ?>
            </div>
            <input type="hidden" name="otp_code" id="otpCode" value="">
            <div class="timer-text">⏱️ OTP expires in <span id="countdown">05:00</span></div>
            <button type="button" class="btn-register btn-resend" onclick="resendOTP()">
                <i data-lucide="refresh-cw" size="16"></i> Resend OTP
            </button>
            <button type="submit" class="btn-register">
                <span>Verify OTP</span>
                <i data-lucide="check-circle" size="18"></i>
            </button>
        </div>
        
        <?php elseif($step === 'ask_optional'): ?>
        <div class="optional-verify-banner">
            <h3>✨ Enhance Your Account Security</h3>
            <p>Would you also like to verify your 
                <?php echo $_SESSION['reg_temp_otp_method'] === 'sms' ? 'email address' : 'phone number'; ?>?
                <br><small>This will add an extra layer of security to your account.</small>
            </p>
            <div class="button-group">
                <button type="button" class="btn-verify" onclick="verifyOtherMethod()">
                    <i data-lucide="check" size="16"></i> Yes, verify
                </button>
                <button type="button" class="btn-skip" onclick="skipOtherMethod()">
                    <i data-lucide="x" size="16"></i> Skip for now
                </button>
            </div>
        </div>
        
        <?php elseif($step === 'verify_secondary'): ?>
        <div class="otp-section">
            <div class="otp-header">
                <h3>✨ Verify Your Other Contact</h3>
                <p>We sent a verification code to your 
                    <?php echo $_SESSION['reg_temp_otp_method'] === 'sms' ? 'email address' : 'phone number'; ?>
                </p>
            </div>
            <div class="otp-display">
                <?php echo $_SESSION['reg_temp_otp_method'] === 'sms' ? '✉️ ' . htmlspecialchars($_SESSION['reg_temp_email']) : '📱 ' . htmlspecialchars($_SESSION['reg_temp_phone']); ?>
            </div>
            <div class="otp-input-group" id="otpGroupSecondary">
                <?php for($i=0; $i<6; $i++): ?>
                <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                <?php endfor; ?>
            </div>
            <input type="hidden" name="otp_code" id="otpCodeSecondary" value="">
            <div class="timer-text">⏱️ OTP expires in <span id="countdownSecondary">05:00</span></div>
            <button type="button" class="btn-register btn-resend" onclick="resendSecondaryOTP()">
                <i data-lucide="refresh-cw" size="16"></i> Resend OTP
            </button>
            <button type="submit" class="btn-register" id="completeRegistrationBtn">
                <span>Verify & Complete Registration</span>
                <i data-lucide="check-circle" size="18"></i>
            </button>
        </div>
        <?php endif; ?>
    </form>

    <p class="footer-text">Already have an account? <a href="login.php">Login here</a></p>
</div>

<script>
    function refreshIcons() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
    refreshIcons();
    
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const warningDiv = document.getElementById('passwordMatchWarning');
    
    function checkPasswordMatch() {
        if (!passwordInput || !confirmPasswordInput || !warningDiv) return;
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        if (confirmPassword.length === 0) {
            warningDiv.innerHTML = '';
            refreshIcons();
            return;
        }
        if (password === confirmPassword) {
            warningDiv.innerHTML = '<i data-lucide="check-circle" size="14"></i> <span>Passwords match!</span>';
            warningDiv.className = 'password-match-warning match-success';
        } else {
            warningDiv.innerHTML = '<i data-lucide="alert-circle" size="14"></i> <span>Passwords do not match</span>';
            warningDiv.className = 'password-match-warning match-error';
        }
        refreshIcons();
    }
    
    if (passwordInput && confirmPasswordInput) {
        passwordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        checkPasswordMatch();
    }
    
    function togglePassword() {
        const p = document.getElementById('password');
        const i = document.getElementById('eyeIcon');
        if(p && i) {
            const isPassword = p.type === 'password';
            p.type = isPassword ? 'text' : 'password';
            i.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
            refreshIcons();
        }
    }

    function toggleConfirmPassword() {
        const p = document.getElementById('confirm_password');
        const i = document.getElementById('confirmEyeIcon');
        if(p && i) {
            const isPassword = p.type === 'password';
            p.type = isPassword ? 'text' : 'password';
            i.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
            refreshIcons();
        }
    }
    
    function verifyOtherMethod() {
        const form = document.getElementById('regForm');
        const stepInput = document.getElementById('step');
        if (stepInput) stepInput.value = 'ask_optional';
        
        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'verify_other';
        input.value = '1';
        form.appendChild(input);
        form.submit();
    }
    
    function skipOtherMethod() {
        const form = document.getElementById('regForm');
        const stepInput = document.getElementById('step');
        if (stepInput) stepInput.value = 'do_registration';
        
        form.submit();
    }

    function getOtpValue(groupId) {
        let otpValue = '';
        const inputs = document.querySelectorAll(`#${groupId} .otp-digit`);
        inputs.forEach(input => { otpValue += input.value; });
        return otpValue;
    }

    function setupOtpInputs(groupId, hiddenInputId, isSecondary = false) {
        const inputs = document.querySelectorAll(`#${groupId} .otp-digit`);
        if (!inputs.length) return;
        
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
                if (e.target.value.length === 1 && index < inputs.length - 1) {
                    const nextInput = document.querySelectorAll(`#${groupId} .otp-digit`)[index + 1];
                    if (nextInput) nextInput.focus();
                }
                const fullOtp = getOtpValue(groupId);
                document.getElementById(hiddenInputId).value = fullOtp;
                if (fullOtp.length === 6) {
                    // For secondary OTP, we need to submit to do_registration
                    if (isSecondary) {
                        const form = document.getElementById('regForm');
                        const stepInput = document.getElementById('step');
                        if (stepInput) stepInput.value = 'do_registration';
                        form.submit();
                    } else {
                        document.getElementById('regForm').submit();
                    }
                }
            });
            
            input.addEventListener('keydown', (e) => {
                const currentInputs = document.querySelectorAll(`#${groupId} .otp-digit`);
                if (e.key === 'Backspace' && e.target.value.length === 0 && index > 0) {
                    const prevInput = currentInputs[index - 1];
                    if (prevInput) prevInput.focus();
                }
            });
            
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').slice(0, 6);
                const pasteDigits = pasteData.replace(/[^0-9]/g, '').split('');
                const currentInputs = document.querySelectorAll(`#${groupId} .otp-digit`);
                
                pasteDigits.forEach((digit, i) => {
                    if (currentInputs[i]) currentInputs[i].value = digit;
                });
                
                const lastFilledIndex = Math.min(pasteDigits.length - 1, currentInputs.length - 1);
                if (lastFilledIndex < currentInputs.length - 1 && currentInputs[lastFilledIndex + 1]) {
                    currentInputs[lastFilledIndex + 1].focus();
                }
                
                const fullOtp = getOtpValue(groupId);
                document.getElementById(hiddenInputId).value = fullOtp;
                if (fullOtp.length === 6) {
                    if (isSecondary) {
                        const form = document.getElementById('regForm');
                        const stepInput = document.getElementById('step');
                        if (stepInput) stepInput.value = 'do_registration';
                        form.submit();
                    } else {
                        document.getElementById('regForm').submit();
                    }
                }
            });
        });
    }
    
    <?php if($step === 'verify_otp'): ?>
    setupOtpInputs('otpGroup', 'otpCode', false);
    
    let timeLeft = <?php echo max(0, ($_SESSION['reg_otp_expiry'] ?? time()) - time()); ?>;
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
    
    function resendOTP() {
        const form = document.getElementById('regForm');
        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'resend_otp';
        input.value = '1';
        form.appendChild(input);
        form.submit();
    }
    <?php elseif($step === 'verify_secondary'): ?>
    setupOtpInputs('otpGroupSecondary', 'otpCodeSecondary', true);
    
    let timeLeftSecondary = <?php echo max(0, ($_SESSION['reg_secondary_expiry'] ?? time()) - time()); ?>;
    const countdownElementSecondary = document.getElementById('countdownSecondary');
    let countdownIntervalSecondary = setInterval(function() {
        if (timeLeftSecondary <= 0) {
            if(countdownElementSecondary) {
                countdownElementSecondary.textContent = 'Expired!';
                countdownElementSecondary.style.color = '#ef4444';
            }
            clearInterval(countdownIntervalSecondary);
            return;
        }
        const minutes = Math.floor(timeLeftSecondary / 60);
        const seconds = timeLeftSecondary % 60;
        if(countdownElementSecondary) countdownElementSecondary.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        timeLeftSecondary--;
    }, 1000);
    
    function resendSecondaryOTP() {
        const form = document.getElementById('regForm');
        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'resend_secondary';
        input.value = '1';
        form.appendChild(input);
        form.submit();
    }
    
    // Add manual button handler for complete registration
    document.getElementById('completeRegistrationBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const form = document.getElementById('regForm');
        const stepInput = document.getElementById('step');
        const otpCode = document.getElementById('otpCodeSecondary').value;
        
        if (otpCode.length === 6) {
            if (stepInput) stepInput.value = 'do_registration';
            form.submit();
        }
    });
    <?php endif; ?>
    
    window.addEventListener('load', function() {
        setTimeout(refreshIcons, 100);
        refreshIcons();
    });
</script>
</body>
</html>