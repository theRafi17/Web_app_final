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
$group_by = isset($_GET['group_by']) ? $_GET['group_by'] : 'daily';

// Get total profit
$profit_sql = "SELECT 
                SUM(p.amount) as total_revenue,
                COUNT(DISTINCT b.id) as total_bookings,
                COUNT(DISTINCT p.id) as total_transactions,
                AVG(p.amount) as avg_transaction_value,
                SUM(p.amount) / COUNT(DISTINCT b.id) as revenue_per_booking
              FROM payments p
              JOIN bookings b ON p.booking_id = b.id
              WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
              AND p.payment_status = 'paid'";

$profit_stmt = $conn->prepare($profit_sql);
$profit_stmt->bind_param("ss", $date_from, $date_to);
$profit_stmt->execute();
$profit_stats = $profit_stmt->get_result()->fetch_assoc();

// Get profit by spot type
$spot_profit_sql = "SELECT 
                    ps.type,
                    COUNT(DISTINCT b.id) as booking_count,
                    SUM(p.amount) as total_revenue,
                    AVG(p.amount) as avg_revenue,
                    SUM(p.amount) / COUNT(DISTINCT b.id) as revenue_per_booking
                  FROM payments p
                  JOIN bookings b ON p.booking_id = b.id
                  JOIN parking_spots ps ON b.spot_id = ps.id
                  WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                  AND p.payment_status = 'paid'
                  GROUP BY ps.type
                  ORDER BY total_revenue DESC";

$spot_profit_stmt = $conn->prepare($spot_profit_sql);
$spot_profit_stmt->bind_param("ss", $date_from, $date_to);
$spot_profit_stmt->execute();
$spot_profit = $spot_profit_stmt->get_result();

// Get profit by floor
$floor_profit_sql = "SELECT 
                     ps.floor_number,
                     COUNT(DISTINCT b.id) as booking_count,
                     SUM(p.amount) as total_revenue,
                     AVG(p.amount) as avg_revenue,
                     SUM(p.amount) / COUNT(DISTINCT b.id) as revenue_per_booking
                   FROM payments p
                   JOIN bookings b ON p.booking_id = b.id
                   JOIN parking_spots ps ON b.spot_id = ps.id
                   WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                   AND p.payment_status = 'paid'
                   GROUP BY ps.floor_number
                   ORDER BY ps.floor_number ASC";

$floor_profit_stmt = $conn->prepare($floor_profit_sql);
$floor_profit_stmt->bind_param("ss", $date_from, $date_to);
$floor_profit_stmt->execute();
$floor_profit = $floor_profit_stmt->get_result();

// Get profit by payment method
$payment_profit_sql = "SELECT 
                       p.payment_method,
                       COUNT(p.id) as transaction_count,
                       SUM(p.amount) as total_revenue,
                       AVG(p.amount) as avg_revenue
                     FROM payments p
                     WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                     AND p.payment_status = 'paid'
                     GROUP BY p.payment_method
                     ORDER BY total_revenue DESC";

$payment_profit_stmt = $conn->prepare($payment_profit_sql);
$payment_profit_stmt->bind_param("ss", $date_from, $date_to);
$payment_profit_stmt->execute();
$payment_profit = $payment_profit_stmt->get_result();

// Get time-based profit data based on group_by parameter
$time_sql = "";
$time_format = "";

switch ($group_by) {
    case 'hourly':
        $time_sql = "SELECT 
                     HOUR(p.payment_date) as time_period,
                     COUNT(p.id) as transaction_count,
                     SUM(p.amount) as total_revenue
                   FROM payments p
                   WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                   AND p.payment_status = 'paid'
                   GROUP BY time_period
                   ORDER BY time_period ASC";
        $time_format = "h A"; // Hour with AM/PM
        break;
    case 'daily':
        $time_sql = "SELECT 
                     DATE(p.payment_date) as time_period,
                     COUNT(p.id) as transaction_count,
                     SUM(p.amount) as total_revenue
                   FROM payments p
                   WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                   AND p.payment_status = 'paid'
                   GROUP BY time_period
                   ORDER BY time_period ASC";
        $time_format = "M d, Y"; // Month day, Year
        break;
    case 'weekly':
        $time_sql = "SELECT 
                     YEARWEEK(p.payment_date, 1) as time_period_raw,
                     DATE_FORMAT(DATE_ADD(p.payment_date, INTERVAL(1-DAYOFWEEK(p.payment_date)) DAY), '%Y-%m-%d') as time_period,
                     COUNT(p.id) as transaction_count,
                     SUM(p.amount) as total_revenue
                   FROM payments p
                   WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                   AND p.payment_status = 'paid'
                   GROUP BY time_period_raw, time_period
                   ORDER BY time_period_raw ASC";
        $time_format = "\\W\\e\\e\\k \\o\\f M d, Y"; // Week of Month day, Year
        break;
    case 'monthly':
        $time_sql = "SELECT 
                     DATE_FORMAT(p.payment_date, '%Y-%m-01') as time_period,
                     COUNT(p.id) as transaction_count,
                     SUM(p.amount) as total_revenue
                   FROM payments p
                   WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                   AND p.payment_status = 'paid'
                   GROUP BY time_period
                   ORDER BY time_period ASC";
        $time_format = "M Y"; // Month Year
        break;
}

