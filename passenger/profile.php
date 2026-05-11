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

$user_id = $_SESSION['user_id'];

// Function to refresh user data
function refreshUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT name, contact, email, profile, email_verified, phone_verified FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Fetch User Data
$user = refreshUserData($conn, $user_id);

$success = $error = "";
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
        $mail->Subject = 'GoTrike Email Verification OTP';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px;'>
                <h2 style='color: #1e40af;'>GoTrike Email Verification</h2>
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

// Handle AJAX verification
if (isset($_POST['ajax_verify'])) {
    header('Content-Type: application/json');
    $otp_input = trim($_POST['otp_code'] ?? '');
    $verify_type = $_POST['verify_type'] ?? '';
    
    $response = ['success' => false, 'message' => ''];
    
    if (!isset($_SESSION['temp_verify_otp'])) {
        $response['message'] = "No active OTP found. Please request a new one.";
    } elseif (time() > $_SESSION['temp_verify_expiry']) {
        $response['message'] = "OTP has expired. Please request a new one.";
        unset($_SESSION['temp_verify_otp']);
    } elseif ($otp_input != $_SESSION['temp_verify_otp']) {
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
        $up->bind_param("i", $user_id);
        
        if ($up->execute()) {
            $response['success'] = true;
            $response['message'] = $response['message'];
            unset($_SESSION['temp_verify_otp'], $_SESSION['temp_verify_type']);
        } else {
            $response['message'] = "Failed to verify. Please try again.";
        }
        $up->close();
    }
    
    echo json_encode($response);
    exit;
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_verify'])) {
    $action = $_POST['action'] ?? '';
    
    // Update Profile Info
    if ($action === 'profile') {
        $name = trim($_POST['name']);
        $contact = trim($_POST['contact'] ?? '');
        $profile_image = $user['profile'];
        
        // Handle Image Upload
        if (!empty($_POST['image_base64'])) {
            $data = $_POST['image_base64'];
            list($type, $data) = explode(';', $data);
            list(, $data) = explode(',', $data);
            $data = base64_decode($data);
            $file_name = 'profile_' . $user_id . '_' . time() . '.png';
            file_put_contents('../uploads/' . $file_name, $data);
            $profile_image = $file_name;
        } elseif (isset($_FILES['profile_file']) && $_FILES['profile_file']['error'] === 0) {
            $ext = pathinfo($_FILES['profile_file']['name'], PATHINFO_EXTENSION);
            $file_name = 'profile_' . $user_id . '_' . time() . '.' . $ext;
            $target = '../uploads/' . $file_name;
            if (move_uploaded_file($_FILES['profile_file']['tmp_name'], $target)) {
                $profile_image = $file_name;
            }
        }
        
        $up = $conn->prepare("UPDATE users SET name = ?, contact = ?, profile = ? WHERE id = ?");
        $up->bind_param("sssi", $name, $contact, $profile_image, $user_id);
        
        if ($up->execute()) {
            $_SESSION['name'] = $name;
            $success = "Profile updated successfully!";
            $user = refreshUserData($conn, $user_id);
        } else {
            $error = "Update failed. Please try again.";
        }
    }
    
    // Send Email Verification OTP
    elseif ($action === 'send_email_otp') {
        $otp_code = rand(100000, 999999);
        $_SESSION['temp_verify_otp'] = $otp_code;
        $_SESSION['temp_verify_expiry'] = time() + 300;
        $_SESSION['temp_verify_type'] = 'email';
        
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
            $_SESSION['temp_verify_otp'] = $otp_code;
            $_SESSION['temp_verify_expiry'] = time() + 300;
            $_SESSION['temp_verify_type'] = 'phone';
            
            // Format phone number for SMS
            $phone = $user['contact'];
            $phone = preg_replace('/[^0-9]/', '', $phone);
            
            if (strlen($phone) == 10 && substr($phone, 0, 1) == '9') {
                $phone = '63' . $phone;
            } elseif (strlen($phone) == 11 && substr($phone, 0, 2) == '09') {
                $phone = '63' . substr($phone, 1);
            }
            $phone_international = '+' . $phone;
            
            if (function_exists('sendSMS') && sendSMS($phone_international, "GoTrike Verification: Your OTP is $otp_code. Valid for 5 minutes.")) {
                $step = 'verify_otp';
                $otp_success = "Verification code sent to your phone number: " . substr($phone_international, -10);
            } else {
                $error = "Failed to send SMS verification.";
            }
        }
    }
    
    // Resend OTP
    elseif ($action === 'resend_otp') {
        $verify_type = $_SESSION['temp_verify_type'] ?? '';
        $otp_code = rand(100000, 999999);
        $_SESSION['temp_verify_otp'] = $otp_code;
        $_SESSION['temp_verify_expiry'] = time() + 300;
        
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
            
            if (function_exists('sendSMS') && sendSMS($phone_international, "GoTrike Verification: Your OTP is $otp_code. Valid for 5 minutes.")) {
                $otp_success = "New verification code sent to your phone.";
                $step = 'verify_otp';
            } else {
                $otp_error = "Failed to resend SMS verification.";
            }
        }
    }
}
?>

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

    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
    }

    .profile-container {
        max-width: 900px;
        margin: 2rem auto;
        padding: 0 1rem;
    }

    .profile-card {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1);
    }

    .profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem;
        color: white;
    }

    .profile-header h1 {
        font-size: 2rem;
        margin: 0;
    }

    .profile-header p {
        margin: 0.5rem 0 0;
        opacity: 0.9;
    }

    .alert {
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: #d1fae5;
        border-left: 4px solid #10b981;
        color: #065f46;
    }

    .alert-error {
        background: #fee2e2;
        border-left: 4px solid #ef4444;
        color: #991b1b;
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
        padding: 0.5rem 1rem;
        border-radius: 10px;
        cursor: pointer;
        font-size: 0.8rem;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .btn-verify:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(16,185,129,0.3);
    }

    .photo-section {
        padding: 2rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        border-bottom: 1px solid #e5e7eb;
    }

    .profile-preview {
        width: 150px;
        height: 150px;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid white;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
    }

    .photo-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
        flex-wrap: wrap;
        justify-content: center;
    }

    .btn-photo {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: bold;
        cursor: pointer;
        border: none;
        font-size: 0.9rem;
    }

    .btn-upload {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-camera {
        background: #f3f4f6;
        color: #374151;
        border: 2px solid #e5e7eb;
    }

    .camera-container {
        background: #1f2937;
        border-radius: 20px;
        padding: 1.5rem;
        margin-top: 1rem;
    }

    .camera-container video {
        max-width: 100%;
        border-radius: 12px;
    }

    .camera-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 1rem;
    }

    .btn-capture {
        background: #10b981;
        color: white;
    }

    .btn-cancel {
        background: #6b7280;
        color: white;
    }

    .form-section {
        padding: 2rem;
    }

    .info-card {
        background: #f9fafb;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid #e5e7eb;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: bold;
        color: #374151;
    }

    .info-value {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        color: #6b7280;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: bold;
        color: #374151;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 1rem;
        background: white;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
    }

    .btn-submit {
        width: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 1rem;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: bold;
        cursor: pointer;
        margin-top: 1rem;
    }

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

    hr {
        margin: 1.5rem 0;
        border-top: 2px solid #e5e7eb;
    }

    @media (max-width: 768px) {
        .info-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .otp-digit {
            width: 45px;
            height: 55px;
            font-size: 1.4rem;
        }
    }
