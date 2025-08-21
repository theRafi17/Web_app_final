<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Handle parking spot actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new parking spot
    if (isset($_POST['add_spot'])) {
        $spot_number = $_POST['spot_number'];
        $floor_number = $_POST['floor_number'];
        $type = $_POST['type'];
        $hourly_rate = $_POST['hourly_rate'];

        // Check if spot number already exists
        $check_sql = "SELECT COUNT(*) as count FROM parking_spots WHERE spot_number = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $spot_number);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();

        if ($result['count'] > 0) {
            $_SESSION['message'] = "Spot number already exists.";
            $_SESSION['message_type'] = "error";
        } else {
            $sql = "INSERT INTO parking_spots (spot_number, floor_number, type, hourly_rate) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisd", $spot_number, $floor_number, $type, $hourly_rate);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Parking spot added successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error adding parking spot.";
                $_SESSION['message_type'] = "error";
            }
        }

        header("Location: parking-spots.php");
        exit();
    }

    // Edit parking spot
    if (isset($_POST['edit_spot'])) {
        $spot_id = $_POST['spot_id'];
        $spot_number = $_POST['spot_number'];
        $floor_number = $_POST['floor_number'];
        $type = $_POST['type'];
        $hourly_rate = $_POST['hourly_rate'];

        // Check if spot number already exists (excluding the current spot)
        $check_sql = "SELECT COUNT(*) as count FROM parking_spots WHERE spot_number = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $spot_number, $spot_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();

        if ($result['count'] > 0) {
            $_SESSION['message'] = "Spot number already exists.";
            $_SESSION['message_type'] = "error";
        } else {
            $sql = "UPDATE parking_spots SET spot_number = ?, floor_number = ?, type = ?, hourly_rate = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisdi", $spot_number, $floor_number, $type, $hourly_rate, $spot_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Parking spot updated successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating parking spot.";
                $_SESSION['message_type'] = "error";
            }
        }

        header("Location: parking-spots.php");
        exit();
    }

    // Delete parking spot
    if (isset($_POST['delete_spot'])) {
        $spot_id = $_POST['spot_id'];

        // Check if spot has any bookings
        $check_sql = "SELECT COUNT(*) as count FROM bookings WHERE spot_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $spot_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();

        if ($result['count'] > 0) {
            $_SESSION['message'] = "Cannot delete spot with existing bookings.";
            $_SESSION['message_type'] = "error";
        } else {
            $sql = "DELETE FROM parking_spots WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $spot_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Parking spot deleted successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting parking spot.";
                $_SESSION['message_type'] = "error";
            }
        }

        header("Location: parking-spots.php");
        exit();
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get total number of parking spots
$total_sql = "SELECT COUNT(*) as total FROM parking_spots";
$total_result = $conn->query($total_sql);
$total_spots = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_spots / $records_per_page);

