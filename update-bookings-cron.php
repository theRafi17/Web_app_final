<?php
/**
 * Cron job script to update booking statuses automatically
 * This script should be run every minute or as frequently as needed
 * 
 * Usage: php update-bookings-cron.php
 * 
 * You can set up a cron job to run this script:
 * * * * * * /usr/bin/php /path/to/your/project/update-bookings-cron.php
 */

// Include configuration
require_once 'config.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'booking-updates.log');

// Log function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    error_log("[$timestamp] $message");
}

try {
    logMessage("Starting booking status update process...");
    
    // Update expired and activate pending bookings
    $result = updateExpiredBookings();
    
    if ($result === false) {
        logMessage("ERROR: Failed to update bookings");
        exit(1);
    }
    
    $activated = $result['activated'];
    $completed = $result['completed'];
    
    logMessage("Booking update completed successfully:");
    logMessage("- Activated bookings: $activated");
    logMessage("- Completed bookings: $completed");
    
    // Additional check for bookings that need manual attention
    $current_time = date('Y-m-d H:i:s');
    
    // Find pending bookings that are past their start time but not paid
    $overdue_sql = "SELECT b.id, b.start_time, b.vehicle_number, u.email 
                    FROM bookings b 
                    JOIN users u ON b.user_id = u.id 
                    WHERE b.status = 'pending' 
                    AND b.start_time < ? 
                    AND b.payment_status = 'pending'";
    $overdue_stmt = $conn->prepare($overdue_sql);
    $overdue_stmt->bind_param("s", $current_time);
    $overdue_stmt->execute();
    $overdue_result = $overdue_stmt->get_result();
    
    if ($overdue_result->num_rows > 0) {
        logMessage("Found " . $overdue_result->num_rows . " overdue unpaid bookings:");
        while ($booking = $overdue_result->fetch_assoc()) {
            logMessage("- Booking #{$booking['id']} (Vehicle: {$booking['vehicle_number']}, User: {$booking['email']})");
        }
    }
    
    // Find active bookings that are close to expiring (within 30 minutes)
    $expiring_soon_sql = "SELECT b.id, b.end_time, b.vehicle_number, u.email 
                          FROM bookings b 
                          JOIN users u ON b.user_id = u.id 
                          WHERE b.status = 'active' 
                          AND b.end_time BETWEEN ? AND DATE_ADD(?, INTERVAL 30 MINUTE)";
    $expiring_soon_stmt = $conn->prepare($expiring_soon_sql);
    $expiring_soon_stmt->bind_param("ss", $current_time, $current_time);
    $expiring_soon_stmt->execute();
    $expiring_soon_result = $expiring_soon_stmt->get_result();
    
    if ($expiring_soon_result->num_rows > 0) {
        logMessage("Found " . $expiring_soon_result->num_rows . " bookings expiring soon:");
        while ($booking = $expiring_soon_result->fetch_assoc()) {
            $time_remaining = strtotime($booking['end_time']) - strtotime($current_time);
            $minutes_remaining = round($time_remaining / 60);
            logMessage("- Booking #{$booking['id']} expires in {$minutes_remaining} minutes (Vehicle: {$booking['vehicle_number']})");
        }
    }
    
    logMessage("Booking status update process completed successfully.");
    exit(0);
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    exit(1);
}
?>