</style>

<div class="profile-container">
    <div class="profile-card">
        <div class="profile-header">
            <h1>👤 My Profile</h1>
            <p>Manage your personal information and account security</p>
        </div>

        <?php if ($success): ?>
            <div style="padding: 0 2rem; padding-top: 1.5rem;">
                <div class="alert alert-success">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="padding: 0 2rem; padding-top: 1.5rem;">
                <div class="alert alert-error">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($step === 'verify_otp'): ?>
            <div style="padding: 2rem;">
                <div class="otp-section">
                    <h3 style="color: #1e40af; margin-bottom: 1rem;">
                        <?= $_SESSION['temp_verify_type'] === 'email' ? '📧 Verify Your Email Address' : '📱 Verify Your Phone Number' ?>
                    </h3>
                    <p style="color: #64748b; margin-bottom: 1rem;">
                        Enter the 6-digit verification code sent to your 
                        <?= $_SESSION['temp_verify_type'] === 'email' ? 'email address' : 'phone number' ?>
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
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data" id="profileForm">
                <input type="hidden" name="action" value="profile">
                
                <div class="photo-section">
                    <?php 
                        $img_path = !empty($user['profile']) ? '../uploads/' . $user['profile'] : '../assets/default-avatar.png';
                    ?>
                    <img src="<?= $img_path ?>" id="img-preview" class="profile-preview" alt="Profile Preview">

                    <div class="photo-actions">
                        <label class="btn-photo btn-upload">
                            📸 Upload Photo
                            <input type="file" name="profile_file" id="file-input" accept="image/*" style="display: none;">
                        </label>
                        <button type="button" class="btn-photo btn-camera" onclick="startCamera()">
                            📷 Use Camera
                        </button>
                    </div>
                </div>

                <div id="camera-container" class="camera-container" style="display: none;">
                    <video id="video" autoplay playsinline style="width: 100%; border-radius: 12px;"></video>
                    <canvas id="canvas" style="display: none;"></canvas>
                    <div class="camera-buttons">
                        <button type="button" class="btn-photo btn-capture" onclick="takeSnapshot()">
                            📸 Capture
                        </button>
                        <button type="button" class="btn-photo btn-cancel" onclick="stopCamera()">
                            ❌ Cancel
                        </button>
                    </div>
                </div>

                <input type="hidden" name="image_base64" id="image_base64">

                <div class="form-section">
                    <div class="info-card">
                        <div class="info-row">
                            <div class="info-label">
                                <span>📧</span> Email Address
                            </div>
                            <div class="info-value">
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
                        
                        <div class="info-row">
                            <div class="info-label">
                                <span>📞</span> Phone Number
                            </div>
                            <div class="info-value">
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

                    <div class="form-group">
                        <label>👤 Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label>📞 Phone Number (for updates)</label>
                        <input type="text" name="contact" value="<?= htmlspecialchars($user['contact'] ?? '') ?>" placeholder="09123456789" class="form-control">
                        <small>Enter your phone number to receive SMS notifications</small>
                    </div>

                    <hr>

                    <button type="submit" class="btn-submit">
                        💾 Save Changes
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const cameraContainer = document.getElementById('camera-container');
const imgPreview = document.getElementById('img-preview');
const imageBase64Input = document.getElementById('image_base64');
let stream = null;

