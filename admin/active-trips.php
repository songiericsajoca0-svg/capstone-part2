<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
if ($_SESSION['role'] !== 'admin') header("Location: ../");

$active = $conn->query("
  SELECT b.*, u.name AS passenger_name 
  FROM bookings b 
  JOIN users u ON b.passenger_id = u.id 
  WHERE b.status IN ('ASSIGNED', 'PASSENGER PICKED UP', 'IN TRANSIT')
  ORDER BY b.created_at DESC
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

  /* Apply Font to Card and all its contents */
  .card, 
  .card h2, 
  .card table, 
  .card th, 
  .card td, 
  .card p,
  .card script + p {
    font-family: 'NaruMonoDemo', monospace !important;
  }

  th {
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.05em;
  }
</style>

<div class="card">
  <h2>Active / Ongoing Trips</h2>

  <?php if ($active->num_rows > 0): ?>
  <div style="overflow-x: auto;">
    <table>
      <thead>
        <tr>
          <th>Code</th>
          <th>Passenger</th>
          <th>Pickup → Drop-off</th>
          <th>Status</th>
          <th>Driver</th>
          <th>Pickup Time</th>
        </tr>
      </thead>
      <tbody>
      <?php while($row = $active->fetch_assoc()): ?>
        <tr>
          <td><?= $row['booking_code'] ?></td>
          <td><?= htmlspecialchars($row['passenger_name']) ?></td>
          <td><?= htmlspecialchars($row['pickup_landmark']) ?> → <?= htmlspecialchars($row['dropoff_landmark']) ?></td>
          <td><strong><?= $row['status'] ?></strong></td>
          <td><?= htmlspecialchars($row['driver_name'] ?: '-') ?></td>
          <td><?= $row['pickup_time'] ? date('h:i A', strtotime($row['pickup_time'])) : '-' ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <p style="margin-top:1.5rem; font-style:italic; font-size: 0.9rem;">
    Auto-refresh every 30 seconds? <input type="checkbox" id="autorefresh"> (for demo purposes)
  </p>

  <script>
    document.getElementById('autorefresh').addEventListener('change', function() {
      if (this.checked) {
        setInterval(() => location.reload(), 30000);
      }
    });
  </script>
  <?php else: ?>
    <p style="padding:2rem; text-align:center;">Empty.</p>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>