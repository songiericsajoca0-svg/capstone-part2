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
    } else {
        $error = "Update failed. Please try again.";
    }
}
?>

<?php include '../includes/header.php'; ?>

<!-- Tailwind CDN (for modern styling - pwede mo palitan ng local Tailwind build kung production) -->
<script src="https://cdn.tailwindcss.com"></script>

<style>
    /* Custom overrides para mas corporate feel */
    .profile-preview {
        width: 140px;
        height: 140px;
        object-fit: cover;
        border-radius: 9999px;
        border: 4px solid #e5e7eb;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    input:focus {
        outline: none;
        ring: 2px solid #3b82f6;
        border-color: #3b82f6;
    }

    .btn-primary {
        transition: all 0.2s ease;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
</style>

<div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto bg-white shadow-lg rounded-2xl overflow-hidden border border-gray-200">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-8 py-10 text-white">
            <h1 class="text-3xl font-bold tracking-tight">My Profile</h1>
            <p class="mt-2 text-blue-100">Manage your personal information and profile picture</p>
        </div>

        <!-- Messages -->
        <div class="px-8 pt-6">
            <?php if ($success): ?>
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-r-lg">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-lg">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data" id="profileForm" class="px-8 pb-10 space-y-8">

            <!-- Profile Photo Section -->
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-8 pt-4">
                <div class="flex-shrink-0">
                    <?php 
                        $img_path = !empty($user['profile']) ? '../uploads/' . $user['profile'] : '../assets/default-avatar.png';
                    ?>
                    <img src="<?= $img_path ?>" id="img-preview" class="profile-preview" alt="Profile Preview">
                </div>

                <div class="flex-1 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-900">Profile Photo</h3>
                    <p class="text-sm text-gray-500">PNG, JPG or JPEG. Max 5MB recommended.</p>

                    <div class="flex flex-wrap gap-4">
                        <label class="cursor-pointer inline-flex items-center px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium rounded-lg border border-gray-300 transition">
                            Upload Photo
                            <input type="file" name="profile_file" id="file-input" class="hidden" accept="image/*">
                        </label>

                        <button type="button" class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition" onclick="startCamera()">
                            Use Camera
                        </button>
                    </div>
                </div>
            </div>

            <!-- Camera Container -->
            <div id="camera-container" class="hidden bg-gray-900 rounded-xl p-4 text-center">
                <video id="video" autoplay playsinline class="mx-auto rounded-lg max-w-[320px] w-full"></video>
                <canvas id="canvas" class="hidden"></canvas>
                <div class="mt-5 flex justify-center gap-4">
                    <button type="button" class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition" onclick="takeSnapshot()">
                        Capture Photo
                    </button>
                    <button type="button" class="px-6 py-2.5 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition" onclick="stopCamera()">
                        Cancel
                    </button>
                </div>
            </div>

            <input type="hidden" name="image_base64" id="image_base64">

            <hr class="border-gray-200 my-8">

            <!-- Form Fields -->
            <div class="grid grid-cols-1 gap-6">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled 
                           class="block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg text-gray-700 cursor-not-allowed">
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="<?= htmlspecialchars($user['name']) ?>" required 
                           class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                </div>

                <div>
                    <label for="contact" class="block text-sm font-medium text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
                    <input type="text" name="contact" id="contact" value="<?= htmlspecialchars($user['contact'] ?? '') ?>" required 
                           class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                </div>

            </div>

            <!-- Submit Button -->
            <div class="pt-6">
                <button type="submit" class="w-full btn-primary inline-flex justify-center items-center px-6 py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition">
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
    cameraContainer.classList.remove('hidden');
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;
    } catch (err) {
        alert("Camera access error: " + err.message);
    }
}

function stopCamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
    cameraContainer.classList.add('hidden');
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