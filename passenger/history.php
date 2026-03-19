<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Siguraduhin na passenger lang ang makaka-access
if ($_SESSION['role'] !== 'passenger') {
    header("Location: ../index.php");
    exit;
}

$passenger_id = $_SESSION['user_id'];

$bookings = $conn->query("
    SELECT 
        id,
        booking_code,
        pickup_landmark,
        dropoff_landmark,
        driver_name,
        status,
        notes,
        pickup_time,
        dropoff_time,
        created_at
    FROM bookings 
    WHERE passenger_id = $passenger_id 
      AND status IN ('COMPLETED', 'CANCELLED')
    ORDER BY created_at DESC 
    LIMIT 50
");

?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <h2>My Booking History</h2>
    <p style="color:#666; margin-bottom:1.5rem;">
        View your completed and cancelled trips. (Last 50 records)
    </p>

    <?php 
    if (isset($_GET['msg'])) {
        $color = ($_GET['type'] ?? 'success') === 'success' ? 'green' : 'red';
        echo "<p style='color:$color; text-align:center; padding:1rem; background:#f8f8f8; border-radius:6px;'>
                {$_GET['msg']}
              </p>";
    }
    ?>

    <?php if ($bookings->num_rows > 0): ?>
    
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Route</th>
                <th>Status</th>
                <th>Driver</th>
                <th>Date / Time</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $bookings->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['booking_code']) ?></strong></td>
                <td>
                    <?= htmlspecialchars($row['pickup_landmark']) ?> 
                    → 
                    <?= htmlspecialchars($row['dropoff_landmark']) ?>
                </td>
                <td>
                    <?php 
                    $status = $row['status'];
                    $color = ($status === 'COMPLETED') ? 'green' : 'red';
                    echo "<strong style='color:$color;'>$status</strong>";
                    ?>
                </td>
                <td><?= htmlspecialchars($row['driver_name'] ?: '—') ?></td>
                <td>
                    <?php 
                    if ($row['dropoff_time']) {
                        echo date('M d, Y<br>h:i A', strtotime($row['dropoff_time']));
                    } elseif ($row['pickup_time']) {
                        echo date('M d, Y<br>h:i A', strtotime($row['pickup_time']));
                    } else {
                        echo date('M d, Y<br>h:i A', strtotime($row['created_at']));
                    }
                    ?>
                </td>
                <td>
                    <a href="qr.php?id=<?= $row['id'] ?>" class="btn btn-primary" 
                       style="padding:6px 12px; font-size:0.9rem; display:inline-block;">
                        View QR / Details
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <?php else: ?>
    
    <div style="text-align:center; padding:3rem 1rem; background:#f8f9fa; border-radius:8px;">
        <p style="font-size:1.2rem; color:#666;">
            Wala ka pang natapos o nakansel na booking.<br>
            <a href="create-booking.php" style="color:#1d4ed8; font-weight:bold;">Mag-book na ngayon!</a>
        </p>
    </div>

    <?php endif; ?>

    <p style="margin-top:2rem; text-align:center; font-size:0.9rem; color:#777;">
        Para sa mga reklamo o follow-up, i-contact ang dispatcher/admin.
    </p>
</div>

<?php include '../includes/footer.php'; ?>