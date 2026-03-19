<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Only drivers allowed
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../dashboard.php");
    exit;
}

$driver_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Driver';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver QR Scanner | Pro</title>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            --success: #10b981;
            --error: #ef4444;
            --bg: #f1f5f9;
        }

        body, button, div, h2, h3, p, span, strong {
            font-family: 'NaruMonoDemo', monospace !important;
        }

        body {
            background-color: var(--bg);
            margin: 0;
            color: #1e293b;
        }

        .scanner-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .header-section {
            text-align: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .scanner-card {
            background: white;
            border-radius: 30px;
            padding: 1.5rem;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            text-align: center;
        }

        .video-wrapper {
            position: relative;
            width: 100%;
            aspect-ratio: 1 / 1;
            margin: 0 auto 1.5rem;
            border-radius: 24px;
            overflow: hidden;
            background: #000;
            border: 5px solid #fff;
            box-shadow: 0 0 0 2px var(--primary);
        }

        #video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Animated Scan Line */
        .scan-line {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
            box-shadow: 0 0 15px 3px var(--primary);
            animation: scan 2.5s ease-in-out infinite;
            z-index: 10;
        }

        @keyframes scan {
            0%, 100% { top: 5%; }
            50% { top: 95%; }
        }

        #result {
            margin-bottom: 1.5rem;
            padding: 1.2rem;
            border-radius: 15px;
            background: #f8fafc;
            border: 2px dashed #e2e8f0;
            font-size: 0.9rem;
            min-height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            padding: 16px;
            border-radius: 15px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-success { 
            background: var(--success); 
            color: white; 
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
        }
        
        .btn-outline { 
            background: white; 
            border: 2px solid #e2e8f0; 
            color: #64748b; 
            text-decoration: none;
            display: block;
            margin-top: 10px;
        }

        .upload-label {
            color: var(--primary);
            font-size: 0.85rem;
            cursor: pointer;
            font-weight: bold;
            text-decoration: underline;
        }

        .manual-input-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px dashed #cbd5e1;
        }

        .manual-input-section input[type="text"] {
            width: 100%;
            padding: 14px;
            font-size: 1rem;
            border: 2px solid #d1d5db;
            border-radius: 12px;
            margin-bottom: 12px;
            font-family: 'NaruMonoDemo', monospace;
        }

        .manual-input-section button {
            width: 100%;
        }

        /* SweetAlert Font Override */
        .swal2-popup { font-family: 'NaruMonoDemo', monospace !important; border-radius: 20px !important; }
    </style>
</head>
<body>

<div class="scanner-container">
    <div class="header-section">
        <h2 style="margin:0; color:var(--primary); font-size: 1.2rem;">TRIP VALIDATION</h2>
        <p style="margin:5px 0 0; color:#64748b; font-size: 0.8rem;">Driver: <strong><?= htmlspecialchars($driver_name) ?></strong></p>
    </div>

    <div class="scanner-card">
        <div class="video-wrapper">
            <div class="scan-line" id="scanLine"></div>
            <video id="video" autoplay playsinline></video>
            <canvas id="canvas" style="display:none;"></canvas>
        </div>

        <div id="result">
            <span style="color: #94a3b8;">[ ALIGN QR CODE TO SCAN ]</span>
        </div>

        <div class="btn-group">
            <button id="completeBtn" class="btn btn-success" style="display:none;">
                COMPLETE TRIP NOW
            </button>
            
            <label class="upload-label">
                <input type="file" id="fileInput" accept="image/*" style="display:none;">
                <span>UPLOAD QR FROM GALLERY</span>
            </label>

            <!-- MANUAL INPUT SECTION -->
            <div class="manual-input-section">
                <input type="text" id="manualCode" placeholder="Enter Booking Code manually" autocomplete="off">
                <button class="btn btn-primary" onclick="manualVerify()">VERIFY MANUALLY</button>
            </div>

            <a href="dashboard.php" class="btn btn-outline">CANCEL / BACK</a>
        </div>
    </div>
</div>

<script>
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const ctx = canvas.getContext('2d', { willReadFrequently: true });
const resultDiv = document.getElementById('result');
const fileInput = document.getElementById('fileInput');
const completeBtn = document.getElementById('completeBtn');
const scanLine = document.getElementById('scanLine');
const manualCodeInput = document.getElementById('manualCode');

let scanning = false;
let currentBookingId = null;

// --- PERSISTENCE LOGIC ---

function saveScannerState(data) {
    const state = {
        booking_id: data.booking_id,
        booking_code: data.booking_code,
        html: resultDiv.innerHTML,
        timestamp: Date.now()
    };
    localStorage.setItem('active_trip_state', JSON.stringify(state));
}

