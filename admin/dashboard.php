<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Update expired bookings
updateExpiredBookings();

// Get user information
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Get statistics for dashboard
// Total users
$total_users_sql = "SELECT COUNT(*) as total FROM users";
$total_users = $conn->query($total_users_sql)->fetch_assoc()['total'];

// Total parking spots
$total_spots_sql = "SELECT COUNT(*) as total FROM parking_spots";
$total_spots = $conn->query($total_spots_sql)->fetch_assoc()['total'];

// Total active bookings
$active_bookings_sql = "SELECT COUNT(*) as total FROM bookings WHERE status = 'active'";
$active_bookings = $conn->query($active_bookings_sql)->fetch_assoc()['total'];

// Total completed bookings
$completed_bookings_sql = "SELECT COUNT(*) as total FROM bookings WHERE status = 'completed'";
$completed_bookings = $conn->query($completed_bookings_sql)->fetch_assoc()['total'];

// Total revenue
$revenue_sql = "SELECT SUM(amount) as total FROM bookings";
$revenue = $conn->query($revenue_sql)->fetch_assoc()['total'] ?? 0;

// Recent bookings
$recent_bookings_sql = "SELECT b.*, u.name as user_name, p.spot_number
                        FROM bookings b
                        JOIN users u ON b.user_id = u.id
                        JOIN parking_spots p ON b.spot_id = p.id
                        ORDER BY b.id DESC LIMIT 5";
$recent_bookings = $conn->query($recent_bookings_sql);

// Get any messages from the session
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : '';
unset($_SESSION['message'], $_SESSION['message_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smart Parking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f4ff',
                            100: '#e0e9ff',
                            200: '#c7d7fe',
                            300: '#a4bcfc',
                            400: '#8098f9',
                            500: '#6371f1',
                            600: '#4a4ce4',
                            700: '#3a3cc8',
                            800: '#3235a2',
                            900: '#2d317f',
                            950: '#1a1b4b',
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
                    <h1 class="text-2xl font-semibold text-gray-800">Admin Dashboard</h1>
                    <div class="flex items-center gap-3">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random"
                             alt="User Avatar"
                             class="w-10 h-10 rounded-full">
                        <span class="text-gray-600"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-6">
                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'; ?>">
                    <div class="flex items-center">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-3"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Dashboard Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                        <div class="bg-primary-100 text-primary-700 p-3 rounded-lg">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Total Users</h3>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo $total_users; ?></p>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                        <div class="bg-blue-100 text-blue-700 p-3 rounded-lg">
                            <i class="fas fa-parking text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Parking Spots</h3>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo $total_spots; ?></p>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                        <div class="bg-green-100 text-green-700 p-3 rounded-lg">
                            <i class="fas fa-calendar-check text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Active Bookings</h3>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo $active_bookings; ?></p>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                        <div class="bg-purple-100 text-purple-700 p-3 rounded-lg">
                            <i class="fas fa-check-circle text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Completed</h3>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo $completed_bookings; ?></p>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                        <div class="bg-amber-100 text-amber-700 p-3 rounded-lg">
                            <i class="fas fa-money-bill-wave text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
                            <p class="text-2xl font-semibold text-gray-800">$<?php echo number_format($revenue, 2); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="flex justify-between items-center px-6 py-4 border-b">
                        <h2 class="text-lg font-semibold text-gray-800">Recent Bookings</h2>
                        <a href="bookings.php" class="text-primary-600 hover:text-primary-800 font-medium text-sm">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spot</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if ($recent_bookings->num_rows > 0): ?>
                                    <?php while($booking = $recent_bookings->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $booking['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($booking['spot_number']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($booking['vehicle_number']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('M d, g:i A', strtotime($booking['start_time'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('M d, g:i A', strtotime($booking['end_time'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $statusClasses = [
                                                'active' => 'bg-green-100 text-green-800',
                                                'completed' => 'bg-blue-100 text-blue-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                                'pending' => 'bg-yellow-100 text-yellow-800'
                                            ];
                                            $status = strtolower($booking['status']);
                                            $statusClass = isset($statusClasses[$status]) ? $statusClasses[$status] : 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($booking['amount'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500">No bookings found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Any dashboard-specific JavaScript can go here
    });
    </script>
</body>
</html>
