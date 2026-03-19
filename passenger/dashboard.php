<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'passenger') {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch counts
$pending = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE passenger_id = $user_id AND status = 'PENDING'")->fetch_assoc()['cnt'];
$active  = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE passenger_id = $user_id AND status IN ('ASSIGNED','PASSENGER PICKED UP','IN TRANSIT')")->fetch_assoc()['cnt'];
$history = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE passenger_id = $user_id AND status IN ('COMPLETED','CANCELLED')")->fetch_assoc()['cnt'];

// Optional: Fetch profile image if you have it in session or DB
// For now assuming you store 'profile' in users table like in profile.php
$stmt = $conn->prepare("SELECT name, profile FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$profile_img = !empty($user_data['profile']) ? '../uploads/' . $user_data['profile'] : '../assets/default-avatar.png';
?>

<?php include '../includes/header.php'; ?>

<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com"></script>

<style>
    .stat-card {
        transition: all 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }
    .btn-primary {
        transition: all 0.2s ease;
    }
    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 15px rgba(37, 99, 235, 0.3);
    }
</style>

<div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto">

        <!-- Welcome & Profile Header -->
        <div class="bg-white rounded-2xl shadow-md overflow-hidden border border-gray-200 mb-8">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-8 py-10 text-white">
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
                    <img 
                        src="<?= htmlspecialchars($profile_img) ?>" 
                        alt="Profile" 
                        class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-lg"
                    >
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>!</h1>
                        <p class="mt-2 text-blue-100 text-lg">Ready for your next ride? Manage bookings or create a new one.</p>
                    </div>
                </div>
            </div>

            <!-- Quick Profile Actions -->
            <div class="px-8 py-5 flex flex-wrap gap-4 border-t border-blue-700">
                <a href="profile.php" class="inline-flex items-center px-5 py-2.5 bg-white hover:bg-gray-50 text-blue-700 font-medium rounded-lg border border-blue-200 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                    Update Profile
                </a>
                <a href="my-bookings.php" class="inline-flex items-center px-5 py-2.5 bg-white hover:bg-gray-50 text-gray-700 font-medium rounded-lg border border-gray-300 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    View All Bookings
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
            <!-- Pending -->
            <div class="stat-card bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 text-center p-8">
                <div class="inline-block p-4 bg-yellow-100 text-yellow-700 rounded-full mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <h4 class="text-lg font-semibold text-gray-700 mb-1">Pending</h4>
                <p class="text-5xl font-bold text-yellow-600"><?= $pending ?></p>
            </div>

            <!-- Active -->
            <div class="stat-card bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 text-center p-8">
                <div class="inline-block p-4 bg-green-100 text-green-700 rounded-full mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                </div>
                <h4 class="text-lg font-semibold text-gray-700 mb-1">Active Rides</h4>
                <p class="text-5xl font-bold text-green-600"><?= $active ?></p>
            </div>

            <!-- History -->
            <div class="stat-card bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 text-center p-8">
                <div class="inline-block p-4 bg-red-100 text-red-700 rounded-full mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" /></svg>
                </div>
                <h4 class="text-lg font-semibold text-gray-700 mb-1">History</h4>
                <p class="text-5xl font-bold text-red-600"><?= $history ?></p>
            </div>
        </div>

        <!-- Create Booking CTA -->
        <div class="text-center mb-12">
            <a href="create-booking.php" class="btn-primary inline-flex items-center px-10 py-5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xl rounded-xl shadow-lg">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Create New Booking
            </a>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>