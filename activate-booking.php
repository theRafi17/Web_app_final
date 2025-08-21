<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Process booking activation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];
    
    // Verify that the booking belongs to the user and is pending
    $verify_sql = "SELECT b.*, p.spot_number, p.floor_number
                   FROM bookings b 
                   JOIN parking_spots p ON b.spot_id = p.id 
                   WHERE b.id = ? AND b.user_id = ? AND b.status = 'pending'";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $booking_id, $user_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "Invalid booking or booking cannot be activated.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    $booking = $result->fetch_assoc();
    
    // Check if payment is completed
    if ($booking['payment_status'] !== 'paid') {
        $_SESSION['message'] = "Payment must be completed before activating the booking.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    // Check if start time has been reached
    $current_time = date('Y-m-d H:i:s');
    if (strtotime($current_time) < strtotime($booking['start_time'])) {
        $_SESSION['message'] = "Booking cannot be activated before the scheduled start time.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking status to active
        $update_booking_sql = "UPDATE bookings SET status = 'active' WHERE id = ?";
        $update_booking_stmt = $conn->prepare($update_booking_sql);
        $update_booking_stmt->bind_param("i", $booking_id);
        $update_booking_stmt->execute();
        
        // Update parking spot as occupied
        $update_spot_sql = "UPDATE parking_spots SET is_occupied = 1, vehicle_number = ? WHERE id = ?";
        $update_spot_stmt = $conn->prepare($update_spot_sql);
        $update_spot_stmt->bind_param("si", $booking['vehicle_number'], $booking['spot_id']);
        $update_spot_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['message'] = "Booking activated successfully! Your parking spot is now active.";
        $_SESSION['message_type'] = "success";
        header("Location: my-bookings.php?status=active");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $_SESSION['message'] = "Error activating booking: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
} else {
    header("Location: my-bookings.php");
    exit();
}
?>
