<?php
// Get current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Check if user is admin
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">
        <img src="https://img.icons8.com/color/96/000000/parking.png" alt="Smart Parking Logo">
        <h2>Smart Parking</h2>
    </div>
    <nav>
        <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="my-bookings.php" class="<?php echo $current_page == 'my-bookings.php' ? 'active' : ''; ?>">
            <i class="fas fa-ticket-alt"></i> My Bookings
        </a>
        <a href="history.php" class="<?php echo $current_page == 'history.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> History
        </a>
        <a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
        <?php if ($is_admin): ?>
        <a href="admin/dashboard.php" class="<?php echo strpos($current_page, 'admin') !== false ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i> Admin Panel
        </a>
        <?php endif; ?>
        <a href="logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</div>
