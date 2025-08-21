<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;

    // Validate inputs
    $errors = [];

    // Validate name
    if (empty($name)) {
        $errors[] = "Name is required.";
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $errors[] = "Name must be between 2 and 100 characters.";
    }

    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    // Validate password
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if email already exists
    $check_sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();

    if ($result['count'] > 0) {
        $errors[] = "Email already exists.";
    }

    // If there are no errors, insert the new user
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user
        $sql = "INSERT INTO users (name, email, password, is_admin) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $name, $email, $hashed_password, $is_admin);

        if ($stmt->execute()) {
            $_SESSION['message'] = "User <strong>" . htmlspecialchars($name) . "</strong> added successfully.";
            $_SESSION['message_type'] = "success";
            header("Location: users.php");
            exit();
        } else {
            $errors[] = "Error adding user: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User - Smart Parking System</title>
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
                    <h1 class="text-2xl font-semibold text-gray-800">Add New User</h1>
                    <button class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all"
                            onclick="window.location.href='users.php'">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </button>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-6">
                <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-50 text-red-700">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-circle mt-0.5 mr-3"></i>
                        <div><?php echo implode("<br>", $errors); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Add User Form -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-user text-gray-400 mr-1"></i> Full Name
                                </label>
                                <input type="text" id="name" name="name" required
                                       value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>"
                                       placeholder="Enter full name"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <p class="mt-1 text-sm text-gray-500">Enter the user's full name</p>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-envelope text-gray-400 mr-1"></i> Email Address
                                </label>
                                <input type="email" id="email" name="email" required
                                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                                       placeholder="Enter email address"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <p class="mt-1 text-sm text-gray-500">This will be used for login and notifications</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-lock text-gray-400 mr-1"></i> Password
                                </label>
                                <div class="relative">
                                    <input type="password" id="password" name="password" required
                                           placeholder="Enter password" minlength="6"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    <button type="button"
                                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                            onclick="togglePasswordVisibility('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Minimum 6 characters</p>
                                <div id="password-strength" class="mt-2"></div>
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-lock text-gray-400 mr-1"></i> Confirm Password
                                </label>
                                <div class="relative">
                                    <input type="password" id="confirm_password" name="confirm_password" required
                                           placeholder="Confirm password" minlength="6"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    <button type="button"
                                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                            onclick="togglePasswordVisibility('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <p id="password-match-message" class="mt-1 text-sm"></p>
                            </div>
                        </div>
                        <div class="mb-6">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_admin"
                                       class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                                       <?php echo isset($is_admin) && $is_admin ? 'checked' : ''; ?>>
                                <span class="ml-2 text-sm font-medium text-gray-700">
                                    <i class="fas fa-user-shield text-gray-400 mr-1"></i> Administrator Access
                                </span>
                            </label>
                            <p class="mt-1 text-sm text-gray-500 ml-6">Grant full access to manage users, spots, and bookings</p>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all"
                                    onclick="window.location.href='users.php'">
                                <i class="fas fa-times mr-1"></i> Cancel
                            </button>
                            <button type="submit" name="add_user"
                                    class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all">
                                <i class="fas fa-user-plus mr-1"></i> Add User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>



    <script>
    // Password Visibility Toggle
    function togglePasswordVisibility(inputId) {
        const passwordInput = document.getElementById(inputId);
        const toggleButton = passwordInput.nextElementSibling.querySelector('i');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleButton.classList.remove('fa-eye');
            toggleButton.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleButton.classList.remove('fa-eye-slash');
            toggleButton.classList.add('fa-eye');
        }
    }

    // Password Strength Checker
    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        const strengthDiv = document.getElementById('password-strength');

        if (password.length === 0) {
            strengthDiv.innerHTML = '';
            return;
        }

        // Check password strength
        let strength = 0;

        // Length check
        if (password.length >= 8) {
            strength += 1;
        }

        // Uppercase check
        if (/[A-Z]/.test(password)) {
            strength += 1;
        }

        // Lowercase check
        if (/[a-z]/.test(password)) {
            strength += 1;
        }

        // Number check
        if (/[0-9]/.test(password)) {
            strength += 1;
        }

        // Special character check
        if (/[^A-Za-z0-9]/.test(password)) {
            strength += 1;
        }

        // Display strength with Tailwind classes
        if (strength < 2) {
            strengthDiv.innerHTML = `
                <div class="flex items-center gap-2">
                    <div class="flex gap-1">
                        <div class="h-1.5 w-6 bg-red-500 rounded"></div>
                        <div class="h-1.5 w-6 bg-gray-200 rounded"></div>
                        <div class="h-1.5 w-6 bg-gray-200 rounded"></div>
                    </div>
                    <span class="text-sm text-red-600">Weak</span>
                </div>
            `;
        } else if (strength < 4) {
            strengthDiv.innerHTML = `
                <div class="flex items-center gap-2">
                    <div class="flex gap-1">
                        <div class="h-1.5 w-6 bg-yellow-500 rounded"></div>
                        <div class="h-1.5 w-6 bg-yellow-500 rounded"></div>
                        <div class="h-1.5 w-6 bg-gray-200 rounded"></div>
                    </div>
                    <span class="text-sm text-yellow-600">Medium</span>
                </div>
            `;
        } else {
            strengthDiv.innerHTML = `
                <div class="flex items-center gap-2">
                    <div class="flex gap-1">
                        <div class="h-1.5 w-6 bg-green-500 rounded"></div>
                        <div class="h-1.5 w-6 bg-green-500 rounded"></div>
                        <div class="h-1.5 w-6 bg-green-500 rounded"></div>
                    </div>
                    <span class="text-sm text-green-600">Strong</span>
                </div>
            `;
        }
    });

    // Password Match Checker
    document.getElementById('confirm_password').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirmPassword = this.value;
        const messageElement = document.getElementById('password-match-message');

        if (confirmPassword.length === 0) {
            messageElement.innerHTML = '';
            return;
        }

        if (password === confirmPassword) {
            messageElement.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Passwords match</span>';
        } else {
            messageElement.innerHTML = '<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i>Passwords do not match</span>';
        }
    });
    </script>
</body>
</html>
