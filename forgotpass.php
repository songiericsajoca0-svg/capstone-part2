<?php
// 1. Database Connection
require_once 'includes/config.php';

// 2. Session Management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$error    = "";
$msg_otp  = "";
$show_otp_form = false;
$email    = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email       = trim($_POST['email'] ?? '');
    $otp_input   = trim($_POST['otp'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $action      = $_POST['action'] ?? '';

    // PHASE 1: Send OTP
    if ($action === 'send_otp' && !empty($email)) {
        // Check if email exists in database
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = "This email address is not registered.";
        } else {
            // Generate 6-digit OTP
            $otp_code = rand(100000, 999999);
            
            // Store in session
            $_SESSION['reset_otp']         = $otp_code;
            $_SESSION['reset_email_target'] = $email;
            $_SESSION['otp_expiry']        = time() + 300; // 5 minutes

            // Send email using PHPMailer
            $mail = new PHPMailer(true);

            try {
                // SMTP Configuration
                $mail->SMTPDebug = SMTP::DEBUG_OFF;
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'songiericsajoca0@gmail.com';
                // ⚠️ REPLACE WITH YOUR ACTUAL GMAIL APP PASSWORD ⚠️
                $mail->Password   = 'xlqoyuisayfzoply'; // <--- CHANGE THIS!
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Disable SSL verification for localhost
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    ]
                ];

                // Email content
                $mail->setFrom('songiericsajoca0@gmail.com', 'GoTrike Support');
                $mail->addAddress($email);
                $mail->addReplyTo('songiericsajoca0@gmail.com', 'GoTrike Support');

                $mail->isHTML(true);
                $mail->Subject = 'GoTrike Password Reset OTP';
                $mail->Body    = '
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <title>Password Reset OTP</title>
                        <style>
                            body { font-family: Arial, sans-serif; }
                            .container { max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; }
                            .header { background: #1e40af; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                            .code { font-size: 32px; letter-spacing: 5px; background: #f0f0f0; padding: 15px; text-align: center; font-weight: bold; margin: 20px 0; }
                            .footer { font-size: 12px; color: #888; text-align: center; margin-top: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="header">
                                <h2>GoTrike Password Reset</h2>
                            </div>
                            <p>Hello,</p>
                            <p>You requested to reset your password. Use the OTP code below:</p>
                            <div class="code">' . $otp_code . '</div>
                            <p>This code will expire in <strong>5 minutes</strong>.</p>
                            <p>If you did not request this, please ignore this email.</p>
                            <div class="footer">
                                <p>&copy; ' . date('Y') . ' GoTrike. All rights reserved.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ';
                
                $mail->AltBody = "GoTrike Password Reset OTP\n\nYour OTP code is: $otp_code\n\nThis code expires in 5 minutes.\n\nIf you did not request this, please ignore this email.";

                $mail->send();
                $show_otp_form = true;
                $msg_otp = "OTP sent successfully to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox or spam folder.";
            } catch (Exception $e) {
                $error = "Failed to send OTP. Error: " . $mail->ErrorInfo;
            }
        }
    }
    // PHASE 2: Verify OTP + Reset Password
    elseif ($action === 'reset_password' && !empty($otp_input)) {
        // Validate new password
        if (empty($new_password)) {
            $error = "Please enter a new password.";
            $show_otp_form = true;
            $email = $_SESSION['reset_email_target'] ?? '';
        }
        elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long.";
            $show_otp_form = true;
            $email = $_SESSION['reset_email_target'] ?? '';
        }
        // Verify OTP
        elseif (!isset($_SESSION['reset_otp']) || !isset($_SESSION['reset_email_target']) || !isset($_SESSION['otp_expiry'])) {
            $error = "Session expired. Please request a new OTP.";
            $show_otp_form = false;
        }
        elseif (time() > $_SESSION['otp_expiry']) {
            $error = "OTP has expired. Please request a new code.";
            $show_otp_form = false;
            // Clear expired session
            unset($_SESSION['reset_otp'], $_SESSION['reset_email_target'], $_SESSION['otp_expiry']);
        }
        elseif ($otp_input !== (string)$_SESSION['reset_otp']) {
            $error = "Invalid OTP. Please try again.";
            $show_otp_form = true;
            $email = $_SESSION['reset_email_target'] ?? '';
        }
        else {
            // All validations passed - update password
            $target_email = $_SESSION['reset_email_target'];
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $target_email);

            if ($stmt->execute()) {
                // Clear session data
                unset($_SESSION['reset_otp'], $_SESSION['reset_email_target'], $_SESSION['otp_expiry']);
                // Redirect to login page with success message
                header("Location: login.php?msg=" . urlencode("Password reset successful! Please login with your new password."));
                exit;
            } else {
                $error = "Database error. Please try again.";
                $show_otp_form = true;
                $email = $_SESSION['reset_email_target'] ?? '';
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
    <title>Forgot Password | GoTrike</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: linear-gradient(135deg, #0ea5e9 0%, #ffffff 50%, #1e3a8a 100%); 
            background-attachment: fixed; 
        }
        .glass-container { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(10px); 
            border: 1px solid rgba(255, 255, 255, 0.3); 
            border-radius: 2rem; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); 
        }
        input:focus {
            outline: none;
            ring: 2px solid #3b82f6;
        }
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-md p-8 md:p-10 glass-container text-center">

        <a href="login.php" class="inline-flex items-center gap-2 text-slate-400 hover:text-blue-600 text-sm font-semibold transition mb-6">
            <i data-lucide="chevron-left" size="18"></i> Back to Login
        </a>

        <h2 class="text-3xl font-extrabold text-slate-800 tracking-tight">Reset Password</h2>
        <p class="text-slate-500 text-sm mt-2 mb-8">Secure your GoTrike account</p>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl mb-6 text-sm flex items-center gap-2">
                <i data-lucide="alert-circle" size="18"></i> 
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($msg_otp): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 p-4 rounded-xl mb-6 text-sm flex items-center gap-2">
                <i data-lucide="shield-check" size="18"></i> 
                <span><?= $msg_otp ?></span>
            </div>
        <?php endif; ?>

        <!-- Phase 1: Email Input -->
        <form method="POST" id="phase1Form" class="space-y-5" <?= $show_otp_form ? 'style="display:none;"' : '' ?>>
            <div class="text-left">
                <label class="block text-xs font-bold uppercase text-slate-400 mb-2 ml-1 tracking-widest">Registered Email</label>
                <div class="relative">
                    <i data-lucide="mail" class="absolute left-4 top-3.5 text-slate-400" size="18"></i>
                    <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required 
                           placeholder="name@example.com"
                           class="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>
            </div>
            <input type="hidden" name="action" value="send_otp">
            <button type="submit" id="sendOtpBtn"
                    class="w-full py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-2xl shadow-lg transition transform hover:-translate-y-1 duration-200">
                Send OTP Code
            </button>
        </form>

        <!-- Phase 2: OTP and New Password -->
        <form method="POST" id="phase2Form" class="space-y-6" <?= !$show_otp_form ? 'style="display:none;"' : '' ?>>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
            
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-2xl border-2 border-blue-200">
                <label class="block text-center text-xs font-bold uppercase text-blue-600 mb-3 tracking-widest">Verification Code</label>
                <input type="text" name="otp" id="otpInput" maxlength="6" required placeholder="000000" autocomplete="off"
                       class="w-full text-center text-3xl tracking-[0.8rem] font-black bg-white rounded-xl border-2 border-blue-200 focus:border-blue-500 outline-none py-4 text-blue-600">
                <p class="text-xs text-center text-slate-500 mt-3">Enter the 6-digit code sent to your email</p>
            </div>

            <div class="text-left">
                <label class="block text-xs font-bold uppercase text-slate-400 mb-2 ml-1 tracking-widest">New Password</label>
                <div class="relative">
                    <i data-lucide="lock" class="absolute left-4 top-3.5 text-slate-400" size="18"></i>
                    <input type="password" name="new_password" id="newPassword" required placeholder="••••••••" minlength="6"
                           class="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>
                <p class="text-xs text-slate-400 mt-2 ml-1">Password must be at least 6 characters</p>
            </div>

            <button type="submit" id="resetPasswordBtn"
                    class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-2xl shadow-lg transition transform hover:-translate-y-1 duration-200">
                Reset Password
            </button>
            
            <button type="button" onclick="window.location.href='forgot-password.php'"
                    class="w-full py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-2xl transition text-sm">
                Request New OTP
            </button>
        </form>
        
        <div class="mt-8 pt-6 border-t border-gray-200">
            <p class="text-xs text-slate-400">
                <i data-lucide="shield" size="12" class="inline"></i> 
                Your password is encrypted and secure
            </p>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Handle Phase 1 Form Submission (Send OTP)
        document.getElementById('phase1Form')?.addEventListener('submit', function(e) {
            const sendBtn = document.getElementById('sendOtpBtn');
            if (sendBtn) {
                sendBtn.innerHTML = 'Sending...';
                sendBtn.disabled = true;
            }
        });
        
        // Handle Phase 2 Form Submission (Reset Password)
        document.getElementById('phase2Form')?.addEventListener('submit', function(e) {
            const resetBtn = document.getElementById('resetPasswordBtn');
            const otpValue = document.getElementById('otpInput')?.value;
            const passwordValue = document.getElementById('newPassword')?.value;
            
            // Validate OTP
            if (!otpValue || otpValue.length !== 6) {
                e.preventDefault();
                alert('Please enter the 6-digit OTP code');
                return false;
            }
            
            // Validate password
            if (!passwordValue || passwordValue.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return false;
            }
            
            // Disable button and show loading state
            if (resetBtn) {
                resetBtn.innerHTML = 'Resetting Password...';
                resetBtn.disabled = true;
            }
        });
        
        // Auto-focus OTP input when phase 2 is visible
        <?php if ($show_otp_form): ?>
        setTimeout(function() {
            const otpInput = document.getElementById('otpInput');
            if (otpInput) {
                otpInput.focus();
            }
        }, 100);
        <?php endif; ?>
        
        // Restrict OTP input to numbers only
        const otpField = document.getElementById('otpInput');
        if (otpField) {
            otpField.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
        }
        
        // Enable Enter key to submit forms
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const form = this.closest('form');
                    if (form) {
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn && !submitBtn.disabled) {
                            submitBtn.click();
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>