function loadScannerState() {
    const savedState = localStorage.getItem('active_trip_state');
    if (savedState) {
        const state = JSON.parse(savedState);
        
        // I-restore ang UI
        resultDiv.style.background = "#f0fdf4";
        resultDiv.style.borderColor = "#10b981";
        resultDiv.innerHTML = state.html;
        
        completeBtn.style.display = 'block';
        currentBookingId = state.booking_id;
        completeBtn.onclick = () => completeTrip(state.booking_id);
        
        // Itigil ang camera scanning dahil verified na
        scanning = false;
        scanLine.style.display = 'none';
        if (video.srcObject) {
            video.srcObject.getTracks().forEach(track => track.stop());
        }
    } else {
        startCamera();
    }
}

function clearScannerState() {
    localStorage.removeItem('active_trip_state');
}

// --- CAMERA LOGIC ---

function startCamera() {
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => {
            video.srcObject = stream;
            video.play();
            scanning = true;
            requestAnimationFrame(tick);
        })
        .catch(err => {
            resultDiv.innerHTML = `<span style="color:var(--error);">CAMERA ERROR. USE UPLOAD OR MANUAL INPUT.</span>`;
            scanLine.style.display = 'none';
        });
}

function tick() {
    if (video.readyState === video.HAVE_ENOUGH_DATA && scanning) {
        canvas.height = video.videoHeight;
        canvas.width = video.videoWidth;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: "dontInvert" });

        if (code) {
            scanning = false;
            handleScanResult(code.data);
        }
    }
    if (scanning) requestAnimationFrame(tick);
}

// --- HANDLERS ---

function handleScanResult(code) {
    resultDiv.innerHTML = `<strong>PROCESSING...</strong>`;
    
    fetch('scanner_process.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'code=' + encodeURIComponent(code)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            resultDiv.style.background = "#f0fdf4";
            resultDiv.style.borderColor = "#10b981";
            resultDiv.innerHTML = `<div style="color:var(--success)">
                <strong>PASSENGER VERIFIED</strong><br>
                <small>CODE: ${data.booking_code}</small>
            </div>`;
            
            completeBtn.style.display = 'block';
            completeBtn.onclick = () => completeTrip(data.booking_id);
            scanLine.style.display = 'none';

            // I-SAVE ANG STATE DITO
            saveScannerState(data);
            
            Swal.fire({ icon: 'success', title: 'Verified!', text: 'Passenger is now in transit.', timer: 2000, showConfirmButton: false });
        } else {
            Swal.fire('Invalid QR', data.message, 'error').then(() => resetScanner());
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Network connection failed.', 'error');
        resetScanner();
    });
}

function completeTrip(bookingId) {
    Swal.fire({
        title: 'Complete Trip?',
        text: "Confirm if passenger has reached the destination.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'YES, COMPLETED'
    }).then((result) => {
        if (result.isConfirmed) {
            completeBtn.disabled = true;
            completeBtn.innerText = "SAVING...";

            fetch('scanner_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=complete&booking_id=' + bookingId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // BURAHIN ANG STATE DAHIL TAPOS NA ANG TRIP
                    clearScannerState();
                    Swal.fire('Trip Done!', 'Data saved successfully.', 'success').then(() => {
                        window.location.href = 'dashboard.php';
                    });
                } else {
                    Swal.fire('Error', data.message, 'error');
                    completeBtn.disabled = false;
                    completeBtn.innerText = "COMPLETE TRIP NOW";
                }
            });
        }
    });
}

function resetScanner() {
    clearScannerState();
    setTimeout(() => {
        scanning = true;
        scanLine.style.display = 'block';
        requestAnimationFrame(tick);
        resultDiv.innerHTML = `<span style="color: #94a3b8;">[ ALIGN QR CODE TO SCAN ]</span>`;
        resultDiv.style.background = "#f8fafc";
        resultDiv.style.borderColor = "#e2e8f0";
        manualCodeInput.value = '';
    }, 2000);
}

// Gallery & Manual Inputs (Retain your existing logic but call handleScanResult)
fileInput.addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = ev => {
        const img = new Image();
        img.onload = () => {
            canvas.height = img.height;
            canvas.width = img.width;
            ctx.drawImage(img, 0, 0);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);
            if (code) handleScanResult(code.data);
            else Swal.fire('Error', 'No QR code detected.', 'error');
        };
        img.src = ev.target.result;
    };
    reader.readAsDataURL(file);
});

function manualVerify() {
    const code = manualCodeInput.value.trim();
    if (code) handleScanResult(code);
}

// INITIALIZE ON LOAD
document.addEventListener('DOMContentLoaded', loadScannerState);
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>