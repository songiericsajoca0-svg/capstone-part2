<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
if ($_SESSION['role'] !== 'admin') header("Location: ../index.php");

// Get statistics
$total_completed = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'COMPLETED'")->fetch_assoc()['cnt'];
$total_cancelled = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'CANCELLED'")->fetch_assoc()['cnt'];
$total_revenue = $conn->query("SELECT SUM(fare_amount) as total FROM bookings WHERE status = 'COMPLETED'")->fetch_assoc()['total'] ?? 0;

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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
  @font-face {
    font-family: 'NaruMonoDemo';
    src: url('../assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
  }

  * {
    font-family: 'NaruMonoDemo', monospace !important;
  }

  /* Keep Font Awesome icons with their original font */
  i, .fas, .far, .fab, .fa {
    font-family: "Font Awesome 6 Free" !important;
    font-weight: 400;
  }

  .fas, .fa-solid {
    font-weight: 900 !important;
  }

  .far {
    font-weight: 400 !important;
  }

  .fab, .fab i {
    font-family: "Font Awesome 6 Brands" !important;
  }

  body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
  }

  .history-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem;
  }

  /* Main Card */
  .main-card {
    background: white;
    border-radius: 28px;
    overflow: hidden;
    box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1);
    animation: slideUp 0.5s ease;
  }

  @keyframes slideUp {
    from {
      opacity: 0;
      transform: translateY(30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  /* Header */
  .card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
  }

  .card-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 1%);
    background-size: 50px 50px;
    animation: shimmer 60s linear infinite;
  }

  @keyframes shimmer {
    0% { transform: translate(0, 0); }
    100% { transform: translate(50px, 50px); }
  }

  .card-header h2 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: bold;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .card-header p {
    margin: 0.5rem 0 0;
    opacity: 0.9;
    position: relative;
    z-index: 1;
  }

  /* Stats Grid */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    padding: 2rem;
    border-bottom: 1px solid #e2e8f0;
  }

  .stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    position: relative;
    overflow: hidden;
    cursor: pointer;
  }

  .stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    transition: height 0.3s ease;
  }

  .stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.12);
  }

  .stat-card:hover::before {
    height: 6px;
  }

  .stat-card.completed::before { background: #10b981; }
  .stat-card.cancelled::before { background: #ef4444; }
  .stat-card.revenue::before { background: #f59e0b; }

  .stat-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
  }

  .stat-card.completed .stat-icon { background: #d1fae5; color: #10b981; }
  .stat-card.cancelled .stat-icon { background: #fee2e2; color: #ef4444; }
  .stat-card.revenue .stat-icon { background: #fef3c7; color: #f59e0b; }

  .stat-value {
    font-size: 2rem;
    font-weight: bold;
    margin: 0.5rem 0;
  }

  .stat-label {
    font-size: 0.7rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .stat-card.completed .stat-value { color: #10b981; }
  .stat-card.cancelled .stat-value { color: #ef4444; }
  .stat-card.revenue .stat-value { color: #f59e0b; }

  /* Table Section */
  .table-section {
    padding: 2rem;
  }

  .section-title {
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 1.5rem;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .section-title i {
    color: #667eea;
  }

  .history-table {
    width: 100%;
    border-collapse: collapse;
  }

  .history-table th {
    text-align: left;
    padding: 1rem;
    background: #f8fafc;
    font-weight: bold;
    color: #4b5563;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
  }

  .history-table th i {
    margin-right: 0.5rem;
    font-size: 0.7rem;
    color: #667eea;
  }

  .history-table td {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
    font-size: 0.85rem;
  }

  .history-table tr {
    transition: all 0.3s ease;
  }

  .history-table tr:hover {
    background: #f8fafc;
  }

  /* Status Badge */
  .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.7rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .status-completed {
    background: #d1fae5;
    color: #065f46;
  }

  .status-cancelled {
    background: #fee2e2;
    color: #991b1b;
  }

  .booking-code {
    font-weight: bold;
    color: #667eea;
  }

  .route-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
  }

  .route-arrow {
    color: #9ca3af;
    font-size: 0.7rem;
  }

  .driver-info {
    display: flex;
    align-items: center;
    gap: 0.3rem;
  }

  .driver-info i {
    color: #667eea;
    font-size: 0.7rem;
  }

  .date-info {
    font-size: 0.75rem;
    color: #6b7280;
  }

  .date-info i {
    margin-right: 0.2rem;
    font-size: 0.65rem;
  }

  .empty-state {
    text-align: center;
    padding: 3rem;
    color: #94a3b8;
  }

  .empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
  }

  @media (max-width: 768px) {
    .history-container {
      padding: 1rem;
    }
    
    .stats-grid {
      padding: 1rem;
      gap: 1rem;
    }
    
    .table-section {
      padding: 1rem;
      overflow-x: auto;
    }
    
    .stat-value {
      font-size: 1.5rem;
    }
    
    .stat-icon {
      width: 45px;
      height: 45px;
      font-size: 1.2rem;
    }
  }
</style>

<div class="history-container">
  <div class="main-card">
    <div class="card-header">
      <h2>
        <i class="fas fa-history"></i>
        Booking History
      </h2>
      <p>View all completed and cancelled trips</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card completed" onclick="window.location.href='?status=COMPLETED'">
        <div class="stat-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value"><?= $total_completed ?></div>
        <div class="stat-label">Completed Trips</div>
      </div>
      <div class="stat-card cancelled" onclick="window.location.href='?status=CANCELLED'">
        <div class="stat-icon">
          <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-value"><?= $total_cancelled ?></div>
        <div class="stat-label">Cancelled Trips</div>
      </div>
      <div class="stat-card revenue">
        <div class="stat-icon">
          <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-value">₱<?= number_format($total_revenue, 2) ?></div>
        <div class="stat-label">Total Revenue</div>
      </div>
    </div>

    <!-- Table Section -->
    <div class="table-section">
      <div class="section-title">
        <i class="fas fa-list-ul"></i>
        Recent Transactions
      </div>

      <?php if ($bookings->num_rows > 0): ?>
        <div style="overflow-x: auto;">
          <table class="history-table">
            <thead>
              <tr>
                <th><i class="fas fa-qrcode"></i> Booking Code</th>
                <th><i class="fas fa-user"></i> Passenger</th>
                <th><i class="fas fa-route"></i> Route</th>
                <th><i class="fas fa-tag"></i> Status</th>
                <th><i class="fas fa-user-circle"></i> Driver</th>
                <th><i class="fas fa-calendar"></i> Created</th>
               </tr>
            </thead>
            <tbody>
            <?php while($row = $bookings->fetch_assoc()): ?>
              <tr>
                <td class="booking-code"><?= htmlspecialchars($row['booking_code']) ?></td>
                <td>
                  <div class="driver-info">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($row['passenger_name']) ?>
                  </div>
                </td>
                <td>
                  <div class="route-info">
                    <span><?= htmlspecialchars($row['pickup_landmark']) ?></span>
                    <span class="route-arrow"><i class="fas fa-arrow-right"></i></span>
                    <span><?= htmlspecialchars($row['dropoff_landmark']) ?></span>
                  </div>
                </td>
                <td>
                  <span class="status-badge <?= $row['status'] === 'COMPLETED' ? 'status-completed' : 'status-cancelled' ?>">
                    <i class="fas fa-<?= $row['status'] === 'COMPLETED' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= $row['status'] ?>
                  </span>
                </td>
                <td>
                  <?php if (!empty($row['driver_name'])): ?>
                    <div class="driver-info">
                      <i class="fas fa-user-circle"></i>
                      <?= htmlspecialchars($row['driver_name']) ?>
                    </div>
                  <?php else: ?>
                    <span style="color: #9ca3af;">
                      <i class="fas fa-user-slash"></i> Unassigned
                    </span>
                  <?php endif; ?>
                </td>
                <td class="date-info">
                  <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($row['created_at'])) ?><br>
                  <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($row['created_at'])) ?>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-inbox"></i>
          <h3>No bookings found</h3>
          <p>No completed or cancelled bookings yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>