<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Check if user ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['message'] = "No user specified for editing.";
    $_SESSION['message_type'] = "error";
    header("Location: users.php");
    exit();
}

$user_id = $_GET['id'];

// Get user data
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$result = $user_stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "User not found.";
    $_SESSION['message_type'] = "error";
    header("Location: users.php");
    exit();
}

$user = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $is_admin = isset($_POST['is_admin']) && $_POST['is_admin'] == 1 ? 1 : 0;
    $errors = [];

    // Validate inputs
    if (empty($name)) {
        $errors[] = "Name is required.";
    }

    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Check if email already exists for another user
    $check_sql = "SELECT COUNT(*) as count FROM users WHERE email = ? AND id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $email, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();

    if ($result['count'] > 0) {
        $errors[] = "Email already exists for another user.";
    }

    // If there are errors, set error message
    if (!empty($errors)) {
        $_SESSION['message'] = implode("<br>", $errors);
        $_SESSION['message_type'] = "error";
    } else {
        // If password is provided, update it too
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "UPDATE users SET name = ?, email = ?, password = ?, is_admin = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssii", $name, $email, $password, $is_admin, $user_id);
        } else {
            $sql = "UPDATE users SET name = ?, email = ?, is_admin = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $name, $email, $is_admin, $user_id);
        }

        if ($stmt->execute()) {
            $_SESSION['message'] = "User <strong>" . htmlspecialchars($name) . "</strong> updated successfully.";
            $_SESSION['message_type'] = "success";
            header("Location: users.php");
            exit();
        } else {
            $_SESSION['message'] = "Error updating user: " . $conn->error;
            $_SESSION['message_type'] = "error";
        }
    }
}

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
    <title>Edit User - Smart Parking System</title>
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
                    <h1 class="text-2xl font-semibold text-gray-800">Edit User</h1>
                    <button class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all"
                            onclick="window.location.href='users.php'">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </button>
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

                <!-- Edit User Form -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-user text-gray-400 mr-1"></i> Full Name
                                </label>
                                <input type="text" id="name" name="name" required
                                       value="<?php echo htmlspecialchars($user['name']); ?>"
                                       placeholder="Enter full name"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <p class="mt-1 text-sm text-gray-500">Enter the user's full name</p>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-envelope text-gray-400 mr-1"></i> Email Address
                                </label>
                                <input type="email" id="email" name="email" required
                                       value="<?php echo htmlspecialchars($user['email']); ?>"
                                       placeholder="Enter email address"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <p class="mt-1 text-sm text-gray-500">Enter a valid email address</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-lock text-gray-400 mr-1"></i> Password
                                </label>
                                <input type="password" id="password" name="password"
                                       placeholder="Leave blank to keep current password"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <p class="mt-1 text-sm text-gray-500">Leave blank to keep the current password</p>
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-lock text-gray-400 mr-1"></i> Confirm Password
                                </label>
                                <input type="password" id="confirm_password" name="confirm_password"
                                       placeholder="Leave blank to keep current password"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <p class="mt-1 text-sm text-gray-500">Re-enter the password to confirm</p>
                            </div>
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user-shield text-gray-400 mr-1"></i> User Role
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="relative flex flex-col border-2 rounded-xl p-4 cursor-pointer transition-all
                                    <?php echo $user['is_admin'] ? 'border-gray-200 hover:border-primary-300 hover:bg-gray-50' : 'border-primary-500 bg-primary-50'; ?>">
                                    <input type="radio" name="user_role" value="user" class="absolute opacity-0"
                                           <?php echo $user['is_admin'] ? '' : 'checked'; ?>>
                                    <div class="flex items-center mb-2">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 mr-3">
                                            <i class="fas fa-user text-xl"></i>
                                        </div>
                                        <span class="text-lg font-medium">Regular User</span>
                                    </div>
                                    <p class="text-sm text-gray-600">Can book parking spots and manage own bookings</p>
                                </label>
                                <label class="relative flex flex-col border-2 rounded-xl p-4 cursor-pointer transition-all
                                    <?php echo $user['is_admin'] ? 'border-primary-500 bg-primary-50' : 'border-gray-200 hover:border-primary-300 hover:bg-gray-50'; ?>">
                                    <input type="radio" name="user_role" value="admin" class="absolute opacity-0"
                                           <?php echo $user['is_admin'] ? 'checked' : ''; ?>>
                                    <div class="flex items-center mb-2">
                                        <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 mr-3">
                                            <i class="fas fa-user-shield text-xl"></i>
                                        </div>
                                        <span class="text-lg font-medium">Administrator</span>
                                    </div>
                                    <p class="text-sm text-gray-600">Full access to manage users, spots, and bookings</p>
                                </label>
                            </div>
                            <input type="hidden" name="is_admin" id="is_admin_value" value="<?php echo $user['is_admin']; ?>">
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all"
                                    onclick="window.location.href='users.php'">
                                <i class="fas fa-times mr-1"></i> Cancel
                            </button>
                            <button type="submit" name="edit_user"
                                    class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all">
                                <i class="fas fa-save mr-1"></i> Update User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Update is_admin value based on role selection
    document.querySelectorAll('input[name="user_role"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('is_admin_value').value = this.value === 'admin' ? 1 : 0;
            updateRoleSelection();
        });
    });

    // Function to update visual selection of roles
    function updateRoleSelection() {
        const selectedRole = document.querySelector('input[name="user_role"]:checked').value;
        document.getElementById('is_admin_value').value = selectedRole === 'admin' ? 1 : 0;

        // Update visual selection
        const roleLabels = document.querySelectorAll('input[name="user_role"]').forEach(radio => {
            const label = radio.closest('label');
            if (radio.checked) {
                label.classList.remove('border-gray-200', 'hover:border-primary-300', 'hover:bg-gray-50');
                label.classList.add('border-primary-500', 'bg-primary-50');
            } else {
                label.classList.remove('border-primary-500', 'bg-primary-50');
                label.classList.add('border-gray-200', 'hover:border-primary-300', 'hover:bg-gray-50');
            }
        });
    }

    // Password validation
    document.getElementById('password').addEventListener('input', validatePassword);
    document.getElementById('confirm_password').addEventListener('input', validatePassword);

    function validatePassword() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (password && confirmPassword && password !== confirmPassword) {
            document.getElementById('confirm_password').setCustomValidity("Passwords don't match");
        } else {
            document.getElementById('confirm_password').setCustomValidity('');
        }
    }

    // Initialize role selection on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateRoleSelection();
    });
    </script>
</body>
</html>
