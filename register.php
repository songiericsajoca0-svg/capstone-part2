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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $contact      = trim($_POST['contact'] ?? '');
    $password_raw = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $sms_otp_input    = trim($_POST['sms_otp'] ?? '');
    $email_otp_input  = trim($_POST['email_otp'] ?? '');
    $otp_method = $_POST['otp_method'] ?? 'both'; // New: OTP method choice

    // Normalize phone number
    $phone = $contact;
    if (strpos($phone, '09') === 0) {
        $phone = '+63' . substr($phone, 1);
    } elseif (strpos($phone, '9') === 0) {
        $phone = '+63' . $phone;
    }

    // PHASE 1: SEND OTPs
    if (empty($sms_otp_input) && empty($email_otp_input)) {
        $checkStmt = $conn->prepare("SELECT email, contact FROM users WHERE email = ? OR contact = ?");
        $checkStmt->bind_param("ss", $email, $contact);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        $emailExists = false;
        $contactExists = false;
        
        while ($row = $result->fetch_assoc()) {
            if ($row['email'] === $email) $emailExists = true;
            if ($row['contact'] === $contact) $contactExists = true;
        }
        $checkStmt->close();

        if ($emailExists) {
            $error = "This email address is already registered.";
        } elseif ($contactExists) {
            $error = "This contact number is already registered.";
        } elseif ($password_raw !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Store OTP method in session
            $_SESSION['temp_otp_method'] = $otp_method;
            
            $sms_sent = true;
            $email_sent = true;
            $sms_otp_code = null;
            $email_otp_code = null;
            
            $sent_methods = [];
            
            // Send SMS OTP if selected
            if ($otp_method === 'sms' || $otp_method === 'both') {
                $sms_otp_code = rand(100000, 999999);
                $_SESSION['temp_sms_otp'] = $sms_otp_code;
                $sms_message = "GoTrike OTP: $sms_otp_code\nValid for 5 minutes only.";
                $sms_sent = sendSMS($phone, $sms_message);
                if ($sms_sent) $sent_methods[] = 'SMS';
            }
            
            // Send Email OTP if selected
            if ($otp_method === 'email' || $otp_method === 'both') {
                $email_otp_code = rand(100000, 999999);
                $_SESSION['temp_email_otp'] = $email_otp_code;
                $email_sent = sendEmailOTP($email, $email_otp_code, $name);
                if ($email_sent) $sent_methods[] = 'Email';
            }
            
            $_SESSION['otp_expiry']        = time() + 300;
            $_SESSION['temp_name']          = $name;
            $_SESSION['temp_email']         = $email;
            $_SESSION['temp_contact']       = $contact;
            $_SESSION['temp_password']      = $password_raw;
            $_SESSION['temp_phone']         = $phone;
            
            $any_sent = ($otp_method === 'sms' && $sms_sent) || 
                       ($otp_method === 'email' && $email_sent) || 
                       ($otp_method === 'both' && ($sms_sent || $email_sent));
            
            if ($any_sent) {
                $show_otp = true;
                $msg_otp = "OTP code sent via " . implode(' & ', $sent_methods);
            } else {
                $error = "Failed to send OTP. Please try again.";
                unset($_SESSION['temp_sms_otp'], $_SESSION['temp_email_otp'], $_SESSION['otp_expiry']);
            }
        }
    } 
    // PHASE 2: VERIFY OTPS & REGISTER
    else {
        if (!isset($_SESSION['temp_sms_otp']) && !isset($_SESSION['temp_email_otp'])) {
            $error = "No active OTP found. Please start again.";
        } 
        elseif (time() > $_SESSION['otp_expiry']) {
            $error = "OTP has expired. Please request a new one.";
            unset($_SESSION['temp_sms_otp'], $_SESSION['temp_email_otp'], $_SESSION['otp_expiry']);
        } 
        else {
            $method = $_SESSION['temp_otp_method'] ?? 'both';
            $sms_valid = true;
            $email_valid = true;
            
            // Validate SMS OTP if required
            if ($method === 'sms' || $method === 'both') {
                if (!isset($_SESSION['temp_sms_otp']) || $sms_otp_input != $_SESSION['temp_sms_otp']) {
                    $sms_valid = false;
                    $error = "Invalid SMS OTP code.";
                }
            }
            
            // Validate Email OTP if required
            if (($method === 'email' || $method === 'both') && !isset($error)) {
                if (!isset($_SESSION['temp_email_otp']) || $email_otp_input != $_SESSION['temp_email_otp']) {
                    $email_valid = false;
                    $error = "Invalid Email OTP code.";
                }
            }
            
            if (!isset($error)) {
                $hashed_password = password_hash($_SESSION['temp_password'], PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO users (name, email, contact, password, role) VALUES (?, ?, ?, ?, 'passenger')");
                $stmt->bind_param("ssss", 
                    $_SESSION['temp_name'], 
                    $_SESSION['temp_email'], 
                    $_SESSION['temp_contact'], 
                    $hashed_password
                );
                
                if ($stmt->execute()) {
                    $tempData = ['temp_sms_otp', 'temp_email_otp', 'otp_expiry', 'temp_name', 
                                'temp_email', 'temp_contact', 'temp_password', 'temp_phone', 'temp_otp_method'];
                    foreach ($tempData as $key) {
                        unset($_SESSION[$key]);
                    }
                    
                    $stmt->close();
                    header("Location: /login.php?msg=" . urlencode("Registration successful! Please login."));
                    exit;
                } else {
                    $error = "Failed to create account. Please try again.";
                    error_log("DB Error: " . $conn->error);
                }
                $stmt->close();
            }
        }
        
        $show_otp = true;
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
    * { 
      box-sizing: border-box;
      -webkit-tap-highlight-color: transparent;
    }
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
    .back-home:active {
      color: var(--primary);
    }
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
    input, select {
      width: 100%;
      padding: 14px 16px;
      border: 1.5px solid var(--border);
      border-radius: 16px;
      font-size: 1rem;
      transition: all 0.2s;
      background: #f8fafc;
      font-family: inherit;
    }
    input {
      padding-left: 44px;
    }
    select {
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 16px center;
    }
    input:focus, select:focus {
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
    
    /* OTP Method Selector Styles */
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
    .method-option input {
      display: none;
    }
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
    .method-option label:active {
      transform: scale(0.98);
    }
    
    /* OTP Section Styles */
    .otp-section {
      background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
      padding: 20px 16px;
      border-radius: 24px;
      margin-top: 8px;
      text-align: center;
      border: 2px solid var(--secondary);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    .otp-header {
      margin-bottom: 20px;
    }
    .otp-header h3 {
      color: var(--primary);
      font-size: 1.1rem;
      margin: 0 0 6px 0;
    }
    .otp-header p {
      color: var(--text-muted);
      font-size: 0.8rem;
      margin: 0;
    }
    .otp-container {
      display: flex;
      gap: 16px;
      justify-content: center;
      margin-bottom: 20px;
      flex-direction: column;
    }
    .otp-card {
      flex: 1;
      background: white;
      padding: 18px 12px;
      border-radius: 20px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
      transition: transform 0.2s;
    }
    .otp-card:active {
      transform: scale(0.98);
    }
    .otp-card .otp-label {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-bottom: 12px;
      font-weight: 700;
      color: var(--text-main);
      font-size: 0.9rem;
    }
    .otp-card .otp-label i {
      color: var(--secondary);
    }
    .otp-card .phone-number, 
    .otp-card .email-address {
      font-size: 0.7rem;
      color: var(--text-muted);
      margin-bottom: 14px;
      word-break: break-all;
      background: #f1f5f9;
      padding: 6px 10px;
      border-radius: 30px;
      display: inline-block;
      max-width: 100%;
    }
    .otp-input-group {
      display: flex;
      gap: 6px;
      justify-content: center;
      flex-wrap: wrap;
    }
    .otp-digit {
      width: 44px;
      height: 52px;
      text-align: center;
      font-size: 1.4rem;
      font-weight: 700;
      padding: 0;
      border: 2px solid var(--border);
      border-radius: 14px;
      background: white;
      transition: all 0.2s;
      font-family: monospace;
    }
    .otp-digit:focus {
      border-color: var(--secondary);
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
      outline: none;
    }
    .divider {
      text-align: center;
      margin: 12px 0;
      font-size: 0.75rem;
      color: var(--text-muted);
      position: relative;
      font-weight: 600;
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
      transition: all 0.25s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-top: 24px;
      font-family: inherit;
    }
    .btn-register:active {
      transform: scale(0.97);
      background: #0f2b6b;
    }
    .btn-register:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }
    .btn-resend {
      background: rgba(255,255,255,0.9);
      color: var(--primary);
      margin-top: 16px;
      padding: 12px 20px;
      font-size: 0.9rem;
      width: auto;
      display: inline-flex;
      border: 1px solid var(--secondary);
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    .btn-resend:active {
      background: white;
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
      font-size: 0.7rem;
      color: var(--text-muted);
      margin-top: 12px;
      background: rgba(255,255,255,0.7);
      display: inline-block;
      padding: 4px 12px;
      border-radius: 50px;
    }
    
    @media (min-width: 480px) {
      .register-card {
        padding: 32px 28px;
      }
      .otp-container {
        flex-direction: row;
        gap: 20px;
      }
      .otp-digit {
        width: 52px;
        height: 58px;
        font-size: 1.6rem;
      }
      .otp-card {
        padding: 20px 16px;
      }
      .method-options {
        flex-wrap: nowrap;
      }
    }
    
    @media (min-width: 640px) {
      body {
        padding: 24px;
      }
      .register-card {
        padding: 40px 36px;
        border-radius: 48px;
      }
      h2 {
        font-size: 2rem;
      }
      .otp-digit {
        width: 58px;
        height: 64px;
        font-size: 1.8rem;
      }
    }
    
    @media (max-width: 380px) {
      .otp-digit {
        width: 38px;
        height: 48px;
        font-size: 1.2rem;
      }
      .method-option label {
        font-size: 0.75rem;
        padding: 8px;
      }
    }
  </style>
</head>
<body>

<div class="register-card">
    <a href="/index.php" class="back-home"><i data-lucide="chevron-left" size="18"></i> Back to Home</a>
    <h2>Create Account</h2>
    <p class="subtitle">Join GoTrike and start your journey.</p>

    <?php if(isset($error)): ?>
        <div class="alert"><i data-lucide="alert-circle" size="18"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if(isset($msg_otp)): ?>
        <div class="alert alert-info"><i data-lucide="shield-check" size="18"></i> <?php echo htmlspecialchars($msg_otp); ?></div>
    <?php endif; ?>

    <form method="POST" id="regForm">
        <!-- MAIN FIELDS -->
        <div id="mainFields" <?php echo isset($show_otp) ? 'style="display:none;"' : ''; ?>>
            <div class="form-grid">
                <div>
                    <label>Full Name</label>
                    <div class="input-wrapper">
                        <i data-lucide="user" class="icon-left"></i>
                        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ($_SESSION['temp_name'] ?? '')); ?>" placeholder="John Doe" inputmode="text">
                    </div>
                </div>
                <div>
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i data-lucide="mail" class="icon-left"></i>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ($_SESSION['temp_email'] ?? '')); ?>" placeholder="name@email.com" inputmode="email">
                    </div>
                </div>
                <div>
                    <label>Contact Number</label>
                    <div class="input-wrapper">
                        <i data-lucide="phone" class="icon-left"></i>
                        <input type="tel" name="contact" id="contact" value="<?php echo htmlspecialchars($_POST['contact'] ?? ($_SESSION['temp_contact'] ?? '')); ?>" placeholder="09123456789" inputmode="numeric">
                    </div>
                </div>
                
                <!-- NEW: OTP Method Selection -->
                <div class="otp-method-section">
                    <div class="method-title">
                        <i data-lucide="shield" size="16"></i>
                        <span>Receive OTP via:</span>
                    </div>
                    <div class="method-options">
                        <div class="method-option">
                            <input type="radio" name="otp_method" id="method_sms" value="sms" <?php echo (!isset($_POST['otp_method']) || $_POST['otp_method'] === 'sms') ? 'checked' : ''; ?>>
                            <label for="method_sms">
                                <i data-lucide="smartphone" size="16"></i>
                                SMS 
                            </label>
                        </div>
                        <div class="method-option">
                            <input type="radio" name="otp_method" id="method_email" value="email" <?php echo (isset($_POST['otp_method']) && $_POST['otp_method'] === 'email') ? 'checked' : ''; ?>>
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
                        <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="new-password">
                        <button type="button" class="eye-toggle" onclick="togglePassword()" aria-label="Show password"><i data-lucide="eye" id="eyeIcon" size="18"></i></button>
                    </div>
                </div>
                <div>
                    <label>Confirm Password</label>
                    <div class="input-wrapper">
                        <i data-lucide="lock" class="icon-left"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" autocomplete="new-password">
                        <button type="button" class="eye-toggle" onclick="toggleConfirmPassword()" aria-label="Show password"><i data-lucide="eye" id="confirmEyeIcon" size="18"></i></button>
                    </div>
                    <div id="passwordMatchWarning" class="password-match-warning"></div>
                </div>
            </div>
        </div>

        <?php if(isset($show_otp)): ?>
        <div class="otp-section">
            <div class="otp-header">
                <h3>🔐 Verification Required</h3>
                <p>Please enter the OTP code sent to your 
                    <?php 
                        $method = $_SESSION['temp_otp_method'] ?? 'both';
                        if($method === 'sms') echo 'phone number';
                        elseif($method === 'email') echo 'email address';
                        else echo 'phone and email';
                    ?>
                </p>
            </div>
            
            <div class="otp-container">
                <?php if($_SESSION['temp_otp_method'] === 'sms' || $_SESSION['temp_otp_method'] === 'both'): ?>
                <!-- SMS OTP Card -->
                <div class="otp-card">
                    <div class="otp-label">
                        <i data-lucide="smartphone" size="18"></i>
                        <span>SMS Verification</span>
                    </div>
                    <div class="phone-number">
                        📱 <?php echo htmlspecialchars($_SESSION['temp_phone'] ?? $phone); ?>
                    </div>
                    <div class="otp-input-group" id="smsOtpGroup">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if($_SESSION['temp_otp_method'] === 'email' || $_SESSION['temp_otp_method'] === 'both'): ?>
                <!-- Email OTP Card -->
                <div class="otp-card">
                    <div class="otp-label">
                        <i data-lucide="mail-check" size="18"></i>
                        <span>Email Verification</span>
                    </div>
                    <div class="email-address">
                        ✉️ <?php echo htmlspecialchars($_SESSION['temp_email']); ?>
                    </div>
                    <div class="otp-input-group" id="emailOtpGroup">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="timer-text" id="timerText">⏱️ OTP expires in <span id="countdown">05:00</span></div>
            
            <button type="button" class="btn-register btn-resend" onclick="resendOTP()">
                <i data-lucide="refresh-cw" size="16"></i> Resend OTP
            </button>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn-register" id="submitBtn">
            <span><?php echo isset($show_otp) ? 'Verify & Register' : 'Send OTP & Register'; ?></span>
            <i data-lucide="<?php echo isset($show_otp) ? 'check-circle' : 'arrow-right'; ?>" size="18"></i>
        </button>
    </form>

    <p class="footer-text">Already have an account? <a href="/login.php">Login here</a></p>
</div>

<script>
    // Enhanced icon management system
    let iconRefreshTimeout = null;
    
    function refreshIcons() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
    
    // Debounced icon refresh to avoid multiple calls
    function debouncedIconRefresh() {
        if (iconRefreshTimeout) {
            clearTimeout(iconRefreshTimeout);
        }
        iconRefreshTimeout = setTimeout(refreshIcons, 50);
    }
    
    // Initial icon load
    refreshIcons();
    
    // Observe DOM changes for dynamic content
    const iconObserver = new MutationObserver(function(mutations) {
        let needsRefresh = false;
        
        mutations.forEach(function(mutation) {
            // Check if added nodes contain elements with data-lucide attribute
            if (mutation.type === 'childList' && mutation.addedNodes.length) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        if (node.hasAttribute && node.hasAttribute('data-lucide')) {
                            needsRefresh = true;
                        }
                        if (node.querySelectorAll && node.querySelectorAll('[data-lucide]').length) {
                            needsRefresh = true;
                        }
                    }
                });
            }
            // Check for attribute changes on data-lucide elements
            if (mutation.type === 'attributes' && mutation.attributeName === 'data-lucide') {
                needsRefresh = true;
            }
        });
        
        if (needsRefresh) {
            debouncedIconRefresh();
        }
    });
    
    // Start observing
    iconObserver.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['data-lucide', 'style', 'class']
    });
    
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const submitBtn = document.getElementById('submitBtn');
    const warningDiv = document.getElementById('passwordMatchWarning');
    
    function checkPasswordMatch() {
        if (!passwordInput || !confirmPasswordInput || !warningDiv) return;
        
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        if (confirmPassword.length === 0) {
            warningDiv.innerHTML = '';
            if(submitBtn) submitBtn.disabled = false;
            refreshIcons();
            return;
        }
        
        if (password === confirmPassword) {
            warningDiv.innerHTML = '<i data-lucide="check-circle" size="14"></i> <span>Passwords match!</span>';
            warningDiv.className = 'password-match-warning match-success';
            if(submitBtn) submitBtn.disabled = false;
        } else {
            warningDiv.innerHTML = '<i data-lucide="alert-circle" size="14"></i> <span>Passwords do not match</span>';
            warningDiv.className = 'password-match-warning match-error';
            if(submitBtn) submitBtn.disabled = true;
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

    function getOtpValue(groupId) {
        let otpValue = '';
        const inputs = document.querySelectorAll(`#${groupId} .otp-digit`);
        inputs.forEach(input => {
            otpValue += input.value;
        });
        return otpValue;
    }

    function setupOtpInputs(groupId) {
        const inputs = document.querySelectorAll(`#${groupId} .otp-digit`);
        
        if (!inputs.length) return;
        
        inputs.forEach((input, index) => {
            // Remove existing listeners to avoid duplicates
            const newInput = input.cloneNode(true);
            input.parentNode.replaceChild(newInput, input);
            
            newInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
                if (e.target.value.length === 1 && index < inputs.length - 1) {
                    const nextInput = document.querySelectorAll(`#${groupId} .otp-digit`)[index + 1];
                    if (nextInput) nextInput.focus();
                }
                refreshIcons();
            });
            
            newInput.addEventListener('keydown', (e) => {
                const currentInputs = document.querySelectorAll(`#${groupId} .otp-digit`);
                if (e.key === 'Backspace' && e.target.value.length === 0 && index > 0) {
                    const prevInput = currentInputs[index - 1];
                    if (prevInput) prevInput.focus();
                }
            });
            
            newInput.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').slice(0, 6);
                const pasteDigits = pasteData.replace(/[^0-9]/g, '').split('');
                const currentInputs = document.querySelectorAll(`#${groupId} .otp-digit`);
                
                pasteDigits.forEach((digit, i) => {
                    if (currentInputs[i]) {
                        currentInputs[i].value = digit;
                    }
                });
                
                const lastFilledIndex = Math.min(pasteDigits.length - 1, currentInputs.length - 1);
                if (lastFilledIndex < currentInputs.length - 1 && currentInputs[lastFilledIndex + 1]) {
                    currentInputs[lastFilledIndex + 1].focus();
                } else if (currentInputs[currentInputs.length - 1]) {
                    currentInputs[currentInputs.length - 1].focus();
                }
                refreshIcons();
            });
        });
    }
    
    <?php if(isset($show_otp)): ?>
    <?php if($_SESSION['temp_otp_method'] === 'sms' || $_SESSION['temp_otp_method'] === 'both'): ?>
    setupOtpInputs('smsOtpGroup');
    <?php endif; ?>
    <?php if($_SESSION['temp_otp_method'] === 'email' || $_SESSION['temp_otp_method'] === 'both'): ?>
    setupOtpInputs('emailOtpGroup');
    <?php endif; ?>
    
    let timeLeft = <?php echo max(0, ($_SESSION['otp_expiry'] ?? time()) - time()); ?>;
    const countdownElement = document.getElementById('countdown');
    let countdownInterval = null;
    
    function updateCountdown() {
        if (timeLeft <= 0) {
            if(countdownElement) {
                countdownElement.textContent = 'Expired!';
                countdownElement.style.color = '#ef4444';
            }
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
            refreshIcons();
            return;
        }
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        if(countdownElement) {
            countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        timeLeft--;
    }
    
    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);
    <?php endif; ?>
    
    function resendOTP() {
        const form = document.getElementById('regForm');
        const btn = document.getElementById('submitBtn');
        if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.7';
        }
        form.submit();
    }
    
    const regForm = document.getElementById('regForm');
    if(regForm) {
        regForm.addEventListener('submit', function(e) {
            const mainFields = document.getElementById('mainFields');
            const isOTPPhase = mainFields && mainFields.style.display === 'none';
            
            if (isOTPPhase) {
                const smsOtp = document.getElementById('smsOtpGroup') ? getOtpValue('smsOtpGroup') : '';
                const emailOtp = document.getElementById('emailOtpGroup') ? getOtpValue('emailOtpGroup') : '';
                
                const existingSms = document.querySelector('input[name="sms_otp"]');
                const existingEmail = document.querySelector('input[name="email_otp"]');
                if(existingSms) existingSms.remove();
                if(existingEmail) existingEmail.remove();
                
                if (smsOtp) {
                    const smsHidden = document.createElement('input');
                    smsHidden.type = 'hidden';
                    smsHidden.name = 'sms_otp';
                    smsHidden.value = smsOtp;
                    this.appendChild(smsHidden);
                }
                
                if (emailOtp) {
                    const emailHidden = document.createElement('input');
                    emailHidden.type = 'hidden';
                    emailHidden.name = 'email_otp';
                    emailHidden.value = emailOtp;
                    this.appendChild(emailHidden);
                }
                
                <?php if(isset($_SESSION['temp_otp_method']) && ($_SESSION['temp_otp_method'] === 'sms' || $_SESSION['temp_otp_method'] === 'both')): ?>
                if (smsOtp && smsOtp.length !== 6) {
                    e.preventDefault();
                    alert('Please enter the complete 6-digit SMS OTP code');
                    return false;
                }
                <?php endif; ?>
                
                <?php if(isset($_SESSION['temp_otp_method']) && ($_SESSION['temp_otp_method'] === 'email' || $_SESSION['temp_otp_method'] === 'both')): ?>
                if (emailOtp && emailOtp.length !== 6) {
                    e.preventDefault();
                    alert('Please enter the complete 6-digit Email OTP code');
                    return false;
                }
                <?php endif; ?>
            } else {
                const name = document.getElementById('name') ? document.getElementById('name').value : '';
                const email = document.getElementById('email') ? document.getElementById('email').value : '';
                const contact = document.getElementById('contact') ? document.getElementById('contact').value : '';
                const password = document.getElementById('password') ? document.getElementById('password').value : '';
                const confirmPassword = document.getElementById('confirm_password') ? document.getElementById('confirm_password').value : '';
                const otpMethod = document.querySelector('input[name="otp_method"]:checked');
                
                if (!name || !email || !contact || !password || !confirmPassword) {
                    e.preventDefault();
                    alert('Please fill in all fields');
                    return false;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match');
                    return false;
                }
                
                if (!otpMethod) {
                    e.preventDefault();
                    alert('Please select an OTP verification method');
                    return false;
                }
            }
            
            const btn = document.getElementById('submitBtn');
            if(btn && btn.querySelector('span')) {
                const span = btn.querySelector('span');
                const isOTPPhase = document.getElementById('mainFields') && document.getElementById('mainFields').style.display === 'none';
                span.innerText = isOTPPhase ? 'Verifying...' : 'Sending OTP...';
                btn.style.opacity = '0.7';
                btn.disabled = true;
            }
            refreshIcons();
        });
    }
    
    // Fix for dynamic method selection icons
    const methodRadios = document.querySelectorAll('input[name="otp_method"]');
    if (methodRadios.length) {
        methodRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                setTimeout(refreshIcons, 50);
            });
        });
    }
    
    // Fix for any dynamically added content
    window.addEventListener('load', function() {
        setTimeout(refreshIcons, 100);
        // Re-run after all resources are loaded
        if (document.readyState === 'complete') {
            refreshIcons();
        }
    });
    
    // Fix for window resize (affects responsive layouts)
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(refreshIcons, 100);
    });
    
    // Manual refresh function that can be called from anywhere
    window.manualIconRefresh = refreshIcons;
    
    // Log for debugging (optional - remove in production)
    console.log('Icon management system initialized');
</script>
</body>
</html>