<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

$user_id = $_SESSION['user_id'];

// 1. Fetch User Data
$stmt = $conn->prepare("SELECT name, contact, email, profile FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$success = $error = "";

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $profile_image = $user['profile']; // Default sa dating image

    // Handle Image Upload (File or Camera)
    if (!empty($_POST['image_base64'])) {
        // Galing sa Camera (Base64)
        $data = $_POST['image_base64'];
        list($type, $data) = explode(';', $data);
        list(, $data)      = explode(',', $data);
        $data = base64_decode($data);
        $file_name = 'profile_' . $user_id . '_' . time() . '.png';
        file_put_contents('../uploads/' . $file_name, $data);
        $profile_image = $file_name;
    } elseif (isset($_FILES['profile_file']) && $_FILES['profile_file']['error'] === 0) {
        // Galing sa File Upload
        $ext = pathinfo($_FILES['profile_file']['name'], PATHINFO_EXTENSION);
        $file_name = 'profile_' . $user_id . '_' . time() . '.' . $ext;
        $target = '../uploads/' . $file_name;
        
        if (move_uploaded_file($_FILES['profile_file']['tmp_name'], $target)) {
            $profile_image = $file_name;
        }
    }

    // 3. Update Database
    $up = $conn->prepare("UPDATE users SET name = ?, contact = ?, profile = ? WHERE id = ?");
    $up->bind_param("sssi", $name, $contact, $profile_image, $user_id);
    
    if ($up->execute()) {
        $_SESSION['name'] = $name;
        $success = "Profile updated successfully!";
        // Refresh data
        $user['profile'] = $profile_image;
        $user['name'] = $name;
        $user['contact'] = $contact;
    } else {
        $error = "Update failed. Please try again.";
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
        max-width: 800px;
        margin: 2rem auto;
        padding: 0 1rem;
    }

    /* Profile Card */
    .profile-card {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
    }

    .profile-card:hover {
        transform: translateY(-5px);
    }

    .profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .profile-header::before {
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

    .profile-header h1 {
        font-size: 2rem;
        margin: 0;
        font-weight: bold;
        position: relative;
        z-index: 1;
    }

    .profile-header p {
        margin: 0.5rem 0 0;
        opacity: 0.9;
        position: relative;
        z-index: 1;
    }

    /* Messages */
    .alert {
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
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
        border-left: 4px solid #10b981;
        color: #065f46;
    }

    .alert-error {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        border-left: 4px solid #ef4444;
        color: #991b1b;
    }

    /* Profile Photo Section */
    .photo-section {
        padding: 2rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        border-bottom: 1px solid #e5e7eb;
    }

    .profile-preview-wrapper {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .profile-preview {
        width: 150px;
        height: 150px;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid white;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .profile-preview:hover {
        transform: scale(1.05);
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.2);
    }

    .photo-actions {
        display: flex;
        gap: 1rem;
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
        transition: all 0.3s ease;
        border: none;
        font-size: 0.9rem;
    }

    .btn-upload {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-upload:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102,126,234,0.4);
    }

    .btn-camera {
        background: #f3f4f6;
        color: #374151;
        border: 2px solid #e5e7eb;
    }

    .btn-camera:hover {
        background: #e5e7eb;
        transform: translateY(-2px);
    }

    /* Camera Container */
    .camera-container {
        background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
        border-radius: 20px;
        padding: 1.5rem;
        margin-top: 1rem;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .camera-container video {
        max-width: 100%;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    }

    .camera-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 1rem;
    }

    .btn-capture {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }

    .btn-cancel {
        background: #6b7280;
        color: white;
    }

    /* Form Section */
    .form-section {
        padding: 2rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: bold;
        color: #374151;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-control {
        width: 100%;
        padding: 0.875rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }

    .form-control:disabled {
        background: #f9fafb;
        cursor: not-allowed;
        color: #6b7280;
    }

    .input-icon {
        position: relative;
    }

    .input-icon .icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }

    .input-icon .form-control {
        padding-left: 2.5rem;
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
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102,126,234,0.4);
    }

    .btn-submit:active {
        transform: translateY(0);
    }

    hr {
        margin: 1.5rem 0;
        border: none;
        border-top: 2px solid #e5e7eb;
    }

    @media (max-width: 768px) {
        .profile-container {
            margin: 1rem auto;
        }
        
        .photo-actions {
            flex-direction: column;
        }
        
        .btn-photo {
            justify-content: center;
        }
        
        .profile-header h1 {
            font-size: 1.5rem;
        }
    }
