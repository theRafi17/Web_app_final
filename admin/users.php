<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle admin status
    if (isset($_POST['toggle_admin'])) {
        $user_id = $_POST['user_id'];
        $is_admin = $_POST['is_admin'] ? 0 : 1; // Toggle the value

        $sql = "UPDATE users SET is_admin = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $is_admin, $user_id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "User admin status updated successfully.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating user admin status.";
            $_SESSION['message_type'] = "error";
        }

        header("Location: users.php");
        exit();
    }

    // Delete user
    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];

        // Check if user has any bookings
        $check_sql = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();

        if ($result['count'] > 0) {
            $_SESSION['message'] = "Cannot delete user with existing bookings.";
            $_SESSION['message_type'] = "error";
        } else {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = "User deleted successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting user.";
                $_SESSION['message_type'] = "error";
            }
        }

        header("Location: users.php");
        exit();
    }

    // Add new user
    if (isset($_POST['add_user'])) {
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

        // If there are errors, set error message
        if (!empty($errors)) {
            $_SESSION['message'] = implode("<br>", $errors);
            $_SESSION['message_type'] = "error";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $sql = "INSERT INTO users (name, email, password, is_admin) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $name, $email, $hashed_password, $is_admin);

            if ($stmt->execute()) {
                $_SESSION['message'] = "User <strong>" . htmlspecialchars($name) . "</strong> added successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error adding user: " . $conn->error;
                $_SESSION['message_type'] = "error";
            }
        }

        header("Location: users.php");
        exit();
    }

    // Edit user
    if (isset($_POST['edit_user'])) {
        $user_id = $_POST['user_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'] ?? '';
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

        // Validate password if provided
        if (!empty($password)) {
            if (strlen($password) < 6) {
                $errors[] = "Password must be at least 6 characters long.";
            } elseif ($password !== $confirm_password) {
                $errors[] = "Passwords do not match.";
            }
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
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET name = ?, email = ?, password = ?, is_admin = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssii", $name, $email, $hashed_password, $is_admin, $user_id);
            } else {
                $sql = "UPDATE users SET name = ?, email = ?, is_admin = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssii", $name, $email, $is_admin, $user_id);
            }

            if ($stmt->execute()) {
                $_SESSION['message'] = "User <strong>" . htmlspecialchars($name) . "</strong> updated successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating user: " . $conn->error;
                $_SESSION['message_type'] = "error";
            }
        }

        header("Location: users.php");
        exit();
    }
}

// Handle search and filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build the WHERE clause
$where_clause = "";
$params = [];
$types = "";

if (!empty($search)) {
    $where_clause = " WHERE (name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($filter !== 'all') {
    $is_admin = ($filter === 'admin') ? 1 : 0;
    if (empty($where_clause)) {
        $where_clause = " WHERE is_admin = ?";
    } else {
        $where_clause .= " AND is_admin = ?";
    }
    $params[] = $is_admin;
    $types .= "i";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get total number of users with filters
$total_sql = "SELECT COUNT(*) as total FROM users" . $where_clause;
if (!empty($params)) {
    $total_stmt = $conn->prepare($total_sql);
    $total_stmt->bind_param($types, ...$params);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
} else {
    $total_result = $conn->query($total_sql);
}
$total_users = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $records_per_page);

// Get users with pagination and filters
$users_sql = "SELECT * FROM users" . $where_clause . " ORDER BY id DESC LIMIT ?, ?";
$users_stmt = $conn->prepare($users_sql);

// Add pagination parameters
$params[] = $offset;
$params[] = $records_per_page;
$types .= "ii";

