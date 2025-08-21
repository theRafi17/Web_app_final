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

// Process booking cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the booking ID from the form submission
    $booking_id = $_POST['booking_id'];

    // Verify that the booking belongs to the user
    $verify_sql = "SELECT b.*, p.spot_number, p.floor_number, p.hourly_rate
                  FROM bookings b 
                  JOIN parking_spots p ON b.spot_id = p.id 
                  WHERE b.id = ? AND b.user_id = ? AND b.status IN ('pending', 'active')";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $booking_id, $user_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "Invalid booking or booking cannot be cancelled.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    $booking = $result->fetch_assoc();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $refund_amount = 0;
        $cancellation_reason = '';
        
        if ($booking['status'] === 'pending') {
            // For pending bookings, full refund if payment was made
            if ($booking['payment_status'] === 'paid') {
                $refund_amount = $booking['amount'];
                $cancellation_reason = 'Booking cancelled before activation - full refund';
            }
            
            // Update booking status to cancelled
            $update_sql = "UPDATE bookings SET status = 'cancelled', payment_status = 'refunded' WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $booking_id);
            $update_stmt->execute();
            
        } elseif ($booking['status'] === 'active') {
            // For active bookings, calculate partial refund based on time used
            $current_booking = calculateCurrentAmount($booking_id);
            $current_amount = $current_booking['current_amount'] ?? $booking['amount'];
            
            if ($booking['payment_status'] === 'paid') {
                $refund_amount = $booking['amount'] - $current_amount;
                if ($refund_amount < 0) $refund_amount = 0;
                $cancellation_reason = 'Booking cancelled during active period - partial refund';
            }
            
            // Update booking with current amount and status
            $update_sql = "UPDATE bookings SET status = 'cancelled', amount = ?, payment_status = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $new_payment_status = ($booking['payment_status'] === 'paid') ? 'refunded' : 'pending';
            $update_stmt->bind_param("dsi", $current_amount, $new_payment_status, $booking_id);
            $update_stmt->execute();
            
            // Free up the parking spot
            $update_spot_sql = "UPDATE parking_spots SET is_occupied = 0, vehicle_number = NULL WHERE id = ?";
            $update_spot_stmt = $conn->prepare($update_spot_sql);
            $update_spot_stmt->bind_param("i", $booking['spot_id']);
            $update_spot_stmt->execute();
        }
        
        // If there's a refund, create a refund record
        if ($refund_amount > 0) {
            $refund_sql = "INSERT INTO payments (booking_id, amount, payment_date, payment_method, transaction_id, payment_status) 
                          VALUES (?, ?, NOW(), 'refund', ?, 'refunded')";
            $refund_stmt = $conn->prepare($refund_sql);
            $refund_transaction_id = 'REFUND_' . time() . '_' . $booking_id;
            $refund_stmt->bind_param("ids", $booking_id, $refund_amount, $refund_transaction_id);
            $refund_stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        if ($refund_amount > 0) {
            $_SESSION['message'] = "Booking cancelled successfully. Refund of $" . number_format($refund_amount, 2) . " will be processed.";
        } else {
            $_SESSION['message'] = "Booking cancelled successfully.";
        }
        $_SESSION['message_type'] = "success";
        
        header("Location: my-bookings.php?status=cancelled");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $_SESSION['message'] = "Error cancelling booking: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
} else {
    header("Location: my-bookings.php");
    exit();
}
?>