</style>

<div class="profile-container">
    <div class="profile-card">
        <!-- Header -->
        <div class="profile-header">
            <h1>👤 My Profile</h1>
            <p>Manage your personal information and profile picture</p>
        </div>

        <!-- Messages -->
        <div style="padding: 0 2rem; padding-top: 1.5rem;">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?= htmlspecialchars($success) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?= htmlspecialchars($error) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data" id="profileForm">
            <!-- Profile Photo Section -->
            <div class="photo-section">
                <div class="profile-preview-wrapper">
                    <?php 
                        $img_path = !empty($user['profile']) ? '../uploads/' . $user['profile'] : '../assets/default-avatar.png';
                    ?>
                    <img src="<?= $img_path ?>" id="img-preview" class="profile-preview" alt="Profile Preview">
                </div>

                <div class="photo-actions">
                    <label class="btn-photo btn-upload">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Upload Photo
                        <input type="file" name="profile_file" id="file-input" class="hidden" accept="image/*">
                    </label>

                    <button type="button" class="btn-photo btn-camera" onclick="startCamera()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Use Camera
                    </button>
                </div>
            </div>

            <!-- Camera Container -->
            <div id="camera-container" class="camera-container" style="display: none; margin: 0 2rem 2rem 2rem;">
                <video id="video" autoplay playsinline class="mx-auto rounded-lg" style="max-width: 100%;"></video>
                <canvas id="canvas" style="display: none;"></canvas>
                <div class="camera-buttons">
                    <button type="button" class="btn-photo btn-capture" onclick="takeSnapshot()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Capture Photo
                    </button>
                    <button type="button" class="btn-photo btn-cancel" onclick="stopCamera()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancel
                    </button>
                </div>
            </div>

            <input type="hidden" name="image_base64" id="image_base64">

            <!-- Form Fields -->
            <div class="form-section">
                <div class="form-group">
                    <label>📧 Email Address</label>
                    <div class="input-icon">
                        <svg class="icon w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled 
                               class="form-control" style="padding-left: 2.5rem;">
                    </div>
                </div>

                <div class="form-group">
                    <label>👤 Full Name <span style="color: #ef4444;">*</span></label>
                    <div class="input-icon">
                        <svg class="icon w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <input type="text" name="name" id="name" value="<?= htmlspecialchars($user['name']) ?>" required 
                               class="form-control" style="padding-left: 2.5rem;">
                    </div>
                </div>

                <div class="form-group">
                    <label>📞 Contact Number <span style="color: #ef4444;">*</span></label>
                    <div class="input-icon">
                        <svg class="icon w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        <input type="text" name="contact" id="contact" value="<?= htmlspecialchars($user['contact'] ?? '') ?>" required 
                               class="form-control" style="padding-left: 2.5rem;">
                    </div>
                </div>

                <hr>

                <button type="submit" class="btn-submit">
                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// JavaScript remains EXACTLY the same as original — walang binago sa logic
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const cameraContainer = document.getElementById('camera-container');
const imgPreview = document.getElementById('img-preview');
const imageBase64Input = document.getElementById('image_base64');
let stream = null;

// File preview
document.getElementById('file-input').onchange = function (evt) {
    const [file] = this.files;
    if (file) {
        imgPreview.src = URL.createObjectURL(file);
        imageBase64Input.value = ""; // Clear camera data
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
</script>

<?php include '../includes/footer.php'; ?>