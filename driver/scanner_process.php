<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if Driver or Admin
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'driver' && $_SESSION['role'] !== 'admin')) {
    header("Location: ../index.php");
    exit;
}

$driver_id = $_SESSION['user_id'];
$today_date = date('Y-m-d');

// ── HYBRID RESPONSE LOGIC ──────────────────────────────────────────────────
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' || isset($_POST['code']) || (isset($_POST['action']) && $_POST['action'] === 'complete');

$message = "";
$status_type = "";
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $code   = trim($_POST['code'] ?? '');
    $bid    = (int)($_POST['booking_id'] ?? 0);
    $time   = date('Y-m-d H:i:s');

    // Case 1: Manual Pickup or Scan Pickup
    if (!empty($code) && $action !== 'complete') {
        $stmt = $conn->prepare("SELECT id, booking_code, driver_id FROM bookings WHERE booking_code = ? AND status = 'ASSIGNED'");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        if ($res) {
            if ($res['driver_id'] != $driver_id) {
                $message = "ACCESS DENIED: This booking is not assigned to you.";
                $status_type = "error";
                $response['message'] = $message;
            } else {
                $up = $conn->prepare("UPDATE bookings SET status = 'IN TRANSIT', pickup_time = ? WHERE id = ?");
                $up->bind_param("si", $time, $res['id']);
                if ($up->execute()) {
                    $message = "Passenger picked up! Trip is now IN TRANSIT.";
                    $status_type = "success";
                    $response = [
                        'success' => true, 
                        'message' => $message,
                        'booking_id' => $res['id'],
                        'booking_code' => $res['booking_code']
                    ];
                }
            }
        } else {
            $message = "Invalid Code or Trip already started.";
            $status_type = "error";
            $response['message'] = $message;
        }
    } 

    // Case 2: Complete Trip
    else if ($action === 'complete' && $bid > 0) {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("SELECT fare FROM bookings WHERE id = ? AND driver_id = ? AND status IN ('IN TRANSIT', 'PASSENGER PICKED UP')");
            $stmt->bind_param("ii", $bid, $driver_id);
            $stmt->execute();
            $booking = $stmt->get_result()->fetch_assoc();

            if (!$booking) {
                throw new Exception("Cannot complete booking. This trip may not be yours or is already finished.");
            }

            $fare = $booking['fare'];

            $upBooking = $conn->prepare("UPDATE bookings SET status = 'COMPLETED', dropoff_time = ? WHERE id = ?");
            $upBooking->bind_param("si", $time, $bid);
            $upBooking->execute();

            $upStats = $conn->prepare("
                INSERT INTO driver_stats (driver_id, today_earnings, lifetime_earnings, total_completed_trips, last_update) 
                VALUES (?, ?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE 
                    today_earnings = CASE 
                        WHEN last_update = VALUES(last_update) THEN today_earnings + VALUES(today_earnings) 
                        ELSE VALUES(today_earnings) 
                    END,
                    lifetime_earnings = lifetime_earnings + VALUES(lifetime_earnings),
                    total_completed_trips = total_completed_trips + 1,
                    last_update = VALUES(last_update)
            ");
            $upStats->bind_param("idds", $driver_id, $fare, $fare, $today_date);
            $upStats->execute();

            $conn->commit();
            $message = "Trip successfully completed! Fare: ₱" . number_format($fare, 2);
            $status_type = "success";
            $response = ['success' => true, 'message' => $message];

        } catch (Exception $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $status_type = "error";
            $response['message'] = $message;
        }
    }

    if (isset($_POST['code']) || (isset($_POST['action']) && $_POST['action'] === 'complete' && isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Terminal | Scan & Complete</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        :root { --primary: #2563eb; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; }
        .terminal-card { background: white; border-radius: 1.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

<div class="max-w-md w-full terminal-card p-8">
    <div class="text-center mb-8">
        <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-qrcode text-blue-600 text-2xl"></i>
        </div>
        <h2 class="text-2xl font-bold text-slate-800">Driver Terminal</h2>
        <p class="text-slate-500 text-sm">Manual verification and trip ending</p>
    </div>

    <form method="POST" class="space-y-4 mb-10">
        <input type="hidden" name="manual_form" value="1">
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-2 ml-1">Enter Booking Code</label>
            <input type="text" name="code" class="w-full p-4 rounded-xl border-2 border-dashed border-slate-200 text-center font-bold text-lg focus:border-blue-500 focus:border-solid outline-none transition-all uppercase" placeholder="BK-XXXXX" required>
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-200 transition-all flex items-center justify-center gap-2">
            <i class="fas fa-user-check"></i> VERIFY & PICKUP
        </button>
    </form>

    <div class="relative mb-10">
        <div class="absolute inset-0 flex items-center"><span class="w-full border-t border-slate-200"></span></div>
        <div class="relative flex justify-center text-xs uppercase"><span class="bg-white px-4 text-slate-400 font-semibold">OR END CURRENT TRIP</span></div>
    </div>

    <form method="POST" class="space-y-4">
        <input type="hidden" name="manual_form" value="1">
        <input type="hidden" name="action" value="complete">
        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-2 ml-1">Active Booking ID</label>
            <input type="number" name="booking_id" class="w-full p-3 rounded-lg border border-slate-200 text-center font-bold" placeholder="0000" required>
        </div>
        <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-emerald-200 transition-all flex items-center justify-center gap-2">
            <i class="fas fa-flag-checkered"></i> COMPLETE TRIP
        </button>
    </form>

    <div class="mt-8 text-center">
        <a href="driver-scanner.php" class="text-blue-600 font-semibold hover:underline flex items-center justify-center gap-2">
            <i class="fas fa-camera"></i> Open QR Scanner
        </a>
    </div>
</div>

<?php if ($message): ?>
<script>
    Swal.fire({
        title: '<?= $status_type === "success" ? "Success!" : "Error" ?>',
        text: '<?= $message ?>',
        icon: '<?= $status_type ?>',
        confirmButtonColor: '#2563eb'
    }).then(() => {
        <?php if ($status_type === "success"): ?>
        window.location.href = 'dashboard.php';
        <?php endif; ?>
    });
</script>
<?php endif; ?>

</body>
</html>