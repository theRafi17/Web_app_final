<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if passwords match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Check if email exists
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Update password directly
            $update_sql = "UPDATE users SET password = ? WHERE email = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ss", $hashed_password, $email);

            if ($update_stmt->execute()) {
                $success = "Password updated successfully! Redirecting to login...";
                header("refresh:2;url=login.php");
            } else {
                $error = "Error updating password. Please try again.";
            }
        } else {
            $error = "No account found with this email address.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Smart Parking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Additional styles specific to forgot password page */
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

        /* Loading animation for form submission */
        .loading {
            display: none;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .loading-spinner {
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 3px solid rgba(0, 65, 106, 0.2);
            border-radius: 50%;
            border-top-color: #00416A;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Email icon animation */
        .input-wrapper:focus-within i.fa-envelope {
            animation: bounce 0.5s ease-in-out;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(-50%); }
            50% { transform: translateY(-65%); }
        }

        /* Form text helper */
        .form-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.5rem;
            display: block;
        }

        /* Input focus effect */
        .input-wrapper.focused {
            border-color: #00416A;
            box-shadow: 0 0 0 4px rgba(0, 65, 106, 0.1);
        }

        /* Reset link styling */
        .reset-link {
            display: inline-block;
            background-color: #00416A;
            color: white !important;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            margin: 10px 0;
            transition: all 0.3s ease;
        }

        .reset-link:hover {
            background-color: #005688;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 65, 106, 0.2);
            text-decoration: none !important;
        }

        /* Media query for larger screens */
        @media (min-width: 992px) {
            .login-container {
                display: flex;
            }

            .login-image {
                display: block;
                flex: 1;
                background: url('https://images.unsplash.com/photo-1573348722427-f1d6819fdf98?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80') center/cover no-repeat;
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
                <h2>Password Recovery</h2>
                <p>We'll help you get back into your account in no time.</p>
            </div>
        </div>

        <!-- Right side form -->
        <div class="form-container">
            <div class="container fade-in">
                <div class="form-header">
                    <img src="https://img.icons8.com/color/96/000000/parking.png" alt="Smart Parking Logo">
                    <h2 class="form-title">Reset Password</h2>
                    <p class="form-subtitle">Enter your email and new password</p>
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

                <!-- Loading indicator -->
                <div id="loading" class="loading">
                    <div class="loading-spinner"></div>
                    <p>Resetting password...</p>
                </div>

                <form method="POST" action="" id="resetForm">
                    <div class="input-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="Enter your registered email" required>
                        </div>
                        <small class="form-text">Enter the email associated with your account</small>
                    </div>

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

                    <button type="submit" class="btn" id="submitBtn">
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
        // Form submission with loading indicator
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            // Show loading indicator
            document.getElementById('loading').style.display = 'block';

            // Disable submit button to prevent multiple submissions
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';

            // Form will submit normally
            // The loading indicator will be hidden when the page reloads
        });

        // Add visual feedback when focusing on inputs
        document.getElementById('email').addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });

        document.getElementById('email').addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });

        document.getElementById('password').addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });

        document.getElementById('password').addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });

        document.getElementById('confirm_password').addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });

        document.getElementById('confirm_password').addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });

        // Toggle password visibility
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