// Bind all parameters
if (!empty($params)) {
    $users_stmt->bind_param($types, ...$params);
}
$users_stmt->execute();
$users = $users_stmt->get_result();

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
    <title>User Management - Smart Parking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../dashboard.css">
    <link rel="stylesheet" href="admin.css">
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
                    <h1 class="text-2xl font-semibold text-gray-800">User Management</h1>
                    <button class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all flex items-center gap-2" onclick="window.location.href='add-user.php'">
                        <i class="fas fa-plus"></i> Add New User
                    </button>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-6">
                <!-- Search and Filter -->
                <div class="mb-6 flex flex-col md:flex-row justify-between gap-4">
                    <form method="GET" action="" class="w-full md:w-1/3">
                        <div class="relative">
                            <input type="text" name="search" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <button type="submit" class="absolute right-0 top-0 h-full px-3 text-gray-500 hover:text-primary-600">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    <div class="flex flex-wrap gap-2">
                        <a href="?filter=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-all <?php echo $filter === 'all' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
                            All Users
                        </a>
                        <a href="?filter=admin<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-all <?php echo $filter === 'admin' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
                            Admins
                        </a>
                        <a href="?filter=user<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-all <?php echo $filter === 'user' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
                            Regular Users
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'; ?>">
                    <div class="flex items-center">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-3"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Users Table -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if ($users->num_rows > 0): ?>
                                    <?php while($user = $users->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $user['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <button onclick="confirmRoleChangeWithAlert(<?php echo $user['id']; ?>, <?php echo $user['is_admin']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')"
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    <?php echo $user['is_admin'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                <?php echo $user['is_admin'] ? 'Admin' : 'User'; ?>
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button class="text-primary-600 hover:text-primary-900" onclick="viewUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="text-amber-600 hover:text-amber-900" onclick="window.location.href='edit-user.php?id=<?php echo $user['id']; ?>'">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" action="" class="inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="delete_user" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this user?');">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No users found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 bg-white border-t border-gray-200">
                        <div class="flex justify-center">
                            <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . $filter : ''; ?>"
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                // Show limited page numbers with ellipsis
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                if ($start_page > 1) {
                                    echo '<a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . ($filter !== 'all' ? '&filter=' . $filter : '') . '"
                                             class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-primary-500 bg-primary-50 text-sm font-medium text-primary-600">
                                            <?php echo $i; ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . $filter : ''; ?>"
                                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor;

                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                                    }
                                    echo '<a href="?page=' . $total_pages . (!empty($search) ? '&search=' . urlencode($search) : '') . ($filter !== 'all' ? '&filter=' . $filter : '') . '"
                                             class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $total_pages . '</a>';
                                }
                                ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter !== 'all' ? '&filter=' . $filter : ''; ?>"
                                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-user-plus mr-2"></i> Add New User</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="hideAddUserModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST" action="" id="addUserForm" onsubmit="return validateAddUserForm()">
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-user mr-1"></i> Full Name</label>
                            <input type="text" id="name" name="name" required placeholder="Enter full name"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <small class="text-xs text-gray-500">Enter the user's full name</small>
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-envelope mr-1"></i> Email Address</label>
                            <input type="email" id="email" name="email" required placeholder="Enter email address"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <small class="text-xs text-gray-500">This will be used for login and notifications</small>
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-lock mr-1"></i> Password</label>
                            <div class="relative">
                                <input type="password" id="password" name="password" required placeholder="Enter password" minlength="6"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <button type="button" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500" onclick="togglePasswordVisibility('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-xs text-gray-500">Minimum 6 characters</small>
                            <div id="password-strength" class="mt-1"></div>
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-lock mr-1"></i> Confirm Password</label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm password" minlength="6"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <button type="button" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500" onclick="togglePasswordVisibility('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small id="password-match-message" class="text-xs"></small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-user-shield mr-1"></i> User Role</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <label class="border rounded-lg p-3 flex flex-col cursor-pointer hover:bg-gray-50 transition-all">
                                    <div class="flex items-center mb-2">
                                        <input type="radio" name="user_role" value="user" checked class="mr-2">
                                        <span class="font-medium">Regular User</span>
                                    </div>
                                    <span class="text-xs text-gray-500">Can book parking spots and manage own bookings</span>
                                </label>
                                <label class="border rounded-lg p-3 flex flex-col cursor-pointer hover:bg-gray-50 transition-all">
                                    <div class="flex items-center mb-2">
                                        <input type="radio" name="user_role" value="admin" class="mr-2">
                                        <span class="font-medium">Administrator</span>
                                    </div>
                                    <span class="text-xs text-gray-500">Full access to manage users, spots, and bookings</span>
                                </label>
                            </div>
                            <input type="hidden" name="is_admin_value" id="is_admin_value" value="0">
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all"
                                onclick="hideAddUserModal()">
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

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-edit mr-2"></i> Edit User</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="hideEditUserModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST" action="" id="editUserForm">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <div class="space-y-4">
                        <div>
                            <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" id="edit_name" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" id="edit_email" name="email" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="edit_password" class="block text-sm font-medium text-gray-700 mb-1">Password (leave blank to keep current)</label>
                            <input type="password" id="edit_password" name="password"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="edit_is_admin" name="is_admin" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                            <label for="edit_is_admin" class="ml-2 block text-sm text-gray-700">Admin User</label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all"
                                onclick="hideEditUserModal()">
                            Cancel
                        </button>
                        <button type="submit" name="edit_user"
                                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all">
                            Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Role Change Confirmation Modal -->
    <div id="roleChangeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-user-shield mr-2"></i> Confirm Role Change</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="hideRoleChangeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="mb-4 flex items-center p-4 bg-amber-50 text-amber-700 rounded-lg">
                    <div class="mr-3 text-xl">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <p id="roleChangeMessage" class="text-sm"></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="border rounded-lg p-4 bg-gray-50" id="current-role-card">
                        <div class="text-center text-sm font-medium text-gray-500 mb-2">Current Role</div>
                        <div class="text-center text-2xl mb-2" id="current-role-icon"></div>
                        <div class="text-center font-medium mb-1" id="current-role-title"></div>
                        <div class="text-center text-xs text-gray-500" id="current-role-desc"></div>
                    </div>
                    <div class="flex items-center justify-center">
                        <i class="fas fa-arrow-right text-gray-400 text-xl"></i>
                    </div>
                    <div class="border rounded-lg p-4 bg-gray-50" id="new-role-card">
                        <div class="text-center text-sm font-medium text-gray-500 mb-2">New Role</div>
                        <div class="text-center text-2xl mb-2" id="new-role-icon"></div>
                        <div class="text-center font-medium mb-1" id="new-role-title"></div>
                        <div class="text-center text-xs text-gray-500" id="new-role-desc"></div>
                    </div>
                </div>

                <form method="POST" action="" id="roleChangeForm">
                    <input type="hidden" id="role_user_id" name="user_id">
                    <input type="hidden" id="role_is_admin" name="is_admin">
                    <div class="flex justify-end gap-3">
                        <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all"
                                onclick="hideRoleChangeModal()">
                            <i class="fas fa-times mr-1"></i> Cancel
                        </button>
                        <button type="submit" name="toggle_admin"
                                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all">
                            <i class="fas fa-check mr-1"></i> Confirm Change
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Add User Modal Functions
    function showAddUserModal() {
        try {
            // Reset form and validation states
            const form = document.getElementById('addUserForm');
            if (form) {
                form.reset();
            }

            // Clear password strength and match indicators
            document.getElementById('password-strength').innerHTML = '';
            document.getElementById('password-match-message').innerHTML = '';

            // Make sure the user role radio is checked by default
            const userRoleRadio = document.querySelector('input[name="user_role"][value="user"]');
            if (userRoleRadio) {
                userRoleRadio.checked = true;
            }

            // Set default role
            updateRoleSelection();

            // Show the modal
            document.getElementById('addUserModal').classList.remove('hidden');
            document.getElementById('addUserModal').classList.add('flex');
        } catch (error) {
            console.error("Error in showAddUserModal:", error);
            alert("There was an error showing the Add User form. Please try again.");
        }
    }

    function hideAddUserModal() {
        try {
            // Hide the modal
            document.getElementById('addUserModal').classList.remove('flex');
            document.getElementById('addUserModal').classList.add('hidden');

            // Reset the form
            document.getElementById('addUserForm').reset();
        } catch (error) {
            console.error("Error in hideAddUserModal:", error);
        }
    }

    // Edit User Modal Functions
    function editUser(id, name, email, isAdmin) {
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_is_admin').checked = isAdmin === 1;
        document.getElementById('editUserModal').classList.remove('hidden');
        document.getElementById('editUserModal').classList.add('flex');
    }

    function hideEditUserModal() {
        document.getElementById('editUserModal').classList.remove('flex');
        document.getElementById('editUserModal').classList.add('hidden');
    }

    // Role Change with Alert
    function confirmRoleChangeWithAlert(userId, isAdmin, userName) {
        const newRole = isAdmin ? 'User' : 'Admin';
        const currentRole = isAdmin ? 'Admin' : 'User';

        // Show confirmation dialog
        const confirmMessage = `Are you sure you want to change ${userName}'s role from ${currentRole} to ${newRole}?`;

        if (confirm(confirmMessage)) {
            // Create and submit a form to change the role
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            // Create user_id input
            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'user_id';
            userIdInput.value = userId;
            form.appendChild(userIdInput);

            // Create is_admin input
            const isAdminInput = document.createElement('input');
            isAdminInput.type = 'hidden';
            isAdminInput.name = 'is_admin';
            isAdminInput.value = isAdmin;
            form.appendChild(isAdminInput);

            // Create toggle_admin input
            const toggleAdminInput = document.createElement('input');
            toggleAdminInput.type = 'hidden';
            toggleAdminInput.name = 'toggle_admin';
            toggleAdminInput.value = '1';
            form.appendChild(toggleAdminInput);

            // Append form to body and submit
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Old Role Change Modal Functions (kept for reference)
    function confirmRoleChange(userId, isAdmin, userName) {
        const newRole = isAdmin ? 'User' : 'Admin';
        const currentRole = isAdmin ? 'Admin' : 'User';

        // Set message
        document.getElementById('roleChangeMessage').innerHTML =
            `Are you sure you want to change <strong>${userName}</strong>'s role from <strong>${currentRole}</strong> to <strong>${newRole}</strong>?`;

        // Set form values
        document.getElementById('role_user_id').value = userId;
        document.getElementById('role_is_admin').value = isAdmin;

        // Update role cards
        updateRoleCards(currentRole, newRole);

        // Show modal
        document.getElementById('roleChangeModal').classList.remove('hidden');
        document.getElementById('roleChangeModal').classList.add('flex');
    }

    function updateRoleCards(currentRole, newRole) {
        // Current role card
        const currentRoleIcon = document.getElementById('current-role-icon');
        const currentRoleTitle = document.getElementById('current-role-title');
        const currentRoleDesc = document.getElementById('current-role-desc');
        const currentRoleCard = document.getElementById('current-role-card');

        // New role card
        const newRoleIcon = document.getElementById('new-role-icon');
        const newRoleTitle = document.getElementById('new-role-title');
        const newRoleDesc = document.getElementById('new-role-desc');
        const newRoleCard = document.getElementById('new-role-card');

        // Set current role info
        if (currentRole === 'Admin') {
            currentRoleIcon.innerHTML = '<i class="fas fa-user-shield"></i>';
            currentRoleTitle.textContent = 'Administrator';
            currentRoleDesc.textContent = 'Full access to manage users, spots, and bookings';
            currentRoleCard.classList.add('bg-green-50');
            currentRoleCard.classList.remove('bg-yellow-50');
        } else {
            currentRoleIcon.innerHTML = '<i class="fas fa-user"></i>';
            currentRoleTitle.textContent = 'Regular User';
            currentRoleDesc.textContent = 'Can book parking spots and manage own bookings';
            currentRoleCard.classList.add('bg-yellow-50');
            currentRoleCard.classList.remove('bg-green-50');
        }

        // Set new role info
        if (newRole === 'Admin') {
            newRoleIcon.innerHTML = '<i class="fas fa-user-shield"></i>';
            newRoleTitle.textContent = 'Administrator';
            newRoleDesc.textContent = 'Full access to manage users, spots, and bookings';
            newRoleCard.classList.add('bg-green-50');
            newRoleCard.classList.remove('bg-yellow-50');
        } else {
            newRoleIcon.innerHTML = '<i class="fas fa-user"></i>';
            newRoleTitle.textContent = 'Regular User';
            newRoleDesc.textContent = 'Can book parking spots and manage own bookings';
            newRoleCard.classList.add('bg-yellow-50');
            newRoleCard.classList.remove('bg-green-50');
        }
    }

    function hideRoleChangeModal() {
        document.getElementById('roleChangeModal').classList.remove('flex');
        document.getElementById('roleChangeModal').classList.add('hidden');
    }

    function viewUser(id) {
        // Redirect to user details page
        window.location.href = 'user-details.php?id=' + id;
    }

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
        let feedback = '';

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
            strengthDiv.innerHTML = '<div class="flex items-center mt-1"><div class="h-1.5 w-1/3 bg-red-400 rounded mr-1"></div><span class="text-xs text-red-500">Weak</span></div>';
        } else if (strength < 4) {
            strengthDiv.innerHTML = '<div class="flex items-center mt-1"><div class="h-1.5 w-2/3 bg-yellow-400 rounded mr-1"></div><span class="text-xs text-yellow-600">Medium</span></div>';
        } else {
            strengthDiv.innerHTML = '<div class="flex items-center mt-1"><div class="h-1.5 w-full bg-green-400 rounded mr-1"></div><span class="text-xs text-green-600">Strong</span></div>';
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
            messageElement.innerHTML = '<span class="text-xs text-green-600"><i class="fas fa-check-circle"></i> Passwords match</span>';
        } else {
            messageElement.innerHTML = '<span class="text-xs text-red-600"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
        }
    });

    // Role Selection
    const roleRadios = document.querySelectorAll('input[name="user_role"]');
    roleRadios.forEach(radio => {
        radio.addEventListener('change', updateRoleSelection);
    });

    function updateRoleSelection() {
        const selectedRadio = document.querySelector('input[name="user_role"]:checked');

        // If no radio is checked, check the first one (user role)
        if (!selectedRadio) {
            const firstRadio = document.querySelector('input[name="user_role"][value="user"]');
            if (firstRadio) {
                firstRadio.checked = true;
            }
        }

        const selectedRole = document.querySelector('input[name="user_role"]:checked')?.value || 'user';
        document.getElementById('is_admin_value').value = selectedRole === 'admin' ? 1 : 0;

        // Update visual selection with Tailwind classes
        const roleLabels = document.querySelectorAll('input[name="user_role"]').forEach(radio => {
            const label = radio.closest('label');
            if (radio.checked) {
                label.classList.add('border-primary-500', 'bg-primary-50');
            } else {
                label.classList.remove('border-primary-500', 'bg-primary-50');
            }
        });
    }

    // Form Validation
    function validateAddUserForm() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (password !== confirmPassword) {
            alert('Passwords do not match. Please try again.');
            return false;
        }

        if (password.length < 6) {
            alert('Password must be at least 6 characters long.');
            return false;
        }

        return true;
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Make sure all modals are hidden initially
        document.getElementById('addUserModal').classList.add('hidden');
        document.getElementById('editUserModal').classList.add('hidden');
        document.getElementById('roleChangeModal').classList.add('hidden');

        // Set up the default role selection
        const userRoleRadio = document.querySelector('input[name="user_role"][value="user"]');
        if (userRoleRadio) {
            userRoleRadio.checked = true;
        }

        // Initialize role selection
        updateRoleSelection();
    });
    </script>
</body>
</html>