document.getElementById('file-input').onchange = function (evt) {
    const [file] = this.files;
    if (file) {
        imgPreview.src = URL.createObjectURL(file);
        imageBase64Input.value = "";
    }
};

async function startCamera() {
    cameraContainer.style.display = 'block';
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;
    } catch (err) {
        alert("Camera access error: " + err.message);
        cameraContainer.style.display = 'none';
    }
}

function stopCamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
    cameraContainer.style.display = 'none';
}

function takeSnapshot() {
    const context = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    const imageData = canvas.toDataURL('image/png');
    imgPreview.src = imageData;
    imageBase64Input.value = imageData;
    
    stopCamera();
}

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
    
    const verifyType = '<?= $_SESSION['temp_verify_type'] ?? 'phone' ?>';
    
    // Show loading
    const verifyBtn = document.getElementById('verifyBtn');
    const originalText = verifyBtn.innerHTML;
    verifyBtn.innerHTML = '⏳ Verifying...';
    verifyBtn.disabled = true;
    
    // Send AJAX request
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
            window.location.href = 'profile.php?verified=1';
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
if (isset($_SESSION['temp_verify_expiry'])) {
    $expiry = $_SESSION['temp_verify_expiry'];
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

<?php if (isset($_GET['verified'])): ?>
    setTimeout(function() {
        window.location.href = 'profile.php';
    }, 1000);
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>