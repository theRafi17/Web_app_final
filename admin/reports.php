<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Handle date range filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'revenue';

// Get revenue statistics
$revenue_sql = "SELECT
                SUM(p.amount) as total_revenue,
                COUNT(p.id) as total_transactions,
                AVG(p.amount) as average_transaction,
                SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount ELSE 0 END) as cash_revenue,
                SUM(CASE WHEN p.payment_method = 'card' THEN p.amount ELSE 0 END) as card_revenue,
                SUM(CASE WHEN p.payment_method = 'paypal' THEN p.amount ELSE 0 END) as paypal_revenue,
                SUM(CASE WHEN p.payment_method = 'bank_transfer' THEN p.amount ELSE 0 END) as bank_transfer_revenue
                FROM payments p
                WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                AND p.payment_status = 'paid'";

$revenue_stmt = $conn->prepare($revenue_sql);
$revenue_stmt->bind_param("ss", $date_from, $date_to);
$revenue_stmt->execute();
$revenue_stats = $revenue_stmt->get_result()->fetch_assoc();

// Get booking statistics
$booking_sql = "SELECT
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                AVG(TIMESTAMPDIFF(HOUR, start_time, end_time)) as avg_duration
                FROM bookings
                WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";

$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("ss", $date_from, $date_to);
$booking_stmt->execute();
$booking_stats = $booking_stmt->get_result()->fetch_assoc();

// Get revenue by spot type
$spot_revenue_sql = "SELECT
                    ps.type,
                    COUNT(b.id) as booking_count,
                    SUM(p.amount) as total_revenue,
                    AVG(p.amount) as avg_revenue
                    FROM payments p
                    JOIN bookings b ON p.booking_id = b.id
                    JOIN parking_spots ps ON b.spot_id = ps.id
                    WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                    AND p.payment_status = 'paid'
                    GROUP BY ps.type
                    ORDER BY total_revenue DESC";

$spot_revenue_stmt = $conn->prepare($spot_revenue_sql);
$spot_revenue_stmt->bind_param("ss", $date_from, $date_to);
$spot_revenue_stmt->execute();
$spot_revenue = $spot_revenue_stmt->get_result();

// Get monthly revenue data for chart
$monthly_revenue_sql = "SELECT
                        DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                        SUM(p.amount) as total_revenue
                        FROM payments p
                        WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                        AND p.payment_status = 'paid'
                        GROUP BY month
                        ORDER BY month ASC";

$monthly_revenue_result = $conn->query($monthly_revenue_sql);
$monthly_revenue_data = [];
$monthly_revenue_labels = [];

while ($row = $monthly_revenue_result->fetch_assoc()) {
    $monthly_revenue_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthly_revenue_data[] = $row['total_revenue'];
}

// Get daily revenue for the selected period
$daily_revenue_sql = "SELECT
                      DATE(p.payment_date) as day,
                      SUM(p.amount) as daily_revenue
                      FROM payments p
                      WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                      AND p.payment_status = 'paid'
                      GROUP BY day
                      ORDER BY day ASC";

$daily_revenue_stmt = $conn->prepare($daily_revenue_sql);
$daily_revenue_stmt->bind_param("ss", $date_from, $date_to);
$daily_revenue_stmt->execute();
$daily_revenue_result = $daily_revenue_stmt->get_result();

$daily_revenue_data = [];
$daily_revenue_labels = [];

while ($row = $daily_revenue_result->fetch_assoc()) {
    $daily_revenue_labels[] = date('M d', strtotime($row['day']));
    $daily_revenue_data[] = $row['daily_revenue'];
}

// Get most popular parking spots
$popular_spots_sql = "SELECT
                      ps.id, ps.spot_number, ps.floor_number, ps.type, ps.hourly_rate,
                      COUNT(b.id) as booking_count,
                      SUM(p.amount) as total_revenue
                      FROM parking_spots ps
                      LEFT JOIN bookings b ON ps.id = b.spot_id
                      LEFT JOIN payments p ON b.id = p.booking_id
                      WHERE (b.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) OR b.created_at IS NULL)
                      GROUP BY ps.id
                      ORDER BY booking_count DESC, total_revenue DESC
                      LIMIT 10";

$popular_spots_stmt = $conn->prepare($popular_spots_sql);
$popular_spots_stmt->bind_param("ss", $date_from, $date_to);
$popular_spots_stmt->execute();
$popular_spots = $popular_spots_stmt->get_result();

