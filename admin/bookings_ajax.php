<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Update expired bookings
updateExpiredBookings();

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

// Start output buffering to capture HTML
ob_start();

// Generate table HTML
if ($bookings->num_rows > 0) {
    while($booking = $bookings->fetch_assoc()) {
        ?>
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
        <?php
    }
} else {
    ?>
    <tr>
        <td colspan="9" class="px-6 py-10 text-center text-sm text-gray-500">No bookings found</td>
    </tr>
    <?php
}

$tableHtml = ob_get_clean();

// Generate pagination HTML
ob_start();

if ($total_pages > 1) {
    ?>
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
    <?php
}

$paginationHtml = ob_get_clean();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'tableHtml' => $tableHtml,
    'paginationHtml' => $paginationHtml,
    'totalRecords' => $total_bookings,
    'totalPages' => $total_pages,
    'currentPage' => $page
]);
