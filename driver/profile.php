<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

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

// 2. I-fetch ang data ng Driver
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        die("Driver record not found.");
    }
} catch (Exception $e) {
    $error = "Connection Error: " . $e->getMessage();
}

// 3. Handle Update Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $new_pwd = $_POST['password'];
    $profile_name = $user['profile']; 

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
            $stmt = $conn->prepare("UPDATE users SET name=?, contact=?, profile=?, password=? WHERE id=?");
            $stmt->bind_param("ssssi", $name, $contact, $profile_name, $hashed_pwd, $driver_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=?, contact=?, profile=? WHERE id=?");
            $stmt->bind_param("sssi", $name, $contact, $profile_name, $driver_id);
        }
        $stmt->execute();

        $_SESSION['name'] = $name;
        $message = "Profile updated successfully!";
        
        // Refresh user data
        $user['name'] = $name;
        $user['contact'] = $contact;
        $user['profile'] = $profile_name;
        
        header("Refresh:2");
    } catch (Exception $e) {
        $error = "Update failed: " . $e->getMessage();
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

    <!-- Main Form -->
    <form method="POST" enctype="multipart/form-data" class="main-card">
        <!-- Photo Section -->
        <div class="card-section">
            <div class="section-title">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Profile Photo
            </div>
            <div class="photo-upload-area">
                <img src="<?= $profile_img ?>" alt="Profile Preview" class="photo-preview" id="photoPreview">
                <div class="photo-buttons">
                    <button type="button" class="btn-photo btn-camera" id="openCameraBtn">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Take Photo
                    </button>
                    <label class="btn-photo btn-upload">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        Upload Photo
                        <input type="file" name="profile" id="fileInput" accept="image/*" style="display:none;">
                    </label>
                </div>
            </div>
            <input type="hidden" name="camera_image" id="cameraImage">
        </div>

        <!-- Account Information -->
        <div class="card-section">
            <div class="section-title">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <label>Email Address</label>
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-control" readonly>
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
                <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Update Profile
            </button>
            <a href="dashboard.php" class="btn-back">
                <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Return to Dashboard
            </a>
        </div>
    </form>
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
            Swal.fire({
                icon: 'error',
                title: 'Camera Error',
                text: 'Unable to access camera. Please check permissions.',
                confirmButtonColor: '#667eea'
            });
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
        
        Swal.fire({
            icon: 'success',
            title: 'Photo Captured!',
            text: 'Your profile photo has been updated.',
            timer: 1500,
            showConfirmButton: false
        });
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
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>