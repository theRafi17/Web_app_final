<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "smart_parking";

// Set application timezone (adjust if needed)
if (!defined('APP_TIMEZONE')) {
	define('APP_TIMEZONE', 'Asia/Dhaka');
}
@date_default_timezone_set(APP_TIMEZONE);

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
	die("Connection failed: " . $conn->connect_error);
}

// Function to calculate booking amount
function calculate_booking_amount($start_time, $end_time, $hourly_rate) {
	$start = strtotime($start_time);
	$end = strtotime($end_time);
	$duration = $end - $start;
	$hours = ceil($duration / 3600); // Convert seconds to hours and round up
	return round($hours * $hourly_rate, 2);
}

// Function to calculate current amount for active bookings
function calculateCurrentAmount($booking_id) {
	global $conn;

	// Get booking details
	$sql = "SELECT b.*, p.hourly_rate
			FROM bookings b
			JOIN parking_spots p ON b.spot_id = p.id
			WHERE b.id = ?";

	$stmt = $conn->prepare($sql);
	$stmt->bind_param("i", $booking_id);
	$stmt->execute();
	$result = $stmt->get_result();
	$booking = $result->fetch_assoc();

	if (!$booking) {
		return null;
	}

	// Calculate current amount based on actual duration for active bookings
	if ($booking['status'] === 'active') {
		$current_time = date('Y-m-d H:i:s');
		$current_amount = calculate_booking_amount(
			$booking['start_time'],
			$current_time,
			$booking['hourly_rate']
		);
		$booking['current_amount'] = $current_amount;
	}

	return $booking;
}

// Function to update expired bookings
function updateExpiredBookings() {
	global $conn;

	// Get current time
	$current_time = date('Y-m-d H:i:s');

	// Start transaction
	$conn->begin_transaction();

	try {
		// Activate bookings that have reached their start time
		$activate_sql = "UPDATE bookings b 
					JOIN parking_spots p ON b.spot_id = p.id
					SET b.status = 'active', p.is_occupied = 1, p.vehicle_number = b.vehicle_number
					WHERE b.status = 'pending' 
					AND b.start_time <= ? 
					AND b.payment_status = 'paid'";
		$activate_stmt = $conn->prepare($activate_sql);
		$activate_stmt->bind_param("s", $current_time);
		$activate_stmt->execute();
		$activated_count = $activate_stmt->affected_rows;

		// Find all active bookings that have passed their end time
		$find_expired_sql = "SELECT b.id, b.spot_id
							FROM bookings b
							WHERE b.status = 'active'
							AND b.end_time < ?";
		$find_stmt = $conn->prepare($find_expired_sql);
		$find_stmt->bind_param("s", $current_time);
		$find_stmt->execute();
		$expired_result = $find_stmt->get_result();

		// Update each expired booking
		while ($booking = $expired_result->fetch_assoc()) {
			// Update booking status to completed
			$update_booking_sql = "UPDATE bookings SET status = 'completed' WHERE id = ?";
			$update_booking_stmt = $conn->prepare($update_booking_sql);
			$update_booking_stmt->bind_param("i", $booking['id']);
			$update_booking_stmt->execute();

			// Free up the parking spot
			$update_spot_sql = "UPDATE parking_spots SET is_occupied = 0, vehicle_number = NULL WHERE id = ?";
			$update_spot_stmt = $conn->prepare($update_spot_sql);
			$update_spot_stmt->bind_param("i", $booking['spot_id']);
			$update_spot_stmt->execute();
		}

		// Commit transaction
		$conn->commit();

		return [
			'activated' => $activated_count,
			'completed' => $expired_result->num_rows
		];
	} catch (Exception $e) {
		// Rollback transaction on error
		$conn->rollback();
		error_log("Error updating bookings: " . $e->getMessage());
		return false;
	}
}

// Function to activate a specific booking
function activateBooking($booking_id) {
	global $conn;

	// Get booking details
	$sql = "SELECT b.*, p.spot_number 
			FROM bookings b 
			JOIN parking_spots p ON b.spot_id = p.id 
			WHERE b.id = ? AND b.status = 'pending'";
	$stmt = $conn->prepare($sql);
	$stmt->bind_param("i", $booking_id);
	$stmt->execute();
	$result = $stmt->get_result();
	
	if ($result->num_rows === 0) {
		return false;
	}
	
	$booking = $result->fetch_assoc();
	
	// Check if payment is completed
	if ($booking['payment_status'] !== 'paid') {
		return false;
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
		
		return true;
	} catch (Exception $e) {
		// Rollback transaction on error
		$conn->rollback();
		error_log("Error activating booking: " . $e->getMessage());
		return false;
	}
}

// Function to get booking status with time-based logic
function getBookingStatus($booking) {
	$current_time = new DateTime();
	$start_time = new DateTime($booking['start_time']);
	$end_time = new DateTime($booking['end_time']);
	
	// If payment is pending, status is pending regardless of time
	if ($booking['payment_status'] === 'pending') {
		return 'pending';
	}
	
	// If booking is already marked as cancelled, return cancelled
	if ($booking['status'] === 'cancelled') {
		return 'cancelled';
	}
	
	// If current time is before start time, status is pending
	if ($current_time < $start_time) {
		return 'pending';
	}
	
	// If current time is between start and end time, status is active
	if ($current_time >= $start_time && $current_time < $end_time) {
		return 'active';
	}
	
	// If current time is after end time, status is completed
	if ($current_time >= $end_time) {
		return 'completed';
	}
	
	return $booking['status'];
}
?>
