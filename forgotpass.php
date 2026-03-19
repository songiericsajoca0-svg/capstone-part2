<?php
// 1. Database Connection
require_once 'includes/config.php';

// 2. Session Management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$error    = "";
$msg_otp  = "";
$show_otp_form = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email       = trim($_POST['email'] ?? '');
    $otp_input   = trim($_POST['otp'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    // PHASE 1: Send OTP
    if (empty($otp_input) && !empty($email)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = "This email address is not registered.";
        } else {
            $otp_code = rand(100000, 999999);
            $_SESSION['reset_otp']         = $otp_code;
            $_SESSION['reset_email_target'] = $email;
            $_SESSION['otp_expiry']        = time() + 300; // 5 minutes

            $mail = new PHPMailer(true);

            try {
                // ────────────────────────────────────────────────
                //           VERY IMPORTANT ─ 2025/2026 SETTINGS
                // ────────────────────────────────────────────────
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'songiericsajoca0@gmail.com';           // ← your gmail
                $mail->Password   = 'xxxxxxxxxxxxxxxx';                     // ← 16-char APP PASSWORD (NO SPACES!)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Only needed for self-signed certs / old XAMPP (usually safe to keep)
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    ]
                ];

                // For debugging (remove/comment after it works)
                // $mail->SMTPDebug = 2;  // 0 = off, 2 = verbose (see exact SMTP conversation)

                $mail->setFrom('songiericsajoca0@gmail.com', 'GoTrike Support');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'GoTrike Password Reset OTP';
                $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; border: 1px solid #ddd; padding: 25px; border-radius: 15px; max-width: 500px;'>
                        <h2 style='color: #1e40af;'>Password Recovery</h2>
                        <p style='color: #444;'>Use this code to reset your password:</p>
                        <div style='background: #f8fafc; padding: 15px; text-align: center; border: 1px solid #e2e8f0; border-radius: 10px;'>
                            <h1 style='letter-spacing: 12px; color: #1e40af; margin: 0;'>$otp_code</h1>
                        </div>
                        <p style='font-size: 12px; color: #888; margin-top: 20px;'>
                            Code expires in 5 minutes. If this wasn't you, ignore this email.
                        </p>
                    </div>";

                $mail->send();

                $show_otp_form = true;
                $msg_otp = "OTP sent to <strong>$email</strong>. Check spam/junk folder too.";
            } catch (Exception $e) {
                $error = "Failed to send OTP.<br><strong>Error:</strong> " . $mail->ErrorInfo;
                // When debugging: also show $e->getMessage() if needed
            }
        }
    }

    // PHASE 2: Verify OTP + Reset Password
    elseif (!empty($otp_input)) {
        if (isset($_SESSION['reset_otp'], $_SESSION['reset_email_target'], $_SESSION['otp_expiry']) &&
            $otp_input === (string)$_SESSION['reset_otp'] &&
            time() <= $_SESSION['otp_expiry']) {

            $target_email = $_SESSION['reset_email_target'];
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed, $target_email);

            if ($stmt->execute()) {
                // Clean session
                unset($_SESSION['reset_otp'], $_SESSION['reset_email_target'], $_SESSION['otp_expiry']);
                header("Location: login.php?msg=Password+reset+successful.+Please+login.");
                exit;
            } else {
                $error = "Database error. Please try again.";
                $show_otp_form = true;
            }
        } else {
            $error = "Invalid or expired OTP.";
            $show_otp_form = true;
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
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: linear-gradient(135deg, #0ea5e9 0%, #ffffff 50%, #1e3a8a 100%); background-attachment: fixed; }
        .glass-container { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); }
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
                <i data-lucide="alert-circle" size="18"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($msg_otp): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 p-4 rounded-xl mb-6 text-sm flex items-center gap-2">
                <i data-lucide="shield-check" size="18"></i> <?= $msg_otp ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="recoveryForm">

            <div class="space-y-5" <?= $show_otp_form ? 'style="display:none;"' : '' ?>>
                <div class="text-left">
                    <label class="block text-xs font-bold uppercase text-slate-400 mb-2 ml-1 tracking-widest">Registered Email</label>
                    <div class="relative">
                        <i data-lucide="mail" class="absolute left-4 top-3.5 text-slate-400" size="18"></i>
                        <input type="email" name="email" required placeholder="name@email.com"
                               class="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none transition">
                    </div>
                </div>
                <button type="submit" id="mainBtn"
                        class="w-full py-4 bg-blue-600 hover:bg-slate-900 text-white font-bold rounded-2xl shadow-lg transition transform hover:-translate-y-1">
                    Send OTP Code
                </button>
            </div>

            <?php if ($show_otp_form): ?>
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

                <div class="space-y-6">
                    <div class="bg-slate-50 p-6 rounded-2xl border-2 border-dashed border-blue-200">
                        <label class="block text-center text-xs font-bold uppercase text-slate-400 mb-3 tracking-widest">Verification Code</label>
                        <input type="text" name="otp" maxlength="6" required placeholder="000000"
                               class="w-full text-center text-3xl tracking-[0.8rem] font-black bg-transparent outline-none text-blue-600">
                    </div>

                    <div class="text-left">
                        <label class="block text-xs font-bold uppercase text-slate-400 mb-2 ml-1 tracking-widest">New Password</label>
                        <input type="password" name="new_password" required placeholder="••••••••"
                               class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <button type="submit"
                            class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-2xl shadow-lg transition transform hover:-translate-y-1">
                        Reset Password
                    </button>
                </div>
            <?php endif; ?>

        </form>
    </div>

    <script>
        lucide.createIcons();
        document.getElementById('recoveryForm')?.addEventListener('submit', e => {
            const btn = document.getElementById('mainBtn');
            if (btn) {
                btn.innerText = 'Processing...';
                btn.style.opacity = '0.7';
                btn.disabled = true;
            }
        });
    </script>
</body>
</html>