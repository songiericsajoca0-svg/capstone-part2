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
    if ($conn instanceof PDO) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$driver_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    }

    if (!$user) {
        die("Driver record not found.");
    }
} catch (Exception $e) {
    $error = "Connection Error: " . $e->getMessage();
}

// 3. Handle Update Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = $_POST['name'];
    $contact = $_POST['contact'];
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

        if ($conn instanceof PDO) {
            $sql = "UPDATE users SET name = ?, contact = ?, profile = ? " . (!empty($new_pwd) ? ", password = ?" : "") . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $params = [$name, $contact, $profile_name];
            if (!empty($new_pwd)) $params[] = password_hash($new_pwd, PASSWORD_DEFAULT);
            $params[] = $driver_id;
            $stmt->execute($params);
        } else {
            if (!empty($new_pwd)) {
                $hashed_pwd = password_hash($new_pwd, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name=?, contact=?, profile=?, password=? WHERE id=?");
                $stmt->bind_param("ssssi", $name, $contact, $profile_name, $hashed_pwd, $driver_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, contact=?, profile=? WHERE id=?");
                $stmt->bind_param("sssi", $name, $contact, $profile_name, $driver_id);
            }
            $stmt->execute();
        }

        $_SESSION['name'] = $name;
        $message = "Profile updated successfully!";
        header("Refresh:1");
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
    <title>Edit Profile | Driver Pro</title>
    <?php include '../includes/header.php'; ?>

    <style>
        @font-face {
            font-family: 'NaruMonoDemo';
            src: url('../assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        :root {
            --primary: #4f46e5;
            --secondary: #312e81;
            --success: #10b981;
            --danger: #ef4444;
            --bg: #f8fafc;
        }

        body {
            font-family: 'NaruMonoDemo', monospace !important;
            margin: 0;
            background: var(--bg);
            color: #1e293b;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            padding: 3rem 1rem;
            text-align: center;
            border-radius: 30px;
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.4);
            margin-bottom: 2rem;
        }

        .profile-pic-wrapper {
            position: relative;
            display: inline-block;
        }

        .profile-pic {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 6px solid rgba(255,255,255,0.2);
            background: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .card {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #f1f5f9;
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: 'NaruMonoDemo', monospace;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .form-control[readonly] {
            background: #f8fafc;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .btn {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            font-family: 'NaruMonoDemo', monospace;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--secondary); transform: translateY(-2px); }
        
        .btn-camera { background: #1e293b; color: white; margin-bottom: 15px; }
        .btn-camera:hover { background: #000; }

        .btn-cancel { 
            background: transparent; 
            color: #64748b; 
            margin-top: 10px; 
            text-decoration: none; 
            font-size: 0.85rem;
        }

        #camera-wrapper { 
            display: none; 
            background: #000; 
            border-radius: 20px; 
            padding: 10px; 
            margin-bottom: 20px; 
        }
        
        #video { width: 100%; border-radius: 15px; }
        
        .alert { 
            padding: 1rem; 
            border-radius: 15px; 
            margin-bottom: 1.5rem; 
            text-align: center; 
            font-size: 0.9rem;
            font-weight: bold;
        }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Tablet/Desktop View */
        @media (min-width: 640px) {
            .form-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1.5rem;
            }
            .full-width { grid-column: span 2; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="profile-header">
        <div class="profile-pic-wrapper">
            <?php 
                $profile_img = !empty($user['profile']) ? $upload_dir . $user['profile'] : "../assets/default-driver.jpg";
            ?>
            <img src="<?= $profile_img ?>" alt="Profile" class="profile-pic" id="preview">
        </div>
        <h2 style="margin: 1rem 0 0.2rem; letter-spacing: 1px;"><?= htmlspecialchars($user['name']) ?></h2>
        <p style="margin: 0; opacity: 0.7; font-size: 0.8rem;">DRIVER ID: #<?= $user['id'] ?></p>
    </div>

    <?php if ($message) echo "<div class='alert success'>$message</div>"; ?>
    <?php if ($error) echo "<div class='alert error'>$error</div>"; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="card">
            <div class="card-title">📸 Update Photo</div>
            
            <div id="camera-wrapper">
                <video id="video" autoplay playsinline></video>
                <button type="button" class="btn btn-primary" id="snap" style="margin-top: 10px;">Capture Snapshot</button>
            </div>

            <button type="button" class="btn btn-camera" id="start-camera">Open Camera</button>

            <div class="form-group">
                <label>Or select from file</label>
                <input type="file" name="profile" id="file-input" accept="image/*" class="form-control">
            </div>
            
            <input type="hidden" name="camera_image" id="camera_image">
        </div>

        <div class="card">
            <div class="card-title">📝 Account Information</div>
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
                    <label>Designation</label>
                    <input type="text" value="<?= strtoupper($user['role']) ?>" class="form-control" readonly>
                </div>
                <div class="form-group full-width">
                    <label>Change Password (Optional)</label>
                    <input type="password" name="password" placeholder="Leave blank if no changes" class="form-control">
                </div>
            </div>

            <div style="margin-top: 1.5rem; text-align: center;">
                <button type="submit" class="btn btn-primary">Update Profile</button>
                <a href="dashboard.php" class="btn btn-cancel">Return to Dashboard</a>
            </div>
        </div>
    </form>
</div>

<canvas id="canvas" width="400" height="400" style="display:none;"></canvas>

<script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const snap = document.getElementById('snap');
    const cameraWrapper = document.getElementById('camera-wrapper');
    const startCameraBtn = document.getElementById('start-camera');
    const cameraImageInput = document.getElementById('camera_image');
    const preview = document.getElementById('preview');
    const fileInput = document.getElementById('file-input');

    startCameraBtn.addEventListener('click', async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: "user", width: 400, height: 400 }, 
                audio: false 
            });
            video.srcObject = stream;
            cameraWrapper.style.display = 'block';
            startCameraBtn.style.display = 'none';
        } catch (err) {
            alert("Unable to access camera. Check your permissions.");
        }
    });

    snap.addEventListener('click', () => {
        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, 400, 400);
        const dataUrl = canvas.toDataURL('image/jpeg');
        cameraImageInput.value = dataUrl;
        preview.src = dataUrl;
        
        let stream = video.srcObject;
        let tracks = stream.getTracks();
        tracks.forEach(track => track.stop());
        cameraWrapper.style.display = 'none';
        startCameraBtn.style.display = 'block';
        startCameraBtn.innerText = "Retake Photo";
    });

    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            preview.src = URL.createObjectURL(this.files[0]);
            cameraImageInput.value = ""; 
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>