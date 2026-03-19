<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Admin access only
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$bid = (int)$_GET['id'];

// --- HANDLE ASSIGNMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver_id = (int)$_POST['driver_id'];

    if ($driver_id > 0) {
        // Kunin ang name ng driver base sa napiling ID
        $getName = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'driver'");
        $getName->bind_param("i", $driver_id);
        $getName->execute();
        $d_data = $getName->get_result()->fetch_assoc();
        
        if ($d_data) {
            $driver_name = $d_data['name'];

            // Update ang booking: I-link ang driver_id at driver_name
            $stmt = $conn->prepare("UPDATE bookings SET driver_id = ?, driver_name = ?, status = 'ASSIGNED' WHERE id = ? AND status = 'PENDING'");
            $stmt->bind_param("isi", $driver_id, $driver_name, $bid);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                header("Location: dashboard.php?msg=Driver $driver_name assigned successfully");
                exit;
            } else {
                $error = "Failed to assign or booking is no longer pending.";
            }
        } else {
            $error = "Driver not found.";
        }
    } else {
        $error = "Please select a driver.";
    }
}

// --- FETCH ONLINE DRIVERS ---
// Base sa columns mo: id, name, status
$drivers_query = $conn->query("SELECT id, name FROM users WHERE role = 'driver' AND status = 'online' ORDER BY name ASC");

// --- FETCH BOOKING DETAILS ---
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->bind_param("i", $bid);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking || $booking['status'] !== 'PENDING') {
    header("Location: dashboard.php?error=Cannot assign this booking");
    exit;
}
?>
<?php include '../includes/header.php'; ?>

<div class="card" style="max-width:600px; margin:2rem auto; padding: 25px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
  <h2 style="color: #1e293b; margin-bottom: 5px;">Assign Driver</h2>
  <p style="color: #64748b; margin-bottom: 25px;">Code: <strong><?= $booking['booking_code'] ?></strong></p>

  <?php if(isset($error)): ?>
    <div style="background: #fef2f2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fee2e2;">
        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
    </div>
  <?php endif; ?>

  <form method="POST">
    <div style="margin-bottom: 20px;">
      <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #334155;">Select Online Driver</label>
      <select name="driver_id" required style="width:100%; padding:12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 1rem; appearance: none; background-color: white;">
        <option value="">-- Choose Driver --</option>
        <?php if ($drivers_query->num_rows > 0): ?>
            <?php while($row = $drivers_query->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>">
                    ID: <?= $row['id'] ?> | <?= htmlspecialchars($row['name']) ?>
                </option>
            <?php endwhile; ?>
        <?php else: ?>
            <option value="" disabled>No drivers are currently online</option>
        <?php endif; ?>
      </select>
      <p style="font-size: 0.8rem; color: #10b981; margin-top: 6px;">
          <i class="fas fa-dot-circle"></i> Only drivers with <strong>status = 'online'</strong> are shown.
      </p>
    </div>

    <div style="display: flex; gap: 12px; margin-top: 30px;">
      <button type="submit" class="btn btn-primary" style="flex: 2; padding: 14px; border-radius: 10px; font-weight: bold; background: #2563eb; color: white; border: none; cursor: pointer;">
        Confirm Assignment
      </button>
      <a href="dashboard.php" class="btn btn-secondary" style="flex: 1; padding: 14px; border-radius: 10px; text-decoration: none; text-align: center; background: #f1f5f9; color: #475569; font-weight: 600;">
        Cancel
      </a>
    </div>
  </form>

  <hr style="margin: 30px 0; border: 0; border-top: 1px solid #e2e8f0;">

  <h4 style="margin-bottom: 12px; color: #1e293b;">Booking Details</h4>
  <div style="background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #f1f5f9;">
    <p style="margin: 5px 0;">📍 <strong>Pickup:</strong> <?= $booking['pickup_landmark'] ?></p>
    <p style="margin: 5px 0;">🏁 <strong>Drop-off:</strong> <?= $booking['dropoff_landmark'] ?></p>
    <p style="margin: 5px 0;">💰 <strong>Fare:</strong> ₱<?= number_format($booking['fare'], 2) ?></p>
  </div>
</div>

<?php include '../includes/footer.php'; ?>