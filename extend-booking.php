<?php
session_start();
require_once 'config.php';
require_once 'calculate-amount.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$response = ['success' => false, 'message' => ''];

// Process booking extension
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id']) && isset($_POST['extend_hours'])) {
    $booking_id = $_POST['booking_id'];
    $extend_hours = (int)$_POST['extend_hours'];
    
    // Validate extension hours
    if ($extend_hours < 1 || $extend_hours > 24) {
        $_SESSION['message'] = "Invalid extension hours. Please select between 1 and 24 hours.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    // Verify that the booking belongs to the user and is active
    $verify_sql = "SELECT b.*, p.hourly_rate, p.spot_number, p.floor_number
                   FROM bookings b 
                   JOIN parking_spots p ON b.spot_id = p.id 
                   WHERE b.id = ? AND b.user_id = ? AND b.status = 'active'";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $booking_id, $user_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "Invalid booking or booking is not active.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    $booking = $result->fetch_assoc();
    
    // Check if the new end time would exceed 24 hours from start time
    $new_end_time = date('Y-m-d H:i:s', strtotime($booking['end_time'] . " + {$extend_hours} hours"));
    $max_end_time = date('Y-m-d H:i:s', strtotime($booking['start_time'] . " + 24 hours"));
    
    if (strtotime($new_end_time) > strtotime($max_end_time)) {
        $_SESSION['message'] = "Extension would exceed the maximum 24-hour booking period.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    // Get the current calculated amount
    $current_booking = calculateCurrentAmount($booking_id);
    $current_amount = $current_booking['current_amount'] ?? $booking['amount'];
    
    // Calculate additional cost
    $additional_cost = $extend_hours * $booking['hourly_rate'];
    $new_amount = $current_amount + $additional_cost;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking
        $update_sql = "UPDATE bookings SET end_time = ?, amount = ?, payment_status = 'pending' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sdi", $new_end_time, $new_amount, $booking_id);
        $update_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['message'] = "Booking extended successfully! Your booking has been extended by {$extend_hours} hours. Additional cost: $" . number_format($additional_cost, 2) . ". Please complete payment.";
        $_SESSION['message_type'] = "success";
        header("Location: my-bookings.php?status=active");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $_SESSION['message'] = "Error extending booking: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
} else {
    header("Location: my-bookings.php");
    exit();
}
?>
