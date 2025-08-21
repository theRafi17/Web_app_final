<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Check if payment ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['message'] = "No payment ID provided.";
    $_SESSION['message_type'] = "error";
    header("Location: payments.php");
    exit();
}

$payment_id = $_GET['id'];

// Get payment details with related information
$payment_sql = "SELECT p.*, b.vehicle_number, b.start_time, b.end_time, b.status as booking_status, 
                u.id as user_id, u.name as user_name, u.email as user_email, 
                ps.id as spot_id, ps.spot_number, ps.floor_number, ps.type, ps.hourly_rate
                FROM payments p
                JOIN bookings b ON p.booking_id = b.id
                JOIN users u ON b.user_id = u.id
                JOIN parking_spots ps ON b.spot_id = ps.id
                WHERE p.id = ?";

$payment_stmt = $conn->prepare($payment_sql);
$payment_stmt->bind_param("i", $payment_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();

if ($payment_result->num_rows === 0) {
    $_SESSION['message'] = "Payment not found.";
    $_SESSION['message_type'] = "error";
    header("Location: payments.php");
    exit();
}

$payment = $payment_result->fetch_assoc();

// Calculate parking duration
$start = new DateTime($payment['start_time']);
$end = new DateTime($payment['end_time']);
$interval = $start->diff($end);
$hours = $interval->h + ($interval->days * 24);
$minutes = $interval->i;

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
    <title>Payment Details - Smart Parking System</title>
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
                    <h1 class="text-2xl font-semibold text-gray-800">Payment Details</h1>
                    <div class="flex space-x-2">
                        <a href="payments.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all flex items-center gap-2">
                            <i class="fas fa-arrow-left"></i> Back to Payments
                        </a>
                        <a href="../view-receipt.php?booking_id=<?php echo $payment['booking_id']; ?>" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all flex items-center gap-2">
                            <i class="fas fa-receipt"></i> View Receipt
                        </a>
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

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Payment Information -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-800">Payment Information</h2>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Payment ID</h3>
                                        <p class="text-base text-gray-900"><?php echo $payment['id']; ?></p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Transaction ID</h3>
                                        <p class="text-base text-gray-900"><?php echo $payment['transaction_id']; ?></p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Amount</h3>
                                        <p class="text-base text-gray-900 font-semibold">$<?php echo number_format($payment['amount'], 2); ?></p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Payment Method</h3>
                                        <p class="text-base text-gray-900">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
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
                                        </p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Payment Date</h3>
                                        <p class="text-base text-gray-900"><?php echo date('F d, Y h:i A', strtotime($payment['payment_date'])); ?></p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Payment Status</h3>
                                        <p class="text-base text-gray-900">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo $payment['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                <?php echo ucfirst($payment['payment_status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Created At</h3>
                                        <p class="text-base text-gray-900"><?php echo date('F d, Y h:i A', strtotime($payment['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Booking Information -->
                        <div class="bg-white rounded-xl shadow-sm overflow-hidden mt-6">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-800">Booking Information</h2>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Booking ID</h3>
                                        <p class="text-base text-gray-900"><?php echo $payment['booking_id']; ?></p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Vehicle Number</h3>
                                        <p class="text-base text-gray-900"><?php echo htmlspecialchars($payment['vehicle_number']); ?></p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Parking Spot</h3>
                                        <p class="text-base text-gray-900">Spot <?php echo htmlspecialchars($payment['spot_number']); ?> (Floor <?php echo $payment['floor_number']; ?>)</p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Spot Type</h3>
                                        <p class="text-base text-gray-900"><?php echo ucfirst($payment['type']); ?></p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Start Time</h3>
                                        <p class="text-base text-gray-900"><?php echo date('F d, Y h:i A', strtotime($payment['start_time'])); ?></p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">End Time</h3>
                                        <p class="text-base text-gray-900"><?php echo date('F d, Y h:i A', strtotime($payment['end_time'])); ?></p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Duration</h3>
                                        <p class="text-base text-gray-900"><?php echo $hours; ?> hours <?php echo $minutes > 0 ? $minutes . ' minutes' : ''; ?></p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Hourly Rate</h3>
                                        <p class="text-base text-gray-900">$<?php echo number_format($payment['hourly_rate'], 2); ?></p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-1">Booking Status</h3>
                                        <p class="text-base text-gray-900">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php 
                                                    switch($payment['booking_status']) {
                                                        case 'active':
                                                            echo 'bg-blue-100 text-blue-800';
                                                            break;
                                                        case 'completed':
                                                            echo 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'cancelled':
                                                            echo 'bg-red-100 text-red-800';
                                                            break;
                                                        default:
                                                            echo 'bg-gray-100 text-gray-800';
                                                    }
                                                ?>">
                                                <?php echo ucfirst($payment['booking_status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Information -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-800">User Information</h2>
                            </div>
                            <div class="p-6">
                                <div class="flex flex-col items-center mb-6">
                                    <div class="w-20 h-20 rounded-full bg-primary-100 flex items-center justify-center mb-3">
                                        <i class="fas fa-user text-primary-600 text-3xl"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($payment['user_name']); ?></h3>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($payment['user_email']); ?></p>
                                </div>
                                
                                <div class="mt-6">
                                    <a href="users.php?search=<?php echo urlencode($payment['user_email']); ?>" class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all flex items-center justify-center gap-2">
                                        <i class="fas fa-user"></i> View User Profile
                                    </a>
                                    <a href="bookings.php?search=<?php echo urlencode($payment['user_email']); ?>" class="w-full mt-2 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all flex items-center justify-center gap-2">
                                        <i class="fas fa-calendar-check"></i> View User Bookings
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
