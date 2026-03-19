<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
if ($_SESSION['role'] !== 'admin') header("Location: ../index.php");

// Stats
$total_bookings = $conn->query("SELECT COUNT(*) as cnt FROM bookings")->fetch_assoc()['cnt'];
$completed = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'COMPLETED'")->fetch_assoc()['cnt'];
$cancelled = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'CANCELLED'")->fetch_assoc()['cnt'];
$pending   = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'PENDING'")->fetch_assoc()['cnt'];

$today_completed = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'COMPLETED' AND DATE(dropoff_time) = CURDATE()")->fetch_assoc()['cnt'];

// Recent completed (last 10)
$recent = $conn->query("
  SELECT b.booking_code, b.status, b.dropoff_time, u.name AS passenger 
  FROM bookings b 
  JOIN users u ON b.passenger_id = u.id 
  WHERE b.status = 'COMPLETED' 
  ORDER BY b.dropoff_time DESC 
  LIMIT 10
");
?>
<?php include '../includes/header.php'; ?>

<style>
  /* Load the Custom Font */
  @font-face {
    font-family: 'NaruMonoDemo';
    src: url('../assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
  }

  /* Apply Font to everything inside the card */
  .reports-wrapper, 
  .reports-wrapper *,
  .stat-box,
  .stat-box h4,
  .stat-box p,
  table,
  th,
  td {
    font-family: 'NaruMonoDemo', monospace !important;
  }

  /* Stat box specific styling */
  .stat-box {
    text-align: center; 
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  }

  .stat-number {
    font-size: 2.8rem; 
    margin: 0.5rem 0;
    font-weight: bold;
    line-height: 1;
  }

  h2, h3 {
    text-transform: uppercase;
    letter-spacing: 1px;
  }
</style>

<div class="card reports-wrapper">
  <h2>System Reports</h2>

  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1.2rem; margin:2rem 0;">
    <div class="stat-box" style="background:#e0f2fe;">
      <h4>Total Bookings</h4>
      <p class="stat-number"><?= $total_bookings ?></p>
    </div>
    <div class="stat-box" style="background:#dcfce7;">
      <h4>Completed</h4>
      <p class="stat-number"><?= $completed ?></p>
    </div>
    <div class="stat-box" style="background:#fee2e2;">
      <h4>Cancelled</h4>
      <p class="stat-number"><?= $cancelled ?></p>
    </div>
    <div class="stat-box" style="background:#fef3c7;">
      <h4>Pending</h4>
      <p class="stat-number"><?= $pending ?></p>
    </div>
  </div>

  <h3>Today's Completed Trips</h3>
  <p style="font-size:1.4rem; margin-bottom:1.5rem;">Completed today: <strong><?= $today_completed ?></strong></p>

  <h3 style="margin-top: 2rem;">Recent Completed Bookings</h3>
  <?php if ($recent->num_rows > 0): ?>
  <div style="overflow-x: auto;">
    <table>
      <thead>
        <tr>
          <th>Code</th>
          <th>Passenger</th>
          <th>Completed At</th>
        </tr>
      </thead>
      <tbody>
      <?php while($row = $recent->fetch_assoc()): ?>
        <tr>
          <td><?= $row['booking_code'] ?></td>
          <td><?= htmlspecialchars($row['passenger']) ?></td>
          <td><?= date('M d, Y h:i A', strtotime($row['dropoff_time'])) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p>No completed trips yet.</p>
  <?php endif; ?>

  <p style="margin-top:3rem; text-align:center; opacity: 0.8;">
    <small>Note: Para sa full reports (PDF/Excel), pwede i-add sa future using libraries like Dompdf or PhpSpreadsheet.</small>
  </p>
</div>

<?php include '../includes/footer.php'; ?>