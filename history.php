<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Get booking history with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total bookings count
$count_sql = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$total_bookings = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_bookings / $per_page);

// Get bookings for current page
$bookings_sql = "SELECT b.*, p.spot_number, p.floor_number, p.type
                 FROM bookings b
                 JOIN parking_spots p ON b.spot_id = p.id
                 WHERE b.user_id = ?
                 ORDER BY b.created_at DESC
                 LIMIT ? OFFSET ?";
$bookings_stmt = $conn->prepare($bookings_sql);
$bookings_stmt->bind_param("iii", $user_id, $per_page, $offset);
$bookings_stmt->execute();
$bookings = $bookings_stmt->get_result();

// Calculate statistics
$stats_sql = "SELECT
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(CASE WHEN status != 'cancelled' THEN amount ELSE 0 END) as total_amount
              FROM bookings
              WHERE user_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History - Smart Parking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <header>
                <div class="header-content">
                    <h1>Booking History</h1>
                    <div class="user-info">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random" alt="User Avatar">
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                </div>
            </header>

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <i class="fas fa-ticket-alt"></i>
                    <div class="stat-info">
                        <h3>Total Bookings</h3>
                        <p><?php echo $stats['total_bookings']; ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-info">
                        <h3>Completed</h3>
                        <p><?php echo $stats['completed_bookings']; ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-times-circle"></i>
                    <div class="stat-info">
                        <h3>Cancelled</h3>
                        <p><?php echo $stats['cancelled_bookings']; ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-dollar-sign"></i>
                    <div class="stat-info">
                        <h3>Total Spent</h3>
                        <p>$<?php echo number_format($stats['total_amount'], 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Booking History Table -->
            <div class="history-section">
                <div class="history-header">
                    <h2>Recent Bookings</h2>
                    <div class="history-filters">
                        <select id="statusFilter" onchange="filterBookings()">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="history-table-container">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Spot</th>
                                <th>Vehicle</th>
                                <th>Duration</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($bookings->num_rows > 0): ?>
                                <?php while ($booking = $bookings->fetch_assoc()): ?>
                                <tr class="booking-row" data-status="<?php echo $booking['status']; ?>">
                                    <td>#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td>
                                        <div class="spot-info">
                                            <span class="spot-number">Spot <?php echo htmlspecialchars($booking['spot_number']); ?></span>
                                            <span class="floor-number">Floor <?php echo $booking['floor_number']; ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['vehicle_number']); ?></td>
                                    <td>
                                        <div class="duration-info">
                                            <div><?php echo date('M d, H:i', strtotime($booking['start_time'])); ?></div>
                                            <div><?php echo date('M d, H:i', strtotime($booking['end_time'])); ?></div>
                                        </div>
                                    </td>
                                    <td>$<?php echo number_format($booking['amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data">No booking history found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1); ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function filterBookings() {
        const status = document.getElementById('statusFilter').value;
        const rows = document.querySelectorAll('.booking-row');

        rows.forEach(row => {
            if (status === 'all' || row.dataset.status === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    </script>
</body>
</html>
