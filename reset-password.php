<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Verify token and update password
        $sql = "SELECT * FROM users WHERE reset_token = ? AND reset_expiry > NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE reset_token = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ss", $hashed_password, $token);

            if ($update_stmt->execute()) {
                $success = "Password updated successfully! Redirecting to login...";
                header("refresh:2;url=login.php");
            } else {
                $error = "Error updating password. Please try again.";
            }
        } else {
            $error = "Invalid or expired reset link.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Smart Parking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Additional styles specific to reset password page */
        .login-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        .login-image {
            display: none;
        }

        .form-container {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.9rem;
        }

        /* Animation for form elements */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Media query for larger screens */
        @media (min-width: 992px) {
            .login-container {
                display: flex;
            }

            .login-image {
                display: block;
                flex: 1;
                background: url('https://images.unsplash.com/photo-1557761469-f29c6e201784?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80') center/cover no-repeat;
                position: relative;
            }

            .login-image::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 65, 106, 0.4);
            }

            .login-image-content {
                position: absolute;
                bottom: 2rem;
                left: 2rem;
                color: white;
                z-index: 1;
            }

            .login-image-content h2 {
                font-size: 2.5rem;
                margin-bottom: 1rem;
                text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
            }

            .login-image-content p {
                font-size: 1.1rem;
                max-width: 80%;
                text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            }

            .form-container {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left side image (visible on larger screens) -->
        <div class="login-image">
            <div class="login-image-content">
                <h2>Create New Password</h2>
                <p>Choose a strong password to secure your account.</p>
            </div>
        </div>

        <!-- Right side form -->
        <div class="form-container">
            <div class="container fade-in">
                <div class="form-header">
                    <img src="https://img.icons8.com/color/96/000000/parking.png" alt="Smart Parking Logo">
                    <h2 class="form-title">Reset Password</h2>
                    <p class="form-subtitle">Create a new password for your account</p>
                </div>

                <?php if($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="input-group">
                        <label for="password">New Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Enter new password" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('password')"></i>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </form>

                <div class="switch-form">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>

                <div class="login-footer">
                    &copy; <?php echo date('Y'); ?> Smart Parking System. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <script>
    function togglePassword(fieldId) {
        const passwordField = document.getElementById(fieldId);
        const toggleIcon = event.currentTarget;

        if (passwordField.type === "password") {
            passwordField.type = "text";
            toggleIcon.classList.remove("fa-eye");
            toggleIcon.classList.add("fa-eye-slash");
        } else {
            passwordField.type = "password";
            toggleIcon.classList.remove("fa-eye-slash");
            toggleIcon.classList.add("fa-eye");
        }
    }
    </script>
</body>
</html>
