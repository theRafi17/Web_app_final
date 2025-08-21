<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Remember me functionality
    $remember = isset($_POST['remember']) ? true : false;

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_admin'] = $user['is_admin'];

            // Set cookie if remember me is checked (30 days)
            if ($remember) {
                $token = bin2hex(random_bytes(16));
                setcookie('remember_token', $token, time() + 30 * 24 * 60 * 60, '/');

                // Store token in database (in a real app)
                // This is simplified for demonstration
            }

            // Redirect based on user role
            if ($user['is_admin'] == 1) {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Parking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Additional styles specific to login page */
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

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
        }

        .remember-me input {
            margin-right: 0.5rem;
        }

        .forgot-password a {
            color: #00416A;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: #005688;
            text-decoration: underline;
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
                <h2>Smart Parking System</h2>
                <p>Manage your parking spots efficiently with our smart parking solution.</p>
            </div>
        </div>

        <!-- Right side form -->
        <div class="form-container">
            <div class="container fade-in">
                <div class="form-header">
                    <img src="https://img.icons8.com/color/96/000000/parking.png" alt="Smart Parking Logo">
                    <h2 class="form-title">Welcome Back</h2>
                    <p class="form-subtitle">Sign in to access your account</p>
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
                        <label for="email">Email</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="Enter your email" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Enter your password" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('password')"></i>
                        </div>
                    </div>

                    <div class="remember-forgot">
                        <div class="remember-me">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Remember me</label>
                        </div>
                        <div class="forgot-password">
                            <a href="forgot-password.php">Forgot Password?</a>
                        </div>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>

                <div class="switch-form">
                    Don't have an account? <a href="signup.php">Sign Up</a>
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
