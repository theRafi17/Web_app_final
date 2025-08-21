<?php
// Check if user is logged in and is an admin
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Get current page for highlighting active menu item
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="w-64 h-full bg-gradient-to-b from-primary-900 to-primary-800 text-white p-6 flex flex-col">
    <div class="flex items-center gap-3 mb-8">
        <img src="https://img.icons8.com/color/96/000000/parking.png" alt="Smart Parking Logo" class="w-10 h-10">
        <h2 class="text-xl font-semibold">Admin Panel</h2>
    </div>

    <nav class="flex flex-col gap-1">
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200 <?php echo $current_page == 'dashboard.php' ? 'bg-white/10 text-white' : 'text-gray-200 hover:bg-white/5'; ?>">
            <i class="fas fa-tachometer-alt w-5 text-center"></i> Dashboard
        </a>
        <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200 <?php echo $current_page == 'users.php' ? 'bg-white/10 text-white' : 'text-gray-200 hover:bg-white/5'; ?>">
            <i class="fas fa-users w-5 text-center"></i> User Management
        </a>
        <a href="parking-spots.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200 <?php echo $current_page == 'parking-spots.php' ? 'bg-white/10 text-white' : 'text-gray-200 hover:bg-white/5'; ?>">
            <i class="fas fa-parking w-5 text-center"></i> Parking Spots
        </a>
        <a href="bookings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200 <?php echo $current_page == 'bookings.php' ? 'bg-white/10 text-white' : 'text-gray-200 hover:bg-white/5'; ?>">
            <i class="fas fa-calendar-check w-5 text-center"></i> Bookings
        </a>
        <a href="payments.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200 <?php echo $current_page == 'payments.php' ? 'bg-white/10 text-white' : 'text-gray-200 hover:bg-white/5'; ?>">
            <i class="fas fa-money-bill-wave w-5 text-center"></i> Payments
        </a>
        <a href="reports.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200 <?php echo $current_page == 'reports.php' ? 'bg-white/10 text-white' : 'text-gray-200 hover:bg-white/5'; ?>">
            <i class="fas fa-chart-bar w-5 text-center"></i> Reports
        </a>
        <a href="../dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200 text-gray-200 hover:bg-white/5">
            <i class="fas fa-user w-5 text-center"></i> User Dashboard
        </a>
        <div class="mt-auto"></div>
        <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200 text-red-300 hover:bg-red-500/20 mt-4">
            <i class="fas fa-sign-out-alt w-5 text-center"></i> Logout
        </a>
    </nav>
</div>