// Get message from session if any
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : '';
unset($_SESSION['message'], $_SESSION['message_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Smart Parking Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                            950: '#082f49',
                        },
                    },
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    },
                },
            },
        }
    </script>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-6 py-4">
                    <h1 class="text-2xl font-semibold text-gray-800">Reports & Analytics</h1>
                    <div class="flex gap-4">
                        <a href="generate-pdf-report.php?date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
                           class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                            <i class="fas fa-file-pdf"></i> Download PDF Report
                        </a>
                        <a href="profit-analysis.php" class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors flex items-center gap-2">
                            <i class="fas fa-chart-line"></i> Detailed Profit Analysis
                        </a>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <form action="" method="GET" class="flex flex-wrap gap-4 items-end">
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        </div>
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        </div>
                        <div>
                            <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                            <select id="report_type" name="report_type" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option value="revenue" <?php echo $report_type === 'revenue' ? 'selected' : ''; ?>>Revenue Analysis</option>
                                <option value="bookings" <?php echo $report_type === 'bookings' ? 'selected' : ''; ?>>Booking Analysis</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="bg-primary-100 text-primary-700 p-3 rounded-lg">
                                <i class="fas fa-money-bill-wave text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
                                <p class="text-2xl font-semibold text-gray-800">$<?php echo number_format($revenue_stats['total_revenue'] ?? 0, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="bg-green-100 text-green-700 p-3 rounded-lg">
                                <i class="fas fa-receipt text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Transactions</h3>
                                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($revenue_stats['total_transactions'] ?? 0); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="bg-blue-100 text-blue-700 p-3 rounded-lg">
                                <i class="fas fa-calendar-check text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Total Bookings</h3>
                                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($booking_stats['total_bookings'] ?? 0); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="bg-purple-100 text-purple-700 p-3 rounded-lg">
                                <i class="fas fa-clock text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Avg. Duration</h3>
                                <p class="text-2xl font-semibold text-gray-800"><?php echo round($booking_stats['avg_duration'] ?? 0, 1); ?> hrs</p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($report_type === 'revenue'): ?>
                <!-- Revenue Analysis -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Monthly Revenue Chart -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Monthly Revenue</h2>
                        <div class="h-80">
                            <canvas id="monthlyRevenueChart"></canvas>
                        </div>
                    </div>

                    <!-- Daily Revenue Chart -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Daily Revenue (Selected Period)</h2>
                        <div class="h-80">
                            <canvas id="dailyRevenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Revenue by Payment Method -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Revenue by Payment Method</h2>
                        <div class="h-80">
                            <canvas id="paymentMethodChart"></canvas>
                        </div>
                    </div>

                    <!-- Revenue by Spot Type -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Revenue by Spot Type</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spot Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bookings</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Revenue</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if ($spot_revenue->num_rows > 0): ?>
                                        <?php while($row = $spot_revenue->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo ucfirst($row['type']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo number_format($row['booking_count']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                $<?php echo number_format($row['total_revenue'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                $<?php echo number_format($row['avg_revenue'], 2); ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($report_type === 'bookings'): ?>
                <!-- Booking Analysis -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Booking Status Distribution -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Booking Status Distribution</h2>
                        <div class="h-80">
                            <canvas id="bookingStatusChart"></canvas>
                        </div>
                    </div>

                    <!-- Most Popular Parking Spots -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Most Popular Parking Spots</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spot</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Floor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bookings</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if ($popular_spots->num_rows > 0): ?>
                                        <?php while($row = $popular_spots->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo $row['spot_number']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $row['floor_number']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo ucfirst($row['type']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo number_format($row['booking_count']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                $<?php echo number_format($row['total_revenue'] ?? 0, 2); ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        // Monthly Revenue Chart
        const monthlyRevenueCtx = document.getElementById('monthlyRevenueChart');
        if (monthlyRevenueCtx) {
            new Chart(monthlyRevenueCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($monthly_revenue_labels); ?>,
                    datasets: [{
                        label: 'Monthly Revenue',
                        data: <?php echo json_encode($monthly_revenue_data); ?>,
                        backgroundColor: 'rgba(14, 165, 233, 0.2)',
                        borderColor: 'rgba(14, 165, 233, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Daily Revenue Chart
        const dailyRevenueCtx = document.getElementById('dailyRevenueChart');
        if (dailyRevenueCtx) {
            new Chart(dailyRevenueCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($daily_revenue_labels); ?>,
                    datasets: [{
                        label: 'Daily Revenue',
                        data: <?php echo json_encode($daily_revenue_data); ?>,
                        backgroundColor: 'rgba(14, 165, 233, 0.7)',
                        borderColor: 'rgba(14, 165, 233, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Payment Method Chart
        const paymentMethodCtx = document.getElementById('paymentMethodChart');
        if (paymentMethodCtx) {
            new Chart(paymentMethodCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Cash', 'Card', 'PayPal', 'Bank Transfer'],
                    datasets: [{
                        data: [
                            <?php echo $revenue_stats['cash_revenue'] ?? 0; ?>,
                            <?php echo $revenue_stats['card_revenue'] ?? 0; ?>,
                            <?php echo $revenue_stats['paypal_revenue'] ?? 0; ?>,
                            <?php echo $revenue_stats['bank_transfer_revenue'] ?? 0; ?>
                        ],
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.7)',
                            'rgba(14, 165, 233, 0.7)',
                            'rgba(168, 85, 247, 0.7)',
                            'rgba(249, 115, 22, 0.7)'
                        ],
                        borderColor: [
                            'rgba(34, 197, 94, 1)',
                            'rgba(14, 165, 233, 1)',
                            'rgba(168, 85, 247, 1)',
                            'rgba(249, 115, 22, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += '$' + context.raw.toFixed(2);
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Booking Status Chart
        const bookingStatusCtx = document.getElementById('bookingStatusChart');
        if (bookingStatusCtx) {
            new Chart(bookingStatusCtx, {
                type: 'pie',
                data: {
                    labels: ['Active', 'Completed', 'Cancelled'],
                    datasets: [{
                        data: [
                            <?php echo $booking_stats['active_bookings'] ?? 0; ?>,
                            <?php echo $booking_stats['completed_bookings'] ?? 0; ?>,
                            <?php echo $booking_stats['cancelled_bookings'] ?? 0; ?>
                        ],
                        backgroundColor: [
                            'rgba(14, 165, 233, 0.7)',
                            'rgba(34, 197, 94, 0.7)',
                            'rgba(239, 68, 68, 0.7)'
                        ],
                        borderColor: [
                            'rgba(14, 165, 233, 1)',
                            'rgba(34, 197, 94, 1)',
                            'rgba(239, 68, 68, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    </script>
</body>
</html>
