<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/functions.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Kunin lahat ng PENDING bookings + passenger name
$pending_bookings = $conn->query("
    SELECT 
        b.id,
        b.booking_code,
        b.pickup_landmark,
        b.dropoff_landmark,
        b.notes,
        b.created_at,
        b.preferred_time,          -- kung may preferred_time column ka na
        u.name AS passenger_name,
        u.contact AS passenger_contact
    FROM bookings b
    JOIN users u ON b.passenger_id = u.id
    WHERE b.status = 'PENDING'
    ORDER BY b.created_at ASC          -- pinaka matanda muna (FIFO style)
");

$count_pending = $pending_bookings->num_rows;

?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <h2>Pending Bookings</h2>
    
    <p style="margin-bottom:1.5rem; color:#555;">
        <?php if ($count_pending > 0): ?>
            May <strong><?= $count_pending ?></strong> na pending na booking na kailangang i-assign.
        <?php else: ?>
            Walang pending bookings sa ngayon.
        <?php endif; ?>
    </p>

    <?php 
    // Success / error messages mula sa assign o cancel
    if (isset($_GET['msg'])) {
        $color = ($_GET['type'] ?? 'success') === 'success' ? 'green' : 'red';
        echo "<p style='color:$color; text-align:center; padding:1rem; background:#f8f8f8; border-radius:6px; margin-bottom:1.5rem;'>
                {$_GET['msg']}
              </p>";
    }
    ?>

    <?php if ($count_pending > 0): ?>

    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Booking Code</th>
                <th>Passenger</th>
                <th>Contact</th>
                <th>Pickup → Drop-off</th>
                <th>Preferred Time</th>
                <th>Requested At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $pending_bookings->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['booking_code']) ?></strong></td>
                <td><?= htmlspecialchars($row['passenger_name']) ?></td>
                <td><?= htmlspecialchars($row['passenger_contact'] ?: '—') ?></td>
                <td>
                    <?= htmlspecialchars($row['pickup_landmark']) ?> 
                    → 
                    <?= htmlspecialchars($row['dropoff_landmark']) ?>
                </td>
                <td>
                    <?php 
                    if (!empty($row['preferred_time'])) {
                        echo date('M d, Y<br>h:i A', strtotime($row['preferred_time']));
                    } else {
                        echo '—';
                    }
                    ?>
                </td>
                <td><?= date('M d, Y<br>h:i A', strtotime($row['created_at'])) ?></td>
                <td style="white-space:nowrap;">
                    <a href="/assign.php?id=<?= $row['id'] ?>" 
                       class="btn btn-primary" 
                       style="padding:8px 14px; font-size:0.95rem; margin-right:6px;">
                        Assign Driver
                    </a>
                    
                    <form method="POST" action="../cancel.php" style="display:inline;">
                        <input type="hidden" name="booking_id" value="<?= $row['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="redirect" value="/pending.php">
                        <button type="submit" 
                                class="btn btn-danger" 
                                style="padding:8px 12px; font-size:0.95rem;"
                                onclick="return confirm('Sigurado ka bang kanselahin ang booking na ito?')">
                            Cancel
                        </button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>

    <?php else: ?>

    <div style="text-align:center; padding:4rem 1rem; background:#f8f9fa; border-radius:8px; margin:2rem 0;">
        <p style="font-size:1.3rem; color:#666; margin-bottom:1rem;">
            Walang nakabinbing booking ngayon.
        </p>
        <p style="color:#888;">
            Kapag may bagong booking ang passenger, lalabas dito agad.
        </p>
    </div>

    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>