<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Handle AJAX request to calculate booking amount
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'calculate_amount') {
    $booking_id = $_POST['booking_id'];
    $end_time = $_POST['end_time'];
    
    // Get booking details
    $booking_sql = "SELECT b.*, p.hourly_rate, p.id as spot_id 
                   FROM bookings b 
                   JOIN parking_spots p ON b.spot_id = p.id 
                   WHERE b.id = ?";
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("i", $booking_id);
    $booking_stmt->execute();
    $booking = $booking_stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit();
    }
    
    // Calculate amount based on the new end time
    $amount = calculate_booking_amount(
        $booking['start_time'],
        $end_time,
        $booking['hourly_rate']
    );
    
    echo json_encode([
        'success' => true, 
        'amount' => $amount,
        'original_amount' => $booking['amount']
    ]);
    exit();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}
?>
