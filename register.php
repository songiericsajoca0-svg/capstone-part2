<?php

require_once 'includes/config.php';

// PHPMailer Integration
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Change this path based on your PHPMailer location
// If via Composer: require 'vendor/autoload.php';
// If manual download, require these 3 files:
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $contact  = trim($_POST['contact']);
    $password_raw = $_POST['password']; // Raw pass to pass through hidden field
    $otp_input = $_POST['otp'] ?? '';

    // PHASE 1: Send the Actual OTP
    if (empty($otp_input)) {
        // Check if email is already registered
        $checkEmail = $conn->prepare("SELECT email FROM users WHERE email = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        $result = $checkEmail->get_result();

        if ($result->num_rows > 0) {
            $error = "This email address is already registered.";
        } else {
            $otp_code = rand(100000, 999999);
            $_SESSION['temp_otp'] = $otp_code;
            $_SESSION['otp_expiry'] = time() + 300; // 5 mins validity

            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'songiericsajoca0@gmail.com'; 
                $mail->Password   = 'xlqo yuis ayfz oply'; // Your App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Recipients
                $mail->setFrom('songiericsajoca0@gmail.com', 'GoTrike Support');
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'GoTrike Registration OTP';
                $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; border: 1px solid #ddd; padding: 20px; border-radius: 10px;'>
                        <h2 style='color: #1e40af;'>Welcome to GoTrike!</h2>
                        <p>Hi <b>$name</b>, use the code below to complete your registration:</p>
                        <h1 style='background: #f3f4f6; padding: 10px; text-align: center; letter-spacing: 5px; color: #1e40af;'>$otp_code</h1>
                        <p style='font-size: 12px; color: #666;'>This code is only valid for 5 minutes.</p>
                    </div>";

                $mail->send();
                $show_otp = true;
                $msg_otp = "OTP has been successfully sent to $email";
            } catch (Exception $e) {
                $error = "Failed to send OTP. Mailer Error: {$mail->ErrorInfo}";
            }
        }
    } 
    // PHASE 2: Verify OTP and Registration
    else {
        if (isset($_SESSION['temp_otp']) && $otp_input == $_SESSION['temp_otp'] && time() <= $_SESSION['otp_expiry']) {
            $hashed_password = password_hash($password_raw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, contact, password, role) VALUES (?, ?, ?, ?, 'passenger')");
            $stmt->bind_param("ssss", $name, $email, $contact, $hashed_password);

            if ($stmt->execute()) {
                unset($_SESSION['temp_otp']);
                unset($_SESSION['otp_expiry']);
                header("Location: login.php?msg=Registration successful! Please login.");
                exit;
            } else {
                $error = "An error occurred while saving your account.";
            }
        } else {
            $error = "Invalid OTP code or it has expired. Please try again.";
            $show_otp = true; // Keep the OTP screen visible
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register | GoTrike</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  
  <style>
    :root {
      --primary: #1e40af; --secondary: #3b82f6; --text-main: #1e293b;
      --text-muted: #64748b; --error: #ef4444; --success: #10b981; --border: #e2e8f0;
      --bg-gradient: linear-gradient(135deg, #0ea5e9 0%, #ffffff 50%, #1e3a8a 100%);
    }
    * { box-sizing: border-box; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg-gradient); background-attachment: fixed;
      margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px;
    }
    .register-card {
      background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
      padding: 2.5rem; border-radius: 28px; border: 1px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15); width: 100%; max-width: 480px;
      animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
      text-align: center; /* Ensures header and text are centered */
    }
    @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    .back-home { display: inline-flex; align-items: center; gap: 6px; text-decoration: none; color: var(--text-muted); font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem; }
    h2 { color: var(--text-main); font-weight: 800; font-size: 1.8rem; margin: 0; letter-spacing: -0.05em; text-align: center; }
    .subtitle { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 2rem; text-align: center; }
    .alert { padding: 0.8rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; background: #fef2f2; color: var(--error); border: 1px solid #fee2e2; text-align: left; }
    .alert-info { background: #f0fdf4; color: var(--success); border: 1px solid #dcfce7; }
    .form-grid { display: grid; gap: 1.2rem; text-align: left; }
    label { display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-main); }
    .input-wrapper { position: relative; display: flex; align-items: center; }
    .input-wrapper i.icon-left { position: absolute; left: 14px; color: var(--text-muted); width: 18px; }
    .eye-toggle { position: absolute; right: 14px; cursor: pointer; color: var(--text-muted); background: none; border: none; padding: 0; display: flex; align-items: center; }
    input { width: 100%; padding: 12px 16px 12px 44px; border: 1.5px solid var(--border); border-radius: 12px; font-size: 0.95rem; transition: all 0.3s; background: #f8fafc; }
    input:focus { outline: none; border-color: var(--secondary); background: #fff; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15); }
    .otp-section { background: #f1f5f9; padding: 1.5rem; border-radius: 16px; border: 2px dashed var(--secondary); margin-top: 1rem; text-align: center; }
    .btn-register { width: 100%; padding: 14px; background: var(--primary); color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 1.5rem; }
    .btn-register:hover { background: #111827; transform: translateY(-2px); }
    .footer-text { margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-muted); text-align: center; }
    .footer-text a { color: var(--secondary); text-decoration: none; font-weight: 700; }
  </style>
</head>
<body>

<div class="register-card">
    <a href="index.php" class="back-home"><i data-lucide="chevron-left" size="18"></i> Back to Home</a>
    <h2>Create Account</h2>
    <p class="subtitle">Join GoTrike and start your journey.</p>

    <?php if(isset($error)): ?>
        <div class="alert"><i data-lucide="alert-circle" size="18"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if(isset($msg_otp)): ?>
        <div class="alert alert-info"><i data-lucide="shield-check" size="18"></i> <?php echo $msg_otp; ?></div>
    <?php endif; ?>

    <form method="POST" id="regForm">
        <div id="mainFields" <?php echo isset($show_otp) ? 'style="display:none;"' : ''; ?>>
            <div class="form-grid">
                <div>
                    <label>Full Name</label>
                    <div class="input-wrapper">
                        <i data-lucide="user" class="icon-left"></i>
                        <input type="text" name="name" value="<?php echo $_POST['name'] ?? ''; ?>" placeholder="John Doe" required>
                    </div>
                </div>
                <div>
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i data-lucide="mail" class="icon-left"></i>
                        <input type="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" placeholder="name@email.com" required>
                    </div>
                </div>
                <div>
                    <label>Contact Number</label>
                    <div class="input-wrapper">
                        <i data-lucide="phone" class="icon-left"></i>
                        <input type="text" name="contact" value="<?php echo $_POST['contact'] ?? ''; ?>" placeholder="09123456789" required>
                    </div>
                </div>
                <div>
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i data-lucide="lock" class="icon-left"></i>
                        <input type="password" id="password" name="password" value="<?php echo $_POST['password'] ?? ''; ?>" placeholder="••••••••" required>
                        <button type="button" class="eye-toggle" onclick="togglePassword()"><i data-lucide="eye" id="eyeIcon" size="18"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <?php if(isset($show_otp)): ?>
            <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <input type="hidden" name="contact" value="<?php echo htmlspecialchars($contact); ?>">
            <input type="hidden" name="password" value="<?php echo htmlspecialchars($password_raw); ?>">

            <div class="otp-section">
                <label>Enter 6-Digit OTP</label>
                <div class="input-wrapper">
                    <i data-lucide="key-round" class="icon-left"></i>
                    <input type="text" name="otp" maxlength="6" placeholder="000000" style="text-align: center; letter-spacing: 8px; font-size: 1.2rem;" required autofocus>
                </div>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn-register" id="submitBtn">
            <span><?php echo isset($show_otp) ? 'Verify & Register' : 'Get OTP Code'; ?></span>
            <i data-lucide="<?php echo isset($show_otp) ? 'check-circle' : 'arrow-right'; ?>" size="18"></i>
        </button>
    </form>

    <p class="footer-text">Already have an account? <a href="login.php">Login here</a></p>
</div>

<script>
    lucide.createIcons();
    function togglePassword() {
        const p = document.getElementById('password');
        const i = document.getElementById('eyeIcon');
        p.type = p.type === 'password' ? 'text' : 'password';
        i.setAttribute('data-lucide', p.type === 'password' ? 'eye' : 'eye-off');
        lucide.createIcons();
    }
    document.getElementById('regForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.querySelector('span').innerText = 'Sending...';
        btn.style.opacity = '0.7';
        btn.style.pointerEvents = 'none';
    });
</script>
</body>
</html>