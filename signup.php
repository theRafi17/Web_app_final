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
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        $check_sql = "SELECT * FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = "Email already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $name, $email, $hashed_password);

            if ($stmt->execute()) {
                $success = "Registration successful! Please login.";
                header("refresh:2;url=login.php");
            } else {
                $error = "Error during registration. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Smart Parking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Additional styles specific to signup page */
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
                background: url('https://images.unsplash.com/photo-1506521781263-d8422e82f27a?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80') center/cover no-repeat;
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
                <h2>Join Smart Parking</h2>
                <p>Create an account to start managing your parking experience efficiently.</p>
            </div>
        </div>

        <!-- Right side form -->
        <div class="form-container">
            <div class="container fade-in">
                <div class="form-header">
                    <img src="https://img.icons8.com/color/96/000000/parking.png" alt="Smart Parking Logo">
                    <h2 class="form-title">Create Account</h2>
                    <p class="form-subtitle">Sign up to get started with Smart Parking</p>
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
                        <label for="name">Full Name</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" id="name" name="name" placeholder="Enter your full name" required>
                        </div>
                    </div>

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
                            <input type="password" id="password" name="password" placeholder="Create a password" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('password')"></i>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-user-plus"></i> Sign Up
                    </button>
                </form>

                <div class="switch-form">
                    Already have an account? <a href="login.php">Login</a>
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
