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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    
    if (password_verify($current_password, $user['password'])) {
        $update_sql = "UPDATE users SET name = ?, email = ?";
        $params = array($name, $email);
        $types = "ss";
        
        if (!empty($new_password)) {
            $update_sql .= ", password = ?";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            $types .= "s";
        }
        
        $update_sql .= " WHERE id = ?";
        $params[] = $user_id;
        $types .= "i";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param($types, ...$params);
        
        if ($update_stmt->execute()) {
            $_SESSION['message'] = "Profile updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating profile.";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Current password is incorrect.";
        $_SESSION['message_type'] = "error";
    }
    
    header("Location: settings.php");
    exit();
}

// Get notification preferences (you can expand this)
$notifications = [
    'booking_confirmation' => true,
    'booking_reminder' => true,
    'parking_expiry' => true
];

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
    <title>Settings - Smart Parking System</title>
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
                    <h1>Settings</h1>
                    <div class="user-info">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random" alt="User Avatar">
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                </div>
            </header>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div class="settings-container">
                <!-- Profile Settings -->
                <div class="settings-section">
                    <h2><i class="fas fa-user"></i> Profile Settings</h2>
                    <form method="POST" action="" class="settings-form">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password (leave blank to keep current)</label>
                            <input type="password" id="new_password" name="new_password">
                        </div>
                        <button type="submit" name="update_profile" class="btn-save">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>

                <!-- Notification Settings -->
                <div class="settings-section">
                    <h2><i class="fas fa-bell"></i> Notification Settings</h2>
                    <div class="notification-settings">
                        <div class="notification-option">
                            <label class="toggle">
                                <input type="checkbox" checked="<?php echo $notifications['booking_confirmation']; ?>">
                                <span class="toggle-slider"></span>
                            </label>
                            <div class="notification-info">
                                <h3>Booking Confirmations</h3>
                                <p>Receive notifications when your booking is confirmed</p>
                            </div>
                        </div>
                        <div class="notification-option">
                            <label class="toggle">
                                <input type="checkbox" checked="<?php echo $notifications['booking_reminder']; ?>">
                                <span class="toggle-slider"></span>
                            </label>
                            <div class="notification-info">
                                <h3>Booking Reminders</h3>
                                <p>Get reminded 1 hour before your parking time starts</p>
                            </div>
                        </div>
                        <div class="notification-option">
                            <label class="toggle">
                                <input type="checkbox" checked="<?php echo $notifications['parking_expiry']; ?>">
                                <span class="toggle-slider"></span>
                            </label>
                            <div class="notification-info">
                                <h3>Parking Expiry</h3>
                                <p>Get notified 15 minutes before your parking expires</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    </script>
</body>
</html>
