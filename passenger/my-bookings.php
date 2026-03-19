<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/functions.php';  // para sa generate_csrf_token()

$user_id = intval($_SESSION['user_id']);

// --- SEARCH LOGIC START ---
$search_code = $_GET['search_code'] ?? '';
$search_date = $_GET['search_date'] ?? '';

$query = "SELECT * FROM bookings WHERE passenger_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search_code)) {
    $query .= " AND booking_code LIKE ?";
    $params[] = "%$search_code%";
    $types .= "s";
}

if (!empty($search_date)) {
    $query .= " AND DATE(created_at) = ?";
    $params[] = $search_date;
    $types .= "s";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result();
// --- SEARCH LOGIC END ---
?>

<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="card">
        <h2>My Bookings</h2>
        
        <p style="color:#555; margin-bottom:1.5rem;">
            View and manage all your tricycle booking requests.
        </p>

        <!-- Search Form -->
        <div class="card" style="background:#f8fafc; border:1px solid #e2e8f0; margin-bottom:2rem; padding:1.5rem;">
            <form method="GET" action="" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                <div style="flex:1; min-width:180px;">
                    <label style="display:block; font-weight:500; margin-bottom:0.5rem; color:#4b5563;">
                        Booking Code
                    </label>
                    <input type="text" 
                           name="search_code" 
                           value="<?= htmlspecialchars($search_code) ?>" 
                           placeholder="e.g. BK250101123456" 
                           style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:6px; font-size:1rem;">
                </div>

                <div style="flex:1; min-width:180px;">
                    <label style="display:block; font-weight:500; margin-bottom:0.5rem; color:#4b5563;">
                        Date
                    </label>
                    <input type="date" 
                           name="search_date" 
                           value="<?= htmlspecialchars($search_date) ?>" 
                           style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:6px; font-size:1rem;">
                </div>

                <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary" style="padding:0.75rem 1.5rem;">
                        Search
                    </button>
                    <a href="my-bookings.php" 
                       class="btn btn-secondary" 
                       style="padding:0.75rem 1.5rem; background:#6b7280; color:white;">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Messages -->
        <?php if (isset($_GET['msg'])): ?>
            <?php 
            $msg_type = $_GET['type'] ?? 'success';
            $color = ($msg_type === 'success') ? '#15803d' : '#b91c1c';
            $bg = ($msg_type === 'success') ? '#dcfce7' : '#fee2e2';
            ?>
            <div style="background:<?= $bg ?>; color:<?= $color ?>; padding:1rem; border-radius:6px; margin-bottom:1.5rem; text-align:center; font-weight:500;">
                <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <!-- Table -->
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Route</th>
                        <th>Status</th>
                        <th>Driver</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($bookings->num_rows > 0): ?>
                        <?php while ($row = $bookings->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight:600; color:#1e40af;">
                                    <?= htmlspecialchars($row['booking_code']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['pickup_landmark']) ?> 
                                    <span style="color:#6b7280;">→</span> 
                                    <?= htmlspecialchars($row['dropoff_landmark']) ?>
                                </td>
                                <td>
                                    <?php
                                    $status = $row['status'];
                                    $status_color = match($status) {
                                        'PENDING'           => '#d97706',
                                        'ASSIGNED'          => '#7c3aed',
                                        'PASSENGER PICKED UP' => '#059669',
                                        'IN TRANSIT'        => '#2563eb',
                                        'COMPLETED'         => '#15803d',
                                        'CANCELLED'         => '#b91c1c',
                                        default             => '#6b7280',
                                    };
                                    ?>
                                    <strong style="color:<?= $status_color ?>;">
                                        <?= htmlspecialchars($status) ?>
                                    </strong>
                                </td>
                                <td><?= htmlspecialchars($row['driver_name'] ?: '—') ?></td>
                                <td><?= date('M d, Y<br>h:i A', strtotime($row['created_at'])) ?></td>
                                <td style="white-space:nowrap;">
                                    <a href="qr.php?id=<?= $row['id'] ?>" 
                                       class="btn btn-primary" 
                                       style="padding:0.5rem 1rem; font-size:0.9rem;">
                                        View Details
                                    </a>

                                    <?php if ($row['status'] === 'PENDING'): ?>
                                        <form method="POST" action="../cancel.php" style="display:inline; margin-left:0.75rem;">
                                            <input type="hidden" name="booking_id" value="<?= $row['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="redirect" value="my-bookings.php">
                                            <button type="submit" 
                                                    class="btn btn-danger" 
                                                    onclick="return confirm('Cancel this booking?')"
                                                    style="padding:0.5rem 1rem; font-size:0.9rem;">
                                                Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:3rem 1rem; color:#6b7280; font-size:1.1rem;">
                                <?php if (!empty($search_code) || !empty($search_date)): ?>
                                    No bookings found matching your search criteria.
                                <?php else: ?>
                                    You don't have any bookings yet.<br>
                                    <a href="create-booking.php" style="color:#1d4ed8; font-weight:500;">Create your first booking now</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>