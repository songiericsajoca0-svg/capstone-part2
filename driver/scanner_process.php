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
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
        }
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

    <form id="pickupForm" class="space-y-4 mb-10">
        <input type="hidden" name="manual_form" value="1">
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-2 ml-1">Enter Booking Code</label>
            <input type="text" id="bookingCode" name="code" class="w-full p-4 rounded-xl border-2 border-dashed border-slate-200 text-center font-bold text-lg focus:border-blue-500 focus:border-solid outline-none transition-all uppercase" placeholder="BK-XXXXX" required>
        </div>
        <button type="submit" id="pickupBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-200 transition-all flex items-center justify-center gap-2">
            <i class="fas fa-user-check"></i> VERIFY & PICKUP
        </button>
    </form>

    <div class="relative mb-10">
        <div class="absolute inset-0 flex items-center"><span class="w-full border-t border-slate-200"></span></div>
        <div class="relative flex justify-center text-xs uppercase"><span class="bg-white px-4 text-slate-400 font-semibold">OR END CURRENT TRIP</span></div>
    </div>

    <form id="completeForm" class="space-y-4">
        <input type="hidden" name="manual_form" value="1">
        <input type="hidden" name="action" value="complete">
        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-2 ml-1">Active Booking ID</label>
            <input type="number" id="bookingId" name="booking_id" class="w-full p-3 rounded-lg border border-slate-200 text-center font-bold" placeholder="0000" required>
        </div>
        <button type="submit" id="completeBtn" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-emerald-200 transition-all flex items-center justify-center gap-2">
            <i class="fas fa-flag-checkered"></i> COMPLETE TRIP
        </button>
    </form>

    <div class="mt-8 text-center">
        <a href="driver-scanner.php" class="text-blue-600 font-semibold hover:underline flex items-center justify-center gap-2">
            <i class="fas fa-camera"></i> Open QR Scanner
        </a>
    </div>
</div>

<script>
// Helper function to show loading state on button
function setLoading(button, isLoading) {
    if (isLoading) {
        button.classList.add('btn-loading');
        button.disabled = true;
        const originalHTML = button.innerHTML;
        button.dataset.originalHTML = originalHTML;
        button.innerHTML = '<span class="loading-spinner"></span> PROCESSING...';
    } else {
        button.classList.remove('btn-loading');
        button.disabled = false;
        if (button.dataset.originalHTML) {
            button.innerHTML = button.dataset.originalHTML;
            delete button.dataset.originalHTML;
        }
    }
}

// Helper function to reset forms
function resetForms() {
    document.getElementById('bookingCode').value = '';
    document.getElementById('bookingId').value = '';
    
    // Remove any error styling
    document.getElementById('bookingCode').classList.remove('border-red-500');
    document.getElementById('bookingId').classList.remove('border-red-500');
}

// Handle Pickup Form Submission
document.getElementById('pickupForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const code = document.getElementById('bookingCode').value.trim();
    if (!code) {
        Swal.fire({
            icon: 'warning',
            title: 'Empty Field',
            text: 'Please enter a booking code.',
            confirmButtonColor: '#2563eb'
        });
        return;
    }
    
    const pickupBtn = document.getElementById('pickupBtn');
    setLoading(pickupBtn, true);
    
    try {
        const formData = new FormData();
        formData.append('code', code);
        formData.append('manual_form', '1');
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Accept': 'application/json'
            },
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Verified!',
                text: result.message,
                confirmButtonColor: '#2563eb',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                resetForms();
                // Optional: Focus back on the input for next scan
                document.getElementById('bookingCode').focus();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Verification Failed',
                text: result.message,
                confirmButtonColor: '#2563eb'
            });
            document.getElementById('bookingCode').classList.add('border-red-500');
            setTimeout(() => {
                document.getElementById('bookingCode').classList.remove('border-red-500');
            }, 3000);
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Failed to connect to server. Please try again.',
            confirmButtonColor: '#2563eb'
        });
    } finally {
        setLoading(pickupBtn, false);
    }
});

// Handle Complete Trip Form Submission
document.getElementById('completeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const bookingId = document.getElementById('bookingId').value.trim();
    if (!bookingId) {
        Swal.fire({
            icon: 'warning',
            title: 'Empty Field',
            text: 'Please enter a booking ID.',
            confirmButtonColor: '#2563eb'
        });
        return;
    }
    
    const completeBtn = document.getElementById('completeBtn');
    setLoading(completeBtn, true);
    
    try {
        const formData = new FormData();
        formData.append('action', 'complete');
        formData.append('booking_id', bookingId);
        formData.append('manual_form', '1');
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Accept': 'application/json'
            },
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Trip Completed!',
                text: result.message,
                confirmButtonColor: '#2563eb',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                // Reset the form after completion
                resetForms();
                // Optional: Focus back on booking code input for next transaction
                document.getElementById('bookingCode').focus();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Completion Failed',
                text: result.message,
                confirmButtonColor: '#2563eb'
            });
            document.getElementById('bookingId').classList.add('border-red-500');
            setTimeout(() => {
                document.getElementById('bookingId').classList.remove('border-red-500');
            }, 3000);
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Failed to connect to server. Please try again.',
            confirmButtonColor: '#2563eb'
        });
    } finally {
        setLoading(completeBtn, false);
    }
});

// Auto-focus on booking code input on page load
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('bookingCode').focus();
});
</script>

</body>
</html>