$time_profit_stmt = $conn->prepare($time_sql);
$time_profit_stmt->bind_param("ss", $date_from, $date_to);
$time_profit_stmt->execute();
$time_profit = $time_profit_stmt->get_result();

$time_labels = [];
$time_data = [];

while ($row = $time_profit->fetch_assoc()) {
    if ($group_by === 'hourly') {
        // Format hour with AM/PM
        $hour = $row['time_period'];
        $formatted_time = date($time_format, strtotime("$hour:00"));
        $time_labels[] = $formatted_time;
    } else {
        $time_labels[] = date($time_format, strtotime($row['time_period']));
    }
    $time_data[] = $row['total_revenue'];
}

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
    <title>Profit Analysis - Smart Parking Admin</title>
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
                    <h1 class="text-2xl font-semibold text-gray-800">Profit Analysis</h1>
                    <a href="reports.php" class="text-primary-600 hover:text-primary-800 flex items-center gap-2">
                        <i class="fas fa-arrow-left"></i> Back to Reports
                    </a>
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
                            <label for="group_by" class="block text-sm font-medium text-gray-700 mb-1">Group By</label>
                            <select id="group_by" name="group_by" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option value="hourly" <?php echo $group_by === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                <option value="daily" <?php echo $group_by === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo $group_by === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo $group_by === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Profit Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="bg-primary-100 text-primary-700 p-3 rounded-lg">
                                <i class="fas fa-money-bill-wave text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
                                <p class="text-2xl font-semibold text-gray-800">$<?php echo number_format($profit_stats['total_revenue'] ?? 0, 2); ?></p>
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
                                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($profit_stats['total_transactions'] ?? 0); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="bg-blue-100 text-blue-700 p-3 rounded-lg">
                                <i class="fas fa-calendar-check text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Bookings</h3>
                                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($profit_stats['total_bookings'] ?? 0); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="bg-purple-100 text-purple-700 p-3 rounded-lg">
                                <i class="fas fa-chart-line text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Avg. Transaction</h3>
                                <p class="text-2xl font-semibold text-gray-800">$<?php echo number_format($profit_stats['avg_transaction_value'] ?? 0, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="bg-amber-100 text-amber-700 p-3 rounded-lg">
                                <i class="fas fa-calculator text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Revenue/Booking</h3>
                                <p class="text-2xl font-semibold text-gray-800">$<?php echo number_format($profit_stats['revenue_per_booking'] ?? 0, 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Time-based Profit Chart -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Revenue Over Time (<?php echo ucfirst($group_by); ?>)</h2>
                    <div class="h-80">
                        <canvas id="timeBasedProfitChart"></canvas>
                    </div>
                </div>

                <!-- Profit by Category -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Profit by Spot Type -->
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
                                    <?php if ($spot_profit->num_rows > 0): ?>
                                        <?php while($row = $spot_profit->fetch_assoc()): ?>
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

                    <!-- Profit by Payment Method -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Revenue by Payment Method</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Revenue</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if ($payment_profit->num_rows > 0): ?>
                                        <?php while($row = $payment_profit->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo ucfirst($row['payment_method']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo number_format($row['transaction_count']); ?>
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

                <!-- Profit by Floor -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Revenue by Floor</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Floor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bookings</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Revenue</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue/Booking</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if ($floor_profit->num_rows > 0): ?>
                                    <?php while($row = $floor_profit->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            Floor <?php echo $row['floor_number']; ?>
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
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            $<?php echo number_format($row['revenue_per_booking'], 2); ?>
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
            </main>
        </div>
    </div>

    <script>
        // Time-based Profit Chart
        const timeBasedProfitCtx = document.getElementById('timeBasedProfitChart');
        if (timeBasedProfitCtx) {
            new Chart(timeBasedProfitCtx, {
                type: '<?php echo $group_by === 'hourly' ? 'bar' : 'line'; ?>',
                data: {
                    labels: <?php echo json_encode($time_labels); ?>,
                    datasets: [{
                        label: 'Revenue',
                        data: <?php echo json_encode($time_data); ?>,
                        backgroundColor: 'rgba(14, 165, 233, 0.7)',
                        borderColor: 'rgba(14, 165, 233, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: <?php echo $group_by === 'hourly' ? 'false' : 'true'; ?>
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
    </script>
</body>
</html>
