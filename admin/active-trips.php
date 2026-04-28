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

// Get counts for each status
$assigned_count = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'ASSIGNED'")->fetch_assoc()['cnt'];
$picked_up_count = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'PASSENGER PICKED UP'")->fetch_assoc()['cnt'];
$in_transit_count = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'IN TRANSIT'")->fetch_assoc()['cnt'];
$total_active = $assigned_count + $picked_up_count + $in_transit_count;
?>
<?php include '../includes/header.php'; ?>

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

  body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
  }

  .trips-container {
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

  /* Stats Badges */
  .stats-badges {
    display: flex;
    gap: 1rem;
    padding: 1.5rem 2rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    flex-wrap: wrap;
  }

  .stat-badge {
    flex: 1;
    min-width: 120px;
    padding: 1rem;
    border-radius: 16px;
    text-align: center;
    background: white;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
  }

  .stat-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
  }

  .stat-badge .count {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 0;
  }

  .stat-badge .label {
    font-size: 0.7rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.25rem;
  }

  .stat-badge.total .count { color: #667eea; }
  .stat-badge.assigned .count { color: #f59e0b; }
  .stat-badge.picked .count { color: #10b981; }
  .stat-badge.transit .count { color: #3b82f6; }

  /* Table Section */
  .table-section {
    padding: 2rem;
  }

  .table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
  }

  .section-title {
    font-size: 1.2rem;
    font-weight: bold;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .section-title i {
    color: #667eea;
  }

  .auto-refresh-control {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #f1f5f9;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
  }

  .auto-refresh-control input {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #667eea;
  }

  .auto-refresh-control label {
    cursor: pointer;
    color: #475569;
  }

  /* Table */
  .trips-table {
    width: 100%;
    border-collapse: collapse;
  }

  .trips-table th {
    text-align: left;
    padding: 1rem;
    background: #f8fafc;
    font-weight: bold;
    color: #4b5563;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
  }

  .trips-table td {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
    font-size: 0.85rem;
  }

  .trips-table tr {
    transition: all 0.3s ease;
    animation: fadeInRow 0.3s ease forwards;
  }

  @keyframes fadeInRow {
    from {
      opacity: 0;
      transform: translateX(-10px);
    }
    to {
      opacity: 1;
      transform: translateX(0);
    }
  }

  .trips-table tr:hover {
    background: #f8fafc;
    transform: translateX(3px);
  }

  /* Status Badges */
  .status-badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .status-assigned { 
    background: #fef3c7; 
    color: #f59e0b; 
  }
  .status-passenger_picked_up { 
    background: #d1fae5; 
    color: #10b981; 
  }
  .status-in_transit { 
    background: #dbeafe; 
    color: #3b82f6; 
  }

  .booking-code {
    font-weight: bold;
    color: #667eea;
    font-size: 0.85rem;
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
    gap: 0.5rem;
  }

  .driver-info i {
    color: #667eea;
    font-size: 0.8rem;
  }

  .time-info {
    font-size: 0.8rem;
    color: #6b7280;
  }

  .empty-state {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
  }

  .empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
  }

  @media (max-width: 768px) {
    .trips-container {
      padding: 1rem;
    }
    
    .table-section {
      padding: 1rem;
      overflow-x: auto;
    }
    
    .stats-badges {
      padding: 1rem;
      gap: 0.5rem;
    }
    
    .stat-badge {
      min-width: 80px;
      padding: 0.75rem;
    }
    
    .stat-badge .count {
      font-size: 1.2rem;
    }
    
    .table-header {
      flex-direction: column;
      align-items: flex-start;
    }
    
    .trips-table th, .trips-table td {
      padding: 0.75rem;
    }
  }
</style>

<div class="trips-container">
  <div class="main-card">
    <div class="card-header">
      <h2>
        <i class="fas fa-truck-moving"></i> 
        Active / Ongoing Trips
      </h2>
      <p>Monitor and track all active trips in real-time</p>
    </div>

    <!-- Stats Badges -->
    <div class="stats-badges">
      <div class="stat-badge total">
        <div class="count"><?= $total_active ?></div>
        <div class="label">Total Active</div>
      </div>
      <div class="stat-badge assigned">
        <div class="count"><?= $assigned_count ?></div>
        <div class="label">Assigned</div>
      </div>
      <div class="stat-badge picked">
        <div class="count"><?= $picked_up_count ?></div>
        <div class="label">Picked Up</div>
      </div>
      <div class="stat-badge transit">
        <div class="count"><?= $in_transit_count ?></div>
        <div class="label">In Transit</div>
      </div>
    </div>

    <div class="table-section">
      <div class="table-header">
        <div class="section-title">
          <i class="fas fa-list"></i>
          Active Trip List
        </div>
        <div class="auto-refresh-control">
          <input type="checkbox" id="autorefresh">
          <label for="autorefresh">
            <i class="fas fa-sync-alt"></i> Auto-refresh every 30 seconds
          </label>
        </div>
      </div>

      <?php if ($active->num_rows > 0): ?>
        <div style="overflow-x: auto;">
          <table class="trips-table">
            <thead>
              <tr>
                <th><i class="fas fa-qrcode"></i> Booking Code</th>
                <th><i class="fas fa-user"></i> Passenger</th>
                <th><i class="fas fa-route"></i> Route</th>
                <th><i class="fas fa-tag"></i> Status</th>
                <th><i class="fas fa-user-circle"></i> Driver</th>
                <th><i class="fas fa-clock"></i> Pickup Time</th>
               </tr>
            </thead>
            <tbody>
            <?php while($row = $active->fetch_assoc()): ?>
              <?php 
                $status_key = strtolower(str_replace(' ', '_', $row['status']));
                $status_class = 'status-' . $status_key;
                
                $status_display = '';
                if ($row['status'] == 'ASSIGNED') $status_display = 'Assigned';
                elseif ($row['status'] == 'PASSENGER PICKED UP') $status_display = 'Picked Up';
                elseif ($row['status'] == 'IN TRANSIT') $status_display = 'In Transit';
                else $status_display = $row['status'];
              ?>
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
                  <span class="status-badge <?= $status_class ?>">
                    <?= $status_display ?>
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
                <td class="time-info">
                  <?php if (!empty($row['pickup_time'])): ?>
                    <i class="far fa-calendar-alt"></i> 
                    <?= date('M d, Y', strtotime($row['pickup_time'])) ?><br>
                    <i class="far fa-clock"></i> 
                    <?= date('h:i A', strtotime($row['pickup_time'])) ?>
                  <?php else: ?>
                    <span style="color: #9ca3af;">
                      <i class="far fa-clock"></i> Not set
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>

        <p style="margin-top: 1.5rem; font-size: 0.75rem; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
          <i class="fas fa-info-circle"></i> Showing all active trips. Page auto-refreshes when enabled.
        </p>

        <script>
          let refreshInterval = null;
          
          document.getElementById('autorefresh').addEventListener('change', function() {
            if (this.checked) {
              refreshInterval = setInterval(() => {
                location.reload();
              }, 30000);
              // Show notification
              const control = document.querySelector('.auto-refresh-control');
              control.style.background = '#dbeafe';
              setTimeout(() => {
                control.style.background = '#f1f5f9';
              }, 1000);
            } else {
              if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
              }
            }
          });
        </script>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-check-circle"></i>
          <p style="margin-top: 0.5rem;">No active trips at the moment.</p>
          <p style="font-size: 0.8rem; color: #9ca3af;">All trips are either completed or pending assignment.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>