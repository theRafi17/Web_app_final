<?php

require_once 'config.php';

function calculate_refund_amount($booking_id) {
    global $conn;

    try {
        // Get booking details
        $stmt = $conn->prepare("SELECT amount, start_time, end_time FROM bookings WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if (!$booking) {
            throw new Exception("Booking not found");
        }

        $current_time = new DateTime('now', new DateTimeZone('Asia/Dhaka'));
        $end_time = new DateTime($booking['end_time'], new DateTimeZone('Asia/Dhaka'));

        // If current time is after end time, no refund
        if ($current_time >= $end_time) {
            return 0;
        }

        // Calculate remaining time
        $time_remaining = $end_time->getTimestamp() - $current_time->getTimestamp();
        $hours_remaining = ceil($time_remaining / 3600);

        // Calculate refund amount (full amount if more than 1 hour remains, 50% otherwise)
        if ($hours_remaining > 1) {
            return $booking['amount'];
        } else {
            return $booking['amount'] * 0.5;
        }

    } catch (Exception $e) {
        error_log("Error calculating refund amount: " . $e->getMessage());
        return false;
    }
}

?>
