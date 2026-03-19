<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['role'] !== 'admin') header("Location: ../index.php");

// Pending bookings
$pending = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM bookings 
    WHERE status = 'PENDING'
")->fetch_assoc()['cnt'];

// Active bookings (ASSIGNED + PICKED UP + IN TRANSIT)
$active = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM bookings 
    WHERE status IN ('ASSIGNED','PASSENGER PICKED UP','IN TRANSIT')
")->fetch_assoc()['cnt'];

// Completed today
$completed_today = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM bookings 
    WHERE status = 'COMPLETED' 
    AND DATE(dropoff_time) = CURDATE()
")->fetch_assoc()['cnt'];

// Recent active bookings
$bookings = $conn->query("
  SELECT b.*, u.name AS passenger_name 
  FROM bookings b 
  JOIN users u ON b.passenger_id = u.id 
  WHERE b.status IN ('PENDING','ASSIGNED','PASSENGER PICKED UP','IN TRANSIT')
  ORDER BY b.created_at DESC
  LIMIT 20
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

  /* Apply Font specifically to this dashboard's content */
  .dashboard-container, 
  .dashboard-container *,
  .card,
  .card h2,
  .card h3,
  table,
  th,
  td,
  .btn {
    font-family: 'NaruMonoDemo', monospace !important;
  }

  /* Maintain layout styles */
  .stats-grid {
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
    gap: 1rem; 
    margin: 1.5rem 0;
  }
  
  .stat-card {
    text-align: center;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  }
</style>

<div class="card dashboard-container">
  <h2>Admin Dashboard</h2>

  <div class="stats-grid">
    <div class="stat-card" style="background:#fef3c7;">
      <h3>Pending</h3>
      <p style="font-size:2.5rem; margin:0; font-weight: bold;"><?= $pending ?></p>
    </div>

    <div class="stat-card" style="background:#bbf7d0;">
      <h3>Active Trips</h3>
      <p style="font-size:2.5rem; margin:0; font-weight: bold;"><?= $active ?></p>
    </div>

    <div class="stat-card" style="background:#fecaca;">
      <h3>Completed Today</h3>
      <p style="font-size:2.5rem; margin:0; font-weight: bold;"><?= $completed_today ?></p>
    </div>
  </div>

  <?php
  if(isset($_GET['msg'])) echo "<p style='color:green'>".htmlspecialchars($_GET['msg'])."</p>";
  if(isset($_GET['error'])) echo "<p style='color:red'>".htmlspecialchars($_GET['error'])."</p>";
  ?>

  <h3 style="margin-top: 2rem;">Recent / Active Bookings</h3>

  <?php if ($bookings->num_rows > 0): ?>
  <div style="overflow-x: auto;">
    <table>
      <thead>
        <tr>
          <th>Code</th>
          <th>Passenger</th>
          <th>Pickup → Drop-off</th>
          <th>Status</th>
          <th>Driver</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
      <?php while($row = $bookings->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['booking_code']) ?></td>

          <td><?= htmlspecialchars($row['passenger_name']) ?></td>

          <td>
            <?= htmlspecialchars($row['pickup_landmark']) ?>
            →
            <?= htmlspecialchars($row['dropoff_landmark']) ?>
          </td>

          <td><strong><?= htmlspecialchars($row['status']) ?></strong></td>

          <td><?= htmlspecialchars($row['driver_name'] ?: '-') ?></td>

          <td>
            <?php if ($row['status'] === 'PENDING'): ?>
              <a href="assign.php?id=<?= $row['id'] ?>" 
                 class="btn btn-primary" 
                 style="padding:6px 12px; font-size:0.9rem;">
                 Assign
              </a>
            <?php endif; ?>

            <?php if (in_array($row['status'], ['PENDING','ASSIGNED'])): ?>
              <form method="POST" action="../cancel.php" style="display:inline;">
                <input type="hidden" name="booking_id" value="<?= $row['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="redirect" value="dashboard.php">

                <button type="submit" class="btn btn-danger"
                        style="padding:6px 10px; font-size:0.85rem;"
                        onclick="return confirm('Cancel booking?')">
                        Cancel
                </button>
              </form>
            <?php endif; ?>

            <?php if ($row['status'] === 'PASSENGER PICKED UP'): ?>
              <a href="update_status.php?id=<?= $row['id'] ?>&status=IN TRANSIT"
                 class="btn btn-primary"
                 style="padding:6px 12px; font-size:0.9rem;">
                 Set In Transit
              </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <?php else: ?>
    <p>No pending or active bookings at the moment.</p>
  <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>