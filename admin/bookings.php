<?php
session_start();
require_once '../config.php';
require_once '../calculate-amount.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Update expired bookings
updateExpiredBookings();

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cancel booking
    if (isset($_POST['cancel_booking'])) {
        $booking_id = $_POST['booking_id'];

        // Get booking details
        $booking_sql = "SELECT b.*, p.id as spot_id FROM bookings b JOIN parking_spots p ON b.spot_id = p.id WHERE b.id = ?";
        $booking_stmt = $conn->prepare($booking_sql);
        $booking_stmt->bind_param("i", $booking_id);
        $booking_stmt->execute();
        $booking = $booking_stmt->get_result()->fetch_assoc();

        if ($booking) {
            // Start transaction
            $conn->begin_transaction();

            try {
                // Update booking status
                $update_booking_sql = "UPDATE bookings SET status = 'cancelled' WHERE id = ?";
                $update_booking_stmt = $conn->prepare($update_booking_sql);
                $update_booking_stmt->bind_param("i", $booking_id);
                $update_booking_stmt->execute();

                // Free up the parking spot
                $update_spot_sql = "UPDATE parking_spots SET is_occupied = 0, vehicle_number = NULL WHERE id = ?";
                $update_spot_stmt = $conn->prepare($update_spot_sql);
                $update_spot_stmt->bind_param("i", $booking['spot_id']);
                $update_spot_stmt->execute();

                $conn->commit();
                $_SESSION['message'] = "Booking cancelled successfully.";
                $_SESSION['message_type'] = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['message'] = "Error cancelling booking: " . $e->getMessage();
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "Booking not found.";
            $_SESSION['message_type'] = "error";
        }

        header("Location: bookings.php");
        exit();
    }

    // Complete booking
    if (isset($_POST['complete_booking'])) {
        $booking_id = $_POST['booking_id'];
        $end_time = $_POST['end_time'];
        $paid_amount = $_POST['paid_amount'];
        $payment_method = $_POST['payment_method'];

        // Get booking details
        $booking_sql = "SELECT b.*, p.id as spot_id, p.hourly_rate FROM bookings b JOIN parking_spots p ON b.spot_id = p.id WHERE b.id = ?";
        $booking_stmt = $conn->prepare($booking_sql);
        $booking_stmt->bind_param("i", $booking_id);
        $booking_stmt->execute();
        $booking = $booking_stmt->get_result()->fetch_assoc();

        if ($booking) {
            // Start transaction
            $conn->begin_transaction();

            try {
                // Calculate final amount based on the end time
                $final_amount = calculate_booking_amount(
                    $booking['start_time'],
                    $end_time,
                    $booking['hourly_rate']
                );

                // Get transaction ID or generate one if not provided
                $transaction_id = !empty($_POST['transaction_id']) ? $_POST['transaction_id'] : 'TXN' . time() . rand(1000, 9999);

                // Insert payment record
                $payment_sql = "INSERT INTO payments (booking_id, amount, payment_date, payment_method, transaction_id, payment_status)
                                VALUES (?, ?, NOW(), ?, ?, 'paid')";
                $payment_stmt = $conn->prepare($payment_sql);
                $payment_stmt->bind_param("idss", $booking_id, $paid_amount, $payment_method, $transaction_id);
                $payment_stmt->execute();

                // Update booking status and amount
                $update_booking_sql = "UPDATE bookings SET status = 'completed', end_time = ?, amount = ?, payment_status = 'paid' WHERE id = ?";
                $update_booking_stmt = $conn->prepare($update_booking_sql);
                $update_booking_stmt->bind_param("sdi", $end_time, $final_amount, $booking_id);
                $update_booking_stmt->execute();

                // Free up the parking spot
                $update_spot_sql = "UPDATE parking_spots SET is_occupied = 0, vehicle_number = NULL WHERE id = ?";
                $update_spot_stmt = $conn->prepare($update_spot_sql);
                $update_spot_stmt->bind_param("i", $booking['spot_id']);
                $update_spot_stmt->execute();

                $conn->commit();
                $_SESSION['message'] = "Booking completed successfully. Payment recorded with transaction ID: " . $transaction_id;
                $_SESSION['message_type'] = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['message'] = "Error completing booking: " . $e->getMessage();
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "Booking not found.";
            $_SESSION['message_type'] = "error";
        }

        header("Location: bookings.php");
        exit();
    }
}

// Filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$id_filter = isset($_GET['id_filter']) ? $_GET['id_filter'] : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Build query based on filters
$where_clauses = [];
$params = [];
$types = "";

if ($id_filter) {
    // Exact match for ID
    $where_clauses[] = "b.id = ?";
    $params[] = $id_filter;
    $types .= "i";
}

if ($status_filter) {
    $where_clauses[] = "b.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($date_from) {
    $where_clauses[] = "b.start_time >= ?";
    $params[] = $date_from . " 00:00:00";
    $types .= "s";
}

if ($date_to) {
    $where_clauses[] = "b.end_time <= ?";
    $params[] = $date_to . " 23:59:59";
    $types .= "s";
}

if ($search) {
    $search_term = "%$search%";
    $where_clauses[] = "(u.name LIKE ? OR u.email LIKE ? OR p.spot_number LIKE ? OR b.vehicle_number LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total number of bookings with filters
$total_sql = "SELECT COUNT(*) as total
              FROM bookings b
              JOIN users u ON b.user_id = u.id
              JOIN parking_spots p ON b.spot_id = p.id
              $where_sql";

$total_stmt = $conn->prepare($total_sql);
if (!empty($params)) {
    $total_stmt->bind_param($types, ...$params);
}
$total_stmt->execute();
$total_bookings = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_bookings / $records_per_page);

// Get bookings with pagination and filters
$bookings_sql = "SELECT b.*, u.name as user_name, u.email as user_email, p.spot_number, p.floor_number, p.type
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN parking_spots p ON b.spot_id = p.id
                $where_sql
                ORDER BY b.id DESC
                LIMIT ?, ?";

$bookings_stmt = $conn->prepare($bookings_sql);
$params[] = $offset;
$params[] = $records_per_page;
$types .= "ii";
$bookings_stmt->bind_param($types, ...$params);
$bookings_stmt->execute();
$bookings = $bookings_stmt->get_result();

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
    <title>Bookings Management - Smart Parking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Add a subtle transition for filter changes */
        .filter-transition {
            transition: all 0.3s ease;
        }

        /* Style for the auto-filter indicator */
        .auto-filter-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.4rem;
            border-radius: 0.25rem;
            font-size: 0.65rem;
            font-weight: 500;
            background-color: rgba(99, 113, 241, 0.1);
            color: #6371f1;
            margin-left: 0.25rem;
        }

        /* Style for the clear button */
        .clear-filter-btn {
            transition: all 0.2s ease;
        }

        .clear-filter-btn:hover {
            transform: scale(1.1);
        }
    </style>
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
    <script>
        // Auto-filter functionality with AJAX - no page reload
        document.addEventListener('DOMContentLoaded', function() {
            const idFilterInput = document.getElementById('id_filter');
            const clearIdFilterBtn = document.getElementById('clear-id-filter');
            const searchInput = document.getElementById('search');
            const statusSelect = document.getElementById('status');
            const dateFromInput = document.getElementById('date_from');
            const dateToInput = document.getElementById('date_to');
            const bookingsTableBody = document.querySelector('.bookings-table tbody');
            const paginationContainer = document.querySelector('.pagination-container');
            let debounceTimer;

            // Function to update URL without page reload
            function updateFiltersWithAjax() {
                // Show loading indicator
                document.body.classList.add('cursor-wait');

                // Build query parameters
                let params = new URLSearchParams();

                if (idFilterInput && idFilterInput.value.trim() !== '') {
                    params.append('id_filter', idFilterInput.value.trim());

                    // Clear search field if ID filter is used
                    if (searchInput) {
                        searchInput.value = '';
                    }
                }

                if (statusSelect && statusSelect.value !== '') {
                    params.append('status', statusSelect.value);
                }

                if (dateFromInput && dateFromInput.value !== '') {
                    params.append('date_from', dateFromInput.value);
                }

                if (dateToInput && dateToInput.value !== '') {
                    params.append('date_to', dateToInput.value);
                }

                if (searchInput && searchInput.value.trim() !== '' && (!idFilterInput || idFilterInput.value.trim() === '')) {
                    params.append('search', searchInput.value.trim());
                }

                // Add ajax=1 parameter to indicate this is an AJAX request
                params.append('ajax', '1');

                // Get current page from URL or default to 1
                const urlParams = new URLSearchParams(window.location.search);
                const currentPage = urlParams.get('page') || '1';
                params.append('page', currentPage);

                // Update URL without reloading
                const baseUrl = window.location.href.split('?')[0];
                const newUrl = baseUrl + '?' + params.toString();
                window.history.pushState({ path: newUrl }, '', newUrl);

                // Make AJAX request
                fetch('bookings_ajax.php?' + params.toString())
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Update the table with new data
                        if (bookingsTableBody) {
                            bookingsTableBody.innerHTML = data.tableHtml;
                        }

                        // Update pagination if it exists
                        if (paginationContainer && data.paginationHtml) {
                            paginationContainer.innerHTML = data.paginationHtml;
                        }

                        // Remove loading indicator
                        document.body.classList.remove('cursor-wait');
                    })
                    .catch(error => {
                        console.error('Error fetching data:', error);
                        // Remove loading indicator
                        document.body.classList.remove('cursor-wait');

                        // If AJAX fails, fall back to regular page load
                        window.location.href = newUrl;
                    });
            }

            // Handle ID filter input
            if (idFilterInput) {
                idFilterInput.addEventListener('input', function(e) {
                    // Clear previous timeout
                    clearTimeout(debounceTimer);

                    // Set new timeout (debounce)
                    debounceTimer = setTimeout(function() {
                        updateFiltersWithAjax();
                    }, 500); // 500ms delay for typing
                });

                // Add a clear button functionality
                idFilterInput.addEventListener('keydown', function(e) {
                    // If Escape key is pressed, clear the input
                    if (e.key === 'Escape') {
                        idFilterInput.value = '';
                        updateFiltersWithAjax();
                    }
                });

                // Clear button click handler
                if (clearIdFilterBtn) {
                    clearIdFilterBtn.addEventListener('click', function() {
                        idFilterInput.value = '';
                        updateFiltersWithAjax();
                    });
                }
            }

            // Handle other filter changes
            if (statusSelect) {
                statusSelect.addEventListener('change', updateFiltersWithAjax);
            }

            if (dateFromInput) {
                dateFromInput.addEventListener('change', updateFiltersWithAjax);
            }

            if (dateToInput) {
                dateToInput.addEventListener('change', updateFiltersWithAjax);
            }

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(updateFiltersWithAjax, 500);
                });
            }

            // Handle pagination clicks using event delegation
            document.addEventListener('click', function(e) {
                // Check if the clicked element is a pagination link
                if (e.target.closest('.pagination-link')) {
                    e.preventDefault();
                    const pageLink = e.target.closest('.pagination-link');
                    const pageUrl = pageLink.getAttribute('href');

                    if (pageUrl) {
                        // Extract page number from URL
                        const pageParams = new URLSearchParams(pageUrl.split('?')[1]);
                        const pageNum = pageParams.get('page') || '1';

                        // Update current URL with new page number
                        const currentParams = new URLSearchParams(window.location.search);
                        currentParams.set('page', pageNum);

                        // Update URL without reloading
                        const baseUrl = window.location.href.split('?')[0];
                        const newUrl = baseUrl + '?' + currentParams.toString();
                        window.history.pushState({ path: newUrl }, '', newUrl);

                        // Make AJAX request with current filters and new page
                        currentParams.append('ajax', '1');
                        fetch('bookings_ajax.php?' + currentParams.toString())
                            .then(response => response.json())
                            .then(data => {
                                // Update the table with new data
                                if (bookingsTableBody) {
                                    bookingsTableBody.innerHTML = data.tableHtml;
                                }

                                // Update pagination
                                if (paginationContainer && data.paginationHtml) {
                                    paginationContainer.innerHTML = data.paginationHtml;
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching page data:', error);
                                // If AJAX fails, fall back to regular page load
                                window.location.href = pageUrl;
                            });
                    }
                }
            });
        });
    </script>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-6 py-4">
                    <h1 class="text-2xl font-semibold text-gray-800">Bookings Management</h1>
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

                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <form id="filter-form" method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                        <div>
                            <label for="id_filter" class="block text-sm font-medium text-gray-700 mb-1">
                                Booking ID <span class="auto-filter-badge">auto-filter</span>
                            </label>
                            <div class="relative filter-transition">
                                <input type="number" id="id_filter" name="id_filter" placeholder="Enter ID"
                                       value="<?php echo htmlspecialchars($id_filter); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                       autocomplete="off">
                                <?php if ($id_filter): ?>
                                <button type="button" id="clear-id-filter" class="clear-filter-btn absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-red-500">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="mt-1 text-xs text-gray-500">Type to filter automatically</div>
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="">All</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text" id="search" name="search" placeholder="User, Email, Spot, Vehicle"
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </button>
                            <a href="bookings.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all">
                                <i class="fas fa-sync mr-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Bookings Table -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full bookings-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spot</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if ($bookings->num_rows > 0): ?>
                                    <?php while($booking = $bookings->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $booking['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($booking['user_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($booking['user_email']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($booking['spot_number']); ?></div>
                                            <div class="text-xs text-gray-500">Floor <?php echo $booking['floor_number']; ?>, <?php echo ucfirst($booking['type']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($booking['vehicle_number']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('M d, g:i A', strtotime($booking['start_time'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('M d, g:i A', strtotime($booking['end_time'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $statusClasses = [
                                                'active' => 'bg-green-100 text-green-800',
                                                'completed' => 'bg-blue-100 text-blue-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                                'pending' => 'bg-yellow-100 text-yellow-800'
                                            ];
                                            $status = strtolower($booking['status']);
                                            $statusClass = isset($statusClasses[$status]) ? $statusClasses[$status] : 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($booking['amount'], 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <?php if ($booking['status'] === 'active'): ?>
                                                <div class="flex space-x-3">
                                                    <button type="button"
                                                            class="text-blue-600 hover:text-blue-900"
                                                            onclick="openCompleteModal(<?php echo $booking['id']; ?>, '<?php echo $booking['end_time']; ?>', <?php echo $booking['amount']; ?>)"
                                                            title="Complete Booking">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                    <form method="POST" action="" class="inline">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <button type="submit" name="cancel_booking"
                                                                class="text-red-600 hover:text-red-900"
                                                                onclick="return confirm('Are you sure you want to cancel this booking?');"
                                                                title="Cancel Booking">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="px-6 py-10 text-center text-sm text-gray-500">No bookings found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 bg-white border-t border-gray-200 pagination-container">
                        <div class="flex justify-center">
                            <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>&id_filter=<?php echo $id_filter; ?>"
                                       class="pagination-link relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
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
                                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>&id_filter=<?php echo $id_filter; ?>"
                                           class="pagination-link relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>&id_filter=<?php echo $id_filter; ?>"
                                       class="pagination-link relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
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

    <!-- Complete Booking Modal -->
    <div id="completeBookingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Complete Booking</h3>
                    <button type="button" onclick="closeCompleteModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <form id="completeBookingForm" method="POST" action="">
                <input type="hidden" name="booking_id" id="complete_booking_id">
                <div class="px-6 py-4">
                    <div class="mb-4">
                        <label for="end_time" class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                        <input type="datetime-local" id="end_time" name="end_time"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                               required>
                        <p class="mt-1 text-xs text-gray-500">Default is the scheduled end time. Change if needed.</p>
                    </div>
                    <div class="mb-4">
                        <label for="calculated_amount" class="block text-sm font-medium text-gray-700 mb-1">Calculated Amount</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500">$</span>
                            </div>
                            <input type="text" id="calculated_amount"
                                   class="w-full pl-7 px-3 py-2 border border-gray-300 rounded-lg bg-gray-100"
                                   readonly>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="paid_amount" class="block text-sm font-medium text-gray-700 mb-1">Paid Amount</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500">$</span>
                            </div>
                            <input type="number" id="paid_amount" name="paid_amount" step="0.01" min="0"
                                   class="w-full pl-7 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select id="payment_method" name="payment_method"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                required>
                            <option value="">Select payment method</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="paypal">PayPal</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="transaction_id" class="block text-sm font-medium text-gray-700 mb-1">Transaction ID</label>
                        <input type="text" id="transaction_id" name="transaction_id"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                               placeholder="Enter transaction ID (optional)">
                        <p class="mt-1 text-xs text-gray-500">Leave empty to auto-generate a transaction ID.</p>
                    </div>
                </div>
                <div class="px-6 py-3 bg-gray-50 text-right rounded-b-lg">
                    <button type="button" onclick="closeCompleteModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 mr-2">
                        Cancel
                    </button>
                    <button type="submit" name="complete_booking" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                        Complete Booking
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Variables to store booking details
        let currentBookingId = null;
        let currentEndTime = null;
        let currentAmount = null;
        let hourlyRate = null;
        let startTime = null;

        // Function to open the complete booking modal
        function openCompleteModal(bookingId, endTime, amount) {
            currentBookingId = bookingId;
            currentEndTime = endTime;
            currentAmount = amount;

            // Set the booking ID in the form
            document.getElementById('complete_booking_id').value = bookingId;

            // Format the end time for the datetime-local input
            const formattedEndTime = formatDateTimeForInput(endTime);
            document.getElementById('end_time').value = formattedEndTime;

            // Set the initial calculated amount
            document.getElementById('calculated_amount').value = amount.toFixed(2);

            // Set the initial paid amount to match the calculated amount
            document.getElementById('paid_amount').value = amount.toFixed(2);

            // Show the modal
            document.getElementById('completeBookingModal').classList.remove('hidden');

            // Fetch booking details to get hourly rate and start time
            fetchBookingDetails(bookingId);
        }

        // Function to close the complete booking modal
        function closeCompleteModal() {
            document.getElementById('completeBookingModal').classList.add('hidden');
        }

        // Function to format datetime for datetime-local input
        function formatDateTimeForInput(dateTimeStr) {
            const dt = new Date(dateTimeStr);
            return dt.getFullYear() + '-' +
                   String(dt.getMonth() + 1).padStart(2, '0') + '-' +
                   String(dt.getDate()).padStart(2, '0') + 'T' +
                   String(dt.getHours()).padStart(2, '0') + ':' +
                   String(dt.getMinutes()).padStart(2, '0');
        }

        // Function to fetch booking details
        function fetchBookingDetails(bookingId) {
            // This would typically be an AJAX call to get the booking details
            // For simplicity, we'll use a placeholder that assumes we already have the data
            // In a real implementation, you would fetch this data from the server

            // For now, we'll just add an event listener to recalculate when end time changes
            document.getElementById('end_time').addEventListener('change', recalculateAmount);
        }

        // Function to recalculate amount based on end time
        function recalculateAmount() {
            const endTimeInput = document.getElementById('end_time').value;
            if (!endTimeInput) return;

            // Make an AJAX call to calculate the new amount
            const formData = new FormData();
            formData.append('booking_id', currentBookingId);
            formData.append('end_time', endTimeInput);
            formData.append('action', 'calculate_amount');

            fetch('calculate_booking_amount.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the calculated amount
                    document.getElementById('calculated_amount').value = data.amount.toFixed(2);
                    // Also update the paid amount to match by default
                    document.getElementById('paid_amount').value = data.amount.toFixed(2);
                } else {
                    console.error('Error calculating amount:', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('completeBookingModal');
            if (event.target === modal) {
                closeCompleteModal();
            }
        });
    </script>
</body>
</html>
