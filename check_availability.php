<?php
session_start();
require_once 'config.php';

// Update expired bookings
updateExpiredBookings();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['start_time']) || !isset($data['end_time'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$start_time = $data['start_time'];
$end_time = $data['end_time'];

// Validate time range
$start_date = new DateTime($start_time);
$end_date = new DateTime($end_time);
$now = new DateTime();

if ($start_date < $now) {
    echo json_encode(['success' => false, 'message' => 'Start time cannot be in the past']);
    exit();
}

if ($end_date <= $start_date) {
    echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
    exit();
}

try {
    // Get all parking spots
    $spots_sql = "SELECT p.id, p.spot_number, p.floor_number, p.type, p.hourly_rate
                  FROM parking_spots p
                  ORDER BY p.floor_number, p.spot_number";
    $spots_result = $conn->query($spots_sql);

    $available_spots = [];
    while ($spot = $spots_result->fetch_assoc()) {
        // Check if spot is already booked for the time range
        // The correct logic is:
        // 1. Booking start time is between our start and end time
        // 2. OR Booking end time is between our start and end time
        // 3. OR Existing booking completely contains our time range
        // 4. OR Our time range completely contains existing booking
        $check_sql = "SELECT COUNT(*) as count
                     FROM bookings
                     WHERE spot_id = ?
                     AND status = 'active'
                     AND (
                         (start_time >= ? AND start_time < ?) OR  /* Booking start time is within our range */
                         (end_time > ? AND end_time <= ?) OR      /* Booking end time is within our range */
                         (start_time <= ? AND end_time >= ?) OR   /* Existing booking contains our time range */
                         (start_time >= ? AND end_time <= ?)      /* Our time range contains existing booking */
                     )";

        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("issssssss",
            $spot['id'],
            $start_time, $end_time,    // Booking start time is within our range
            $start_time, $end_time,    // Booking end time is within our range
            $start_time, $end_time,    // Existing booking contains our time range
            $start_time, $end_time     // Our time range contains existing booking
        );
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();

        // If there's no conflict, add to available spots
        if ($check_result['count'] == 0) {
            $available_spots[] = $spot['id']; // Just send the ID, which is what the frontend expects
        }
    }

    echo json_encode([
        'success' => true,
        'available_spots' => $available_spots,
        'message' => count($available_spots) > 0 ? 'Found ' . count($available_spots) . ' available spots' : 'No spots available for the selected time range'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking availability: ' . $e->getMessage()
    ]);
}