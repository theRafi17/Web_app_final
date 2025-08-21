<?php
session_start();
require_once 'config.php';
require_once 'calculate-amount.php';

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit();
}

$user_id = $_SESSION['user_id'];

// Allowed payment methods
$allowed_methods = ['bkash', 'nagad', 'card', 'cash', 'paypal', 'bank_transfer', 'refund'];

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id']) && isset($_POST['payment_method'])) {
	$booking_id = (int)$_POST['booking_id'];
	$payment_method = strtolower(trim($_POST['payment_method']));
	
	if (!in_array($payment_method, $allowed_methods)) {
		$_SESSION['message'] = "Invalid payment method.";
		$_SESSION['message_type'] = "error";
		header("Location: my-bookings.php");
		exit();
	}
	
	// Validate payment method specific fields
	$validation_error = '';
	
	if ($payment_method === 'bkash') {
		$bkash_number = trim($_POST['bkash_number'] ?? '');
		$bkash_transaction = trim($_POST['bkash_transaction'] ?? '');
		
		if (empty($bkash_number) || !preg_match('/^01[0-9]{9}$/', $bkash_number)) {
			$validation_error = "Please enter a valid bKash number (11 digits starting with 01).";
		}
		if (empty($bkash_transaction) || strlen($bkash_transaction) < 8) {
			$validation_error = "Please enter a valid bKash transaction ID.";
		}
	} elseif ($payment_method === 'nagad') {
		$nagad_number = trim($_POST['nagad_number'] ?? '');
		$nagad_transaction = trim($_POST['nagad_transaction'] ?? '');
		
		if (empty($nagad_number) || !preg_match('/^01[0-9]{9}$/', $nagad_number)) {
			$validation_error = "Please enter a valid Nagad number (11 digits starting with 01).";
		}
		if (empty($nagad_transaction) || strlen($nagad_transaction) < 8) {
			$validation_error = "Please enter a valid Nagad transaction ID.";
		}
	} elseif ($payment_method === 'card') {
		$card_number = trim($_POST['card_number'] ?? '');
		$card_expiry = trim($_POST['card_expiry'] ?? '');
		$card_cvv = trim($_POST['card_cvv'] ?? '');
		
		if (empty($card_number) || strlen(str_replace(' ', '', $card_number)) < 13) {
			$validation_error = "Please enter a valid card number.";
		}
		if (empty($card_expiry) || !preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $card_expiry)) {
			$validation_error = "Please enter a valid expiry date (MM/YY).";
		}
		if (empty($card_cvv) || !preg_match('/^[0-9]{3,4}$/', $card_cvv)) {
			$validation_error = "Please enter a valid CVV.";
		}
	}
	
	if (!empty($validation_error)) {
		$_SESSION['message'] = $validation_error;
		$_SESSION['message_type'] = "error";
		header("Location: my-bookings.php");
		exit();
	}
	
	// Verify that the booking belongs to the user
	$verify_sql = "SELECT * FROM bookings WHERE id = ? AND user_id = ?";
	$verify_stmt = $conn->prepare($verify_sql);
	$verify_stmt->bind_param("ii", $booking_id, $user_id);
	$verify_stmt->execute();
	$result = $verify_stmt->get_result();
	
	if ($result->num_rows === 0) {
		$_SESSION['message'] = "Invalid booking.";
		$_SESSION['message_type'] = "error";
		header("Location: my-bookings.php");
		exit();
	}
	
	// Get the booking with calculated amount
	$booking = calculateCurrentAmount($booking_id);
	
	// Start transaction
	$conn->begin_transaction();
	
	try {
		// Generate a transaction ID with method prefix
		$prefix = strtoupper($payment_method);
		$transaction_id = $prefix . '_' . time() . '_' . rand(1000, 9999);
		
		// Determine payment amount
		$payment_amount = $booking['current_amount'] ?? $booking['amount'];
		if ($payment_amount <= 0) {
			$payment_amount = 0.00;
		}
		
		// Insert payment record (mark as paid)
		$payment_sql = "INSERT INTO payments (booking_id, amount, payment_date, payment_method, transaction_id, payment_status) 
					VALUES (?, ?, NOW(), ?, ?, 'paid')";
		$payment_stmt = $conn->prepare($payment_sql);
		$payment_stmt->bind_param("idss", $booking_id, $payment_amount, $payment_method, $transaction_id);
		$payment_stmt->execute();
		
		// Update booking payment status and amount
		$update_sql = "UPDATE bookings SET payment_status = 'paid', amount = ? WHERE id = ?";
		$update_stmt = $conn->prepare($update_sql);
		$update_stmt->bind_param("di", $payment_amount, $booking_id);
		$update_stmt->execute();
		
		// Commit transaction
		$conn->commit();
		
		// Success message with payment method specific info
		$success_message = "Payment processed successfully! Transaction ID: " . $transaction_id;
		if ($payment_method === 'bkash') {
			$success_message .= "<br>bKash Number: " . htmlspecialchars($bkash_number);
			$success_message .= "<br>bKash Transaction ID: " . htmlspecialchars($bkash_transaction);
		} elseif ($payment_method === 'nagad') {
			$success_message .= "<br>Nagad Number: " . htmlspecialchars($nagad_number);
			$success_message .= "<br>Nagad Transaction ID: " . htmlspecialchars($nagad_transaction);
		}
		
		$_SESSION['message'] = $success_message;
		$_SESSION['message_type'] = "success";
		header("Location: view-receipt.php?booking_id=" . $booking_id);
		exit();
		
	} catch (Exception $e) {
		// Rollback transaction on error
		$conn->rollback();
		
		$_SESSION['message'] = "Payment failed: " . $e->getMessage();
		$_SESSION['message_type'] = "error";
		header("Location: my-bookings.php");
		exit();
	}
} else {
	header("Location: my-bookings.php");
	exit();
}
?>
