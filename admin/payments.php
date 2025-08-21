<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Handle search and filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : 'all';
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the WHERE clause
$where_sql = "WHERE 1=1"; // Always true condition to start with
$params = [];
$types = "";

if (!empty($search)) {
    $where_sql .= " AND (p.transaction_id LIKE ? OR b.vehicle_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if ($payment_method !== 'all') {
    $where_sql .= " AND p.payment_method = ?";
    $params[] = $payment_method;
    $types .= "s";
}

if ($payment_status !== 'all') {
    $where_sql .= " AND p.payment_status = ?";
    $params[] = $payment_status;
    $types .= "s";
}

if (!empty($date_from)) {
    $where_sql .= " AND DATE(p.payment_date) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $where_sql .= " AND DATE(p.payment_date) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get total number of payments with filters
$total_sql = "SELECT COUNT(*) as total FROM payments p
              JOIN bookings b ON p.booking_id = b.id
              JOIN users u ON b.user_id = u.id
              $where_sql";

if (!empty($params)) {
    $total_stmt = $conn->prepare($total_sql);
    $total_stmt->bind_param($types, ...$params);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
} else {
    $total_result = $conn->query($total_sql);
}

$total_payments = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_payments / $records_per_page);

// Get payments with pagination and filters
$payments_sql = "SELECT p.*, b.vehicle_number, b.start_time, b.end_time, b.status as booking_status,
                u.name as user_name, u.email as user_email, ps.spot_number, ps.floor_number, ps.type
                FROM payments p
                JOIN bookings b ON p.booking_id = b.id
                JOIN users u ON b.user_id = u.id
                JOIN parking_spots ps ON b.spot_id = ps.id
                $where_sql
                ORDER BY p.id DESC
                LIMIT ?, ?";

$params[] = $offset;
$params[] = $records_per_page;
$types .= "ii";

$payments_stmt = $conn->prepare($payments_sql);
$payments_stmt->bind_param($types, ...$params);
$payments_stmt->execute();
$payments = $payments_stmt->get_result();

