<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Check if user ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['message'] = "User ID is required.";
    $_SESSION['message_type'] = "error";
    header("Location: users.php");
    exit();
}

$user_id = $_GET['id'];

// Get user information
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    $_SESSION['message'] = "User not found.";
    $_SESSION['message_type'] = "error";
    header("Location: users.php");
    exit();
}

$user = $user_result->fetch_assoc();

// Get user's bookings
$bookings_sql = "SELECT b.*, p.spot_number, p.floor_number, p.type
                FROM bookings b
                JOIN parking_spots p ON b.spot_id = p.id
                WHERE b.user_id = ?
                ORDER BY b.id DESC";
$bookings_stmt = $conn->prepare($bookings_sql);
$bookings_stmt->bind_param("i", $user_id);
$bookings_stmt->execute();
$bookings = $bookings_stmt->get_result();

// Get user's booking statistics
$stats_sql = "SELECT
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(amount) as total_amount
              FROM bookings
              WHERE user_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

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
    <title>User Details - Smart Parking System</title>
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
                    <h1 class="text-2xl font-semibold text-gray-800">User Details</h1>
                    <div class="flex items-center gap-3">
                        <button class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all flex items-center gap-2"
                               onclick="window.location.href='users.php'">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </button>
                        <button class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all flex items-center gap-2"
                               onclick="window.location.href='edit-user.php?id=<?php echo $user['id']; ?>'">
                            <i class="fas fa-edit"></i> Edit User
                        </button>
                    </div>
                </div>
            </header>

            <?php if ($message): ?>
            <div class="mx-6 mt-4 px-4 py-3 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> flex items-center">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- User Profile -->
            <div class="mx-6 mt-6 bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-6 flex flex-col md:flex-row items-center md:items-start gap-6">
                    <div class="flex-shrink-0">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random&size=128"
                             alt="User Avatar"
                             class="w-32 h-32 rounded-full border-4 border-primary-100">
                    </div>
                    <div class="flex-grow text-center md:text-left">
                        <h2 class="text-2xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($user['name']); ?></h2>
                        <p class="text-gray-600 mb-1 flex items-center justify-center md:justify-start">
                            <i class="fas fa-envelope text-gray-400 mr-2"></i> <?php echo htmlspecialchars($user['email']); ?>
                        </p>
                        <p class="text-gray-600 mb-3 flex items-center justify-center md:justify-start">
                            <i class="fas fa-calendar-alt text-gray-400 mr-2"></i> Joined: <?php echo date('F d, Y', strtotime($user['created_at'])); ?>
                        </p>
                        <div class="flex items-center justify-center md:justify-start gap-3 mt-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                  <?php echo $user['is_admin'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                <i class="fas fa-<?php echo $user['is_admin'] ? 'user-shield' : 'user'; ?> mr-1"></i>
                                <?php echo $user['is_admin'] ? 'Admin' : 'User'; ?>
                            </span>
                            <button class="text-sm px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-all"
                                   onclick="confirmRoleChangeWithAlert(<?php echo $user['id']; ?>, <?php echo $user['is_admin']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
                                Change Role
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 mx-6 mt-6">
                <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                    <div class="bg-primary-100 text-primary-700 p-3 rounded-lg">
                        <i class="fas fa-calendar-check text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Total Bookings</h3>
                        <p class="text-2xl font-semibold text-gray-800"><?php echo $stats['total_bookings']; ?></p>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                    <div class="bg-blue-100 text-blue-700 p-3 rounded-lg">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Active Bookings</h3>
                        <p class="text-2xl font-semibold text-gray-800"><?php echo $stats['active_bookings']; ?></p>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                    <div class="bg-green-100 text-green-700 p-3 rounded-lg">
                        <i class="fas fa-check-circle text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Completed Bookings</h3>
                        <p class="text-2xl font-semibold text-gray-800"><?php echo $stats['completed_bookings']; ?></p>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                    <div class="bg-red-100 text-red-700 p-3 rounded-lg">
                        <i class="fas fa-times-circle text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Cancelled Bookings</h3>
                        <p class="text-2xl font-semibold text-gray-800"><?php echo $stats['cancelled_bookings']; ?></p>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 flex items-center gap-4 hover:shadow-md transition-shadow">
                    <div class="bg-yellow-100 text-yellow-700 p-3 rounded-lg">
                        <i class="fas fa-money-bill-wave text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Total Amount</h3>
                        <p class="text-2xl font-semibold text-gray-800">$<?php echo number_format($stats['total_amount'], 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- User Bookings -->
            <div class="mx-6 mt-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Booking History</h2>
                </div>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spot</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($bookings->num_rows > 0): ?>
                                    <?php while($booking = $bookings->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $booking['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($booking['spot_number']); ?>
                                            <span class="text-xs text-gray-400">(Floor <?php echo $booking['floor_number']; ?>, <?php echo ucfirst($booking['type']); ?>)</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($booking['vehicle_number']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y h:i A', strtotime($booking['start_time'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y h:i A', strtotime($booking['end_time'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$<?php echo number_format($booking['amount'], 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php
                                                    $status_class = '';
                                                    switch(strtolower($booking['status'])) {
                                                        case 'active':
                                                            $status_class = 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'completed':
                                                            $status_class = 'bg-blue-100 text-blue-800';
                                                            break;
                                                        case 'cancelled':
                                                            $status_class = 'bg-red-100 text-red-800';
                                                            break;
                                                        case 'pending':
                                                            $status_class = 'bg-yellow-100 text-yellow-800';
                                                            break;
                                                        default:
                                                            $status_class = 'bg-gray-100 text-gray-800';
                                                    }
                                                    echo $status_class;
                                                ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="text-primary-600 hover:text-primary-900">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500">No bookings found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center" style="display: none;">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4">
            <div class="flex justify-between items-center border-b px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-800">Edit User</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="hideEditUserModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST" action="users.php" id="editUserForm">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <div class="mb-4">
                        <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" id="edit_name" name="name"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                               required>
                    </div>
                    <div class="mb-4">
                        <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="edit_email" name="email"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                               required>
                    </div>
                    <div class="mb-4">
                        <label for="edit_password" class="block text-sm font-medium text-gray-700 mb-1">Password (leave blank to keep current)</label>
                        <input type="password" id="edit_password" name="password"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="mb-6">
                        <label class="flex items-center">
                            <input type="checkbox" id="edit_is_admin" name="is_admin"
                                   class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Admin User</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all"
                                onclick="hideEditUserModal()">Cancel</button>
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
    <div id="roleChangeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center" style="display: none;">
        <div class="bg-white rounded-xl shadow-lg max-w-lg w-full mx-4">
            <div class="flex justify-between items-center border-b px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-user-shield text-primary-600 mr-2"></i> Confirm Role Change</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="hideRoleChangeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="flex items-center gap-4 p-4 bg-yellow-50 text-yellow-800 rounded-lg mb-6">
                    <div class="text-2xl text-yellow-600">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <p id="roleChangeMessage" class="text-sm"></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200" id="current-role-card">
                        <div class="text-sm font-medium text-gray-500 mb-2">Current Role</div>
                        <div class="text-3xl text-center my-3" id="current-role-icon"></div>
                        <div class="text-lg font-semibold text-gray-800 text-center" id="current-role-title"></div>
                        <div class="text-sm text-gray-600 text-center mt-2" id="current-role-desc"></div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200" id="new-role-card">
                        <div class="text-sm font-medium text-gray-500 mb-2">New Role</div>
                        <div class="text-3xl text-center my-3" id="new-role-icon"></div>
                        <div class="text-lg font-semibold text-gray-800 text-center" id="new-role-title"></div>
                        <div class="text-sm text-gray-600 text-center mt-2" id="new-role-desc"></div>
                    </div>
                </div>

                <form method="POST" action="users.php" id="roleChangeForm">
                    <input type="hidden" id="role_user_id" name="user_id">
                    <input type="hidden" id="role_is_admin" name="is_admin">
                    <div class="flex justify-end gap-3">
                        <button type="button" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all flex items-center gap-2"
                                onclick="hideRoleChangeModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="toggle_admin"
                                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all flex items-center gap-2">
                            <i class="fas fa-check"></i> Confirm Change
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script>
    // Edit User Modal Functions
    function editUser(id, name, email, isAdmin) {
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_is_admin').checked = isAdmin === 1;
        document.getElementById('editUserModal').style.display = 'flex';
    }

    function hideEditUserModal() {
        document.getElementById('editUserModal').style.display = 'none';
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
            form.action = 'users.php';

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

    // Role Change Modal Functions
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
        document.getElementById('roleChangeModal').style.display = 'flex';
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
            currentRoleIcon.innerHTML = '<i class="fas fa-user-shield text-primary-600"></i>';
            currentRoleTitle.textContent = 'Administrator';
            currentRoleDesc.textContent = 'Full access to manage users, spots, and bookings';
            currentRoleCard.classList.add('border-primary-200', 'bg-primary-50');
            currentRoleCard.classList.remove('border-yellow-200', 'bg-yellow-50');
        } else {
            currentRoleIcon.innerHTML = '<i class="fas fa-user text-yellow-600"></i>';
            currentRoleTitle.textContent = 'Regular User';
            currentRoleDesc.textContent = 'Can book parking spots and manage own bookings';
            currentRoleCard.classList.add('border-yellow-200', 'bg-yellow-50');
            currentRoleCard.classList.remove('border-primary-200', 'bg-primary-50');
        }

        // Set new role info
        if (newRole === 'Admin') {
            newRoleIcon.innerHTML = '<i class="fas fa-user-shield text-primary-600"></i>';
            newRoleTitle.textContent = 'Administrator';
            newRoleDesc.textContent = 'Full access to manage users, spots, and bookings';
            newRoleCard.classList.add('border-primary-200', 'bg-primary-50');
            newRoleCard.classList.remove('border-yellow-200', 'bg-yellow-50');
        } else {
            newRoleIcon.innerHTML = '<i class="fas fa-user text-yellow-600"></i>';
            newRoleTitle.textContent = 'Regular User';
            newRoleDesc.textContent = 'Can book parking spots and manage own bookings';
            newRoleCard.classList.add('border-yellow-200', 'bg-yellow-50');
            newRoleCard.classList.remove('border-primary-200', 'bg-primary-50');
        }
    }

    function hideRoleChangeModal() {
        document.getElementById('roleChangeModal').style.display = 'none';
    }
    </script>
</body>
</html>
