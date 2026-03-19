<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
if ($_SESSION['role'] !== 'admin') header("Location: ../index.php");

$bookings = $conn->query("
  SELECT b.*, u.name AS passenger_name 
  FROM bookings b 
  JOIN users u ON b.passenger_id = u.id 
  WHERE b.status IN ('COMPLETED', 'CANCELLED')
  ORDER BY b.created_at DESC 
  LIMIT 50
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
  .history-container, 
  .history-container h2, 
  .history-container table, 
  .history-container th, 
  .history-container td, 
  .history-container p {
    font-family: 'NaruMonoDemo', monospace !important;
  }

  /* Status Badge Styling */
  .status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: bold;
    text-transform: uppercase;
  }
  
  .status-completed {
    background-color: #dcfce7;
    color: #166534;
  }
  
  .status-cancelled {
    background-color: #fee2e2;
    color: #991b1b;
  }

  th {
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.05em;
    background-color: #f8fafc;
  }
</style>

<div class="card history-container">
  <h2>Booking History (Completed & Cancelled)</h2>

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
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
      <?php while($row = $bookings->fetch_assoc()): ?>
        <tr>
          <td style="font-weight: bold;"><?= $row['booking_code'] ?></td>
          <td><?= htmlspecialchars($row['passenger_name']) ?></td>
          <td style="font-size: 0.85rem;">
            <?= htmlspecialchars($row['pickup_landmark']) ?> 
            <span style="color: #64748b;">→</span> 
            <?= htmlspecialchars($row['dropoff_landmark']) ?>
          </td>
          <td>
            <span class="status-badge <?= $row['status'] === 'COMPLETED' ? 'status-completed' : 'status-cancelled' ?>">
              <?= $row['status'] ?>
            </span>
          </td>
          <td><?= htmlspecialchars($row['driver_name'] ?: '-') ?></td>
          <td style="font-size: 0.8rem; color: #475569;">
            <?= date('M d, Y h:i A', strtotime($row['created_at'])) ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p style="padding: 2rem; text-align: center;">No completed or cancelled bookings yet.</p>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>