// Get payment statistics
$stats_sql = "SELECT
                COUNT(*) as total_count,
                SUM(amount) as total_amount,
                COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_count,
                COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN payment_method = 'cash' THEN 1 END) as cash_count,
                COUNT(CASE WHEN payment_method = 'card' THEN 1 END) as card_count,
                COUNT(CASE WHEN payment_method = 'paypal' THEN 1 END) as paypal_count,
                COUNT(CASE WHEN payment_method = 'bank_transfer' THEN 1 END) as bank_transfer_count
              FROM payments";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

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
    <title>Payments Management - Smart Parking System</title>
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
                    <h1 class="text-2xl font-semibold text-gray-800">Payments Management</h1>
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

                <!-- Payment Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                        <div class="bg-primary-100 text-primary-700 p-3 rounded-lg">
                            <i class="fas fa-money-bill-wave text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Total Payments</h3>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo $stats['total_count']; ?></p>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                        <div class="bg-green-100 text-green-700 p-3 rounded-lg">
                            <i class="fas fa-dollar-sign text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
                            <p class="text-2xl font-semibold text-gray-800">$<?php echo number_format($stats['total_amount'], 2); ?></p>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                        <div class="bg-blue-100 text-blue-700 p-3 rounded-lg">
                            <i class="fas fa-check-circle text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Paid Payments</h3>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo $stats['paid_count']; ?></p>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                        <div class="bg-yellow-100 text-yellow-700 p-3 rounded-lg">
                            <i class="fas fa-clock text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Pending Payments</h3>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo $stats['pending_count']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Search & Filter</h2>
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text" id="search" name="search" placeholder="Transaction ID, Vehicle, User..." value="<?php echo htmlspecialchars($search); ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                            <select id="payment_method" name="payment_method"
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="all" <?php echo $payment_method === 'all' ? 'selected' : ''; ?>>All Methods</option>
                                <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="card" <?php echo $payment_method === 'card' ? 'selected' : ''; ?>>Card</option>
                                <option value="paypal" <?php echo $payment_method === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                <option value="bank_transfer" <?php echo $payment_method === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            </select>
                        </div>
                        <div>
                            <label for="payment_status" class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                            <select id="payment_status" name="payment_status"
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="all" <?php echo $payment_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="paid" <?php echo $payment_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="pending" <?php echo $payment_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all">
                                <i class="fas fa-search mr-2"></i> Search
                            </button>
                            <a href="payments.php" class="ml-2 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all">
                                <i class="fas fa-redo mr-2"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Payments Table -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if ($payments->num_rows > 0): ?>
                                    <?php while($payment = $payments->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $payment['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $payment['transaction_id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $payment['booking_id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="ml-2">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($payment['user_name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($payment['user_email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($payment['vehicle_number']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                <?php
                                                    switch($payment['payment_method']) {
                                                        case 'cash':
                                                            echo 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'card':
                                                            echo 'bg-blue-100 text-blue-800';
                                                            break;
                                                        case 'paypal':
                                                            echo 'bg-indigo-100 text-indigo-800';
                                                            break;
                                                        case 'bank_transfer':
                                                            echo 'bg-purple-100 text-purple-800';
                                                            break;
                                                        default:
                                                            echo 'bg-gray-100 text-gray-800';
                                                    }
                                                ?>">
                                                <?php echo ucfirst($payment['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y h:i A', strtotime($payment['payment_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                <?php echo $payment['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                <?php echo ucfirst($payment['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <a href="view-payment.php?id=<?php echo $payment['id']; ?>" class="text-primary-600 hover:text-primary-900">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="../view-receipt.php?booking_id=<?php echo $payment['booking_id']; ?>" target="_blank" class="text-green-600 hover:text-green-900">
                                                    <i class="fas fa-receipt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="px-6 py-10 text-center text-sm text-gray-500">No payments found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 bg-white border-t border-gray-200">
                        <div class="flex justify-center">
                            <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $payment_method !== 'all' ? '&payment_method=' . $payment_method : ''; ?><?php echo $payment_status !== 'all' ? '&payment_status=' . $payment_status : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>"
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                // Show limited page numbers with ellipsis
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                if ($start_page > 1) {
                                    echo '<a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . ($payment_method !== 'all' ? '&payment_method=' . $payment_method : '') . ($payment_status !== 'all' ? '&payment_status=' . $payment_status : '') . (!empty($date_from) ? '&date_from=' . $date_from : '') . (!empty($date_to) ? '&date_to=' . $date_to : '') . '"
                                             class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-primary-500 bg-primary-50 text-sm font-medium text-primary-600">
                                            <?php echo $i; ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $payment_method !== 'all' ? '&payment_method=' . $payment_method : ''; ?><?php echo $payment_status !== 'all' ? '&payment_status=' . $payment_status : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>"
                                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor;

                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                                    }
                                    echo '<a href="?page=' . $total_pages . (!empty($search) ? '&search=' . urlencode($search) : '') . ($payment_method !== 'all' ? '&payment_method=' . $payment_method : '') . ($payment_status !== 'all' ? '&payment_status=' . $payment_status : '') . (!empty($date_from) ? '&date_from=' . $date_from : '') . (!empty($date_to) ? '&date_to=' . $date_to : '') . '"
                                             class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $total_pages . '</a>';
                                }
                                ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $payment_method !== 'all' ? '&payment_method=' . $payment_method : ''; ?><?php echo $payment_status !== 'all' ? '&payment_status=' . $payment_status : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>"
                                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Auto-apply filters when inputs change
    document.addEventListener('DOMContentLoaded', function() {
        const filterForm = document.querySelector('form');
        const filterInputs = filterForm.querySelectorAll('select, input[type="date"]');

        filterInputs.forEach(input => {
            input.addEventListener('change', function() {
                filterForm.submit();
            });
        });
    });
    </script>
</body>
</html>