// Get parking spots with pagination
$spots_sql = "SELECT * FROM parking_spots ORDER BY floor_number, spot_number LIMIT ?, ?";
$spots_stmt = $conn->prepare($spots_sql);
$spots_stmt->bind_param("ii", $offset, $records_per_page);
$spots_stmt->execute();
$spots = $spots_stmt->get_result();

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
    <title>Parking Spots Management - Smart Parking System</title>
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
                    <h1 class="text-2xl font-semibold text-gray-800">Parking Spots Management</h1>
                    <button class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all flex items-center gap-2"
                            onclick="showAddSpotModal()">
                        <i class="fas fa-plus"></i> Add New Spot
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

                <!-- Parking Spots Table -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spot Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Floor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hourly Rate</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if ($spots->num_rows > 0): ?>
                                    <?php while($spot = $spots->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $spot['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($spot['spot_number']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $spot['floor_number']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo ucfirst(htmlspecialchars($spot['type'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($spot['hourly_rate'], 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($spot['is_occupied']): ?>
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    Occupied
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Available
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button class="text-amber-600 hover:text-amber-900"
                                                        onclick="editSpot(<?php echo $spot['id']; ?>, '<?php echo htmlspecialchars($spot['spot_number']); ?>', <?php echo $spot['floor_number']; ?>, '<?php echo htmlspecialchars($spot['type']); ?>', <?php echo $spot['hourly_rate']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" action="" class="inline">
                                                    <input type="hidden" name="spot_id" value="<?php echo $spot['id']; ?>">
                                                    <button type="submit" name="delete_spot"
                                                            class="text-red-600 hover:text-red-900"
                                                            onclick="return confirm('Are you sure you want to delete this parking spot?');">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">No parking spots found</td>
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
                                    <a href="?page=<?php echo $page - 1; ?>"
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-primary-500 bg-primary-50 text-sm font-medium text-primary-600">
                                            <?php echo $i; ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="?page=<?php echo $i; ?>"
                                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>"
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

    <!-- Add Spot Modal -->
    <div id="addSpotModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Add New Parking Spot</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="hideAddSpotModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST" action="" id="addSpotForm">
                    <div class="space-y-4">
                        <div>
                            <label for="spot_number" class="block text-sm font-medium text-gray-700 mb-1">Spot Number</label>
                            <input type="text" id="spot_number" name="spot_number" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="floor_number" class="block text-sm font-medium text-gray-700 mb-1">Floor Number</label>
                            <input type="number" id="floor_number" name="floor_number" min="0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select id="type" name="type" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="Car">Car</option>
                                <option value="Bike">Bike</option>
                                <option value="VIP">VIP</option>
                                <option value="handicap">Handicap</option>
                                <option value="electric">Electric</option>
                                <option value="standard">Standard</option>
                            </select>
                        </div>
                        <div>
                            <label for="hourly_rate" class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate ($)</label>
                            <input type="number" id="hourly_rate" name="hourly_rate" min="0" step="0.01" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all"
                                onclick="hideAddSpotModal()">
                            Cancel
                        </button>
                        <button type="submit" name="add_spot"
                                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all">
                            Add Spot
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Spot Modal -->
    <div id="editSpotModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Edit Parking Spot</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="hideEditSpotModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST" action="" id="editSpotForm">
                    <input type="hidden" id="edit_spot_id" name="spot_id">
                    <div class="space-y-4">
                        <div>
                            <label for="edit_spot_number" class="block text-sm font-medium text-gray-700 mb-1">Spot Number</label>
                            <input type="text" id="edit_spot_number" name="spot_number" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="edit_floor_number" class="block text-sm font-medium text-gray-700 mb-1">Floor Number</label>
                            <input type="number" id="edit_floor_number" name="floor_number" min="0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="edit_type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select id="edit_type" name="type" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="Car">Car</option>
                                <option value="Bike">Bike</option>
                                <option value="VIP">VIP</option>
                                <option value="handicap">Handicap</option>
                                <option value="electric">Electric</option>
                                <option value="standard">Standard</option>
                            </select>
                        </div>
                        <div>
                            <label for="edit_hourly_rate" class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate ($)</label>
                            <input type="number" id="edit_hourly_rate" name="hourly_rate" min="0" step="0.01" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all"
                                onclick="hideEditSpotModal()">
                            Cancel
                        </button>
                        <button type="submit" name="edit_spot"
                                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all">
                            Update Spot
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    <script>
    function showAddSpotModal() {
        document.getElementById('addSpotModal').classList.remove('hidden');
        document.getElementById('addSpotModal').classList.add('flex');
    }

    function hideAddSpotModal() {
        document.getElementById('addSpotModal').classList.remove('flex');
        document.getElementById('addSpotModal').classList.add('hidden');
        document.getElementById('addSpotForm').reset();
    }

    function editSpot(id, spotNumber, floorNumber, type, hourlyRate) {
        document.getElementById('edit_spot_id').value = id;
        document.getElementById('edit_spot_number').value = spotNumber;
        document.getElementById('edit_floor_number').value = floorNumber;
        document.getElementById('edit_type').value = type;
        document.getElementById('edit_hourly_rate').value = hourlyRate;
        document.getElementById('editSpotModal').classList.remove('hidden');
        document.getElementById('editSpotModal').classList.add('flex');
    }

    function hideEditSpotModal() {
        document.getElementById('editSpotModal').classList.remove('flex');
        document.getElementById('editSpotModal').classList.add('hidden');
    }
    </script>
</body>
</html>
