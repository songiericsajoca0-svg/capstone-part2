<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$booking_id = (int)$_GET['id'];
$pid = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND passenger_id = ?");
$stmt->bind_param("ii", $booking_id, $pid);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header("Location: my-bookings.php?error=Booking not found");
    exit;
}

$qr_path = "../qr-code/" . $booking['booking_code'] . ".png";
$logo_path = "../assets/images/logo2.png";
?>

<?php include '../includes/header.php'; ?>

<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --primary-light: #3b82f6;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-600: #4b5563;
        --gray-800: #1f2937;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
    }

    .ticket-page {
        min-height: 100vh;
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        padding: 2rem 1rem;
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }

    .ticket-container {
        width: 100%;
        max-width: 540px;
        background: white;
        border-radius: 1.25rem;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        border: 1px solid var(--gray-200);
        font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .ticket-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        padding: 1.8rem 1.25rem;
        text-align: center;
        position: relative;
    }

    .ticket-header::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 30% 20%, rgba(255,255,255,0.15) 0%, transparent 70%);
        pointer-events: none;
    }

    .ticket-header h1 {
        margin: 0;
        font-size: 1.45rem;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .ticket-header .ref {
        margin-top: 0.4rem;
        font-size: 0.9rem;
        opacity: 0.92;
    }

    .qr-section {
        padding: 2rem 1.25rem;
        background: var(--gray-100);
        text-align: center;
    }

    .qr-wrapper {
        position: relative;
        display: inline-block;
        background: white;
        padding: 1rem;
        border-radius: 1rem;
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        transition: transform 0.2s;
    }

    .qr-wrapper:hover {
        transform: translateY(-3px);
    }

    .qr-wrapper img.main-qr {
        width: 200px;
        height: 200px;
        border-radius: 0.6rem;
    }

    .qr-logo-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 55px;
        height: 55px;
        background: white;
        padding: 5px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border: 2px solid white;
    }

    .qr-instruction {
        margin-top: 1rem;
        color: var(--gray-600);
        font-size: 0.9rem;
        font-weight: 500;
    }

    .details-section {
        padding: 1.5rem 1.6rem;
    }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 0.9rem 1.4rem;
        align-items: baseline;
    }

    .info-label {
        color: var(--gray-600);
        font-size: 0.9rem;
        font-weight: 500;
    }

    .info-value {
        font-weight: 600;
        color: var(--gray-800);
        font-size: 0.98rem;
        text-align: right;
    }

    .fare-highlight {
        color: var(--primary-dark);
        font-size: 1.15rem;
        font-weight: 800;
    }

    .status-badge {
        padding: 0.3rem 0.8rem;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .status-pending    { background: #fef3c7; color: #92400e; }
    .status-confirmed  { background: #d1fae5; color: #065f46; }
    .status-onride     { background: #dbeafe; color: #1e40af; }
    .status-completed  { background: #dcfce7; color: #166534; }
    .status-cancelled  { background: #fee2e2; color: #991b1b; }

    .divider {
        grid-column: 1 / -1;
        height: 1px;
        background: var(--gray-200);
        margin: 0.6rem 0;
    }

    .action-area {
        padding: 1.25rem 1.6rem;
        background: var(--gray-100);
        text-align: center;
        border-top: 1px solid var(--gray-200);
        display: flex;
        justify-content: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .btn-print {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.7rem 1.6rem;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 3px 10px rgba(37,99,235,0.3);
    }

    .btn-print:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
    }

    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.7rem 1.6rem;
        background: var(--gray-800);
        color: white;
        text-decoration: none;
        border-radius: 0.75rem;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.2s;
    }

    .btn-back:hover {
        background: #111827;
        transform: translateY(-1px);
    }

    @media (max-width: 576px) {
        .ticket-container {
            border-radius: 1rem;
            margin: 0 0.5rem;
        }
        .qr-wrapper img.main-qr {
            width: 180px;
            height: 180px;
        }
        .qr-logo-overlay {
            width: 48px;
            height: 48px;
        }
    }

    /* ────────────────────────────────────────
       PRINT STYLES - Receipt-like (≈80mm width)
    ──────────────────────────────────────── */
    @media print {
        body, .ticket-page {
            background: white !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .header, .footer, .action-area, .ticket-page > *:not(.ticket-container) {
            display: none !important;
        }

        .ticket-container {
            width: 80mm !important;           /* ≈ 302px @ 96dpi - common thermal width */
            max-width: 80mm !important;
            margin: 0 auto !important;
            box-shadow: none !important;
            border: 1px solid #ddd !important;
            border-radius: 0 !important;
            page-break-inside: avoid;
            font-size: 0.85rem !important;    /* smaller for receipt feel */
        }

        .ticket-header {
            padding: 12px 10px !important;
        }

        .ticket-header h1 {
            font-size: 1.1rem !important;
        }

        .qr-section {
            padding: 15px 10px !important;
            background: white !important;
        }

        .qr-wrapper {
            padding: 8px !important;
            box-shadow: none !important;
        }

        .qr-wrapper img.main-qr {
            width: 140px !important;
            height: 140px !important;
        }

        .qr-logo-overlay {
            width: 38px !important;
            height: 38px !important;
        }

        .details-section {
            padding: 10px 12px !important;
        }

        .info-grid {
            gap: 6px 8px !important;
        }

        .info-label, .info-value {
            font-size: 0.82rem !important;
        }

        .fare-highlight {
            font-size: 1rem !important;
        }

        .divider {
            margin: 4px 0 !important;
        }

        .qr-instruction {
            font-size: 0.75rem !important;
        }
    }
</style>

<div class="ticket-page">
    <div class="ticket-container">

        <div class="ticket-header">
            <h1>Booking Ticket</h1>
            <div class="ref">Ref #<?= htmlspecialchars($booking['booking_code']) ?></div>
        </div>

        <div class="qr-section">
            <?php if (file_exists($qr_path)): ?>
                <div class="qr-wrapper">
                    <img src="<?= $qr_path ?>" alt="QR Code" class="main-qr">
                    <?php if (file_exists($logo_path)): ?>
                        <img src="<?= $logo_path ?>" class="qr-logo-overlay" alt="Logo">
                    <?php endif; ?>
                </div>
                <div class="qr-instruction">
                    Show this QR to the driver
                </div>
            <?php else: ?>
                <div style="padding: 1.5rem; background: #fef2f2; border: 2px dashed #ef4444; border-radius: 0.8rem; color: #991b1b;">
                    <strong>QR Code Missing</strong><br>Contact support.
                </div>
            <?php endif; ?>
        </div>

        <div class="details-section">
            <div class="info-grid">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span class="status-badge status-<?= strtolower($booking['status'] ?? 'pending') ?>">
                        <?= htmlspecialchars(strtoupper($booking['status'] ?? 'PENDING')) ?>
                    </span>
                </div>

                <div class="divider"></div>

                <div class="info-label">Pickup</div>
                <div class="info-value"><?= htmlspecialchars($booking['pickup_landmark'] ?: '—') ?></div>

                <div class="info-label">Drop-off</div>
                <div class="info-value"><?= htmlspecialchars($booking['dropoff_landmark'] ?: '—') ?></div>

                <div class="info-label">Driver</div>
                <div class="info-value"><?= htmlspecialchars($booking['driver_name'] ?: 'Searching...') ?></div>

                <?php if (!empty($booking['pickup_time'])): ?>
                <div class="info-label">Pickup Time</div>
                <div class="info-value">
                    <?= date('M d, Y • h:i A', strtotime($booking['pickup_time'])) ?>
                </div>
                <?php endif; ?>

                <div class="divider"></div>

                <div class="info-label">Total Passengers</div>
                <div class="info-value"><?= htmlspecialchars($booking['total_pax'] ?? '1') ?> Person/s</div>

                <div class="info-label">Total Units</div>
                <div class="info-value"><?= htmlspecialchars($booking['trike_units'] ?? '1') ?> Tricycle/s</div>

                <div class="info-label">Fare Amount</div>
                <div class="info-value fare-highlight">₱<?= number_format($booking['fare'] ?? 0, 2) ?></div>

                <div class="divider"></div>

                <div class="info-label">Special Notes</div>
                <div class="info-value" style="font-weight: 400; font-style: italic; text-align: right;">
                    "<?= htmlspecialchars($booking['notes'] ?: 'No additional notes') ?>"
                </div>
            </div>
        </div>

        <div class="action-area">
            <a href="my-bookings.php" class="btn-back">← Back</a>
            <button onclick="window.print()" class="btn-print">Print Ticket</button>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>