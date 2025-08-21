<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Get booking and payment details
if (isset($_GET['booking_id'])) {
    $booking_id = $_GET['booking_id'];
    
    // Verify booking belongs to user
    $booking_sql = "SELECT b.*, p.spot_number, p.floor_number, p.type, p.hourly_rate 
                   FROM bookings b 
                   JOIN parking_spots p ON b.spot_id = p.id 
                   WHERE b.id = ? AND b.user_id = ?";
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("ii", $booking_id, $user_id);
    $booking_stmt->execute();
    $booking_result = $booking_stmt->get_result();
    
    if ($booking_result->num_rows === 0) {
        $_SESSION['message'] = "Invalid booking.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    $booking = $booking_result->fetch_assoc();
    
    // Get payment details
    $payment_sql = "SELECT * FROM payments WHERE booking_id = ? ORDER BY payment_date DESC LIMIT 1";
    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param("i", $booking_id);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();
    
    if ($payment_result->num_rows === 0) {
        $_SESSION['message'] = "No payment found for this booking.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    $payment = $payment_result->fetch_assoc();
} else {
    header("Location: my-bookings.php");
    exit();
}

// Get any messages from the session
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : '';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - Smart Parking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .receipt-container { max-width: 800px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); padding: 2rem; }
        .receipt-header { text-align: center; padding-bottom: 1.5rem; border-bottom: 1px dashed #e1e1e1; margin-bottom: 2rem; }
        .receipt-header h2 { color: #00416A; margin-bottom: 0.5rem; }
        .receipt-header p { color: #666; font-size: 0.9rem; }
        .receipt-details { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .detail-group h3 { font-size: 0.9rem; color: #666; margin-bottom: 0.5rem; }
        .detail-group p { font-size: 1.1rem; color: #2d3436; }
        .payment-summary { background: #f5f6fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; }
        .payment-row { display: flex; justify-content: space-between; margin-bottom: 1rem; }
        .payment-row:last-child { margin-bottom: 0; padding-top: 1rem; border-top: 1px solid #e1e1e1; font-weight: 600; }
        .payment-label { color: #666; }
        .payment-value { color: #2d3436; }
        .transaction-info { text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px dashed #e1e1e1; }
        .transaction-id { background: #e1e1e1; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem; color: #2d3436; display: inline-block; margin-top: 0.5rem; }
        .receipt-actions { display: flex; justify-content: center; gap: 1rem; margin-top: 2rem; }
        .btn-print { background: #00416A; color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease; }
        .btn-print:hover { background: #005688; }
        .btn-download { background: #4CAF50; color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease; }
        .btn-download:hover { background: #3d8b40; }
        @media print { .dashboard-container { display: block; } .sidebar, .header-content, .receipt-actions { display: none; } .main-content { margin: 0; padding: 0; } .receipt-container { box-shadow: none; padding: 0; } }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <header>
                <div class="header-content">
                    <h1>Payment Receipt</h1>
                    <div class="user-info">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random" alt="User Avatar">
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                </div>
            </header>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div class="receipt-container" id="receiptContent">
                <div class="receipt-header">
                    <h2>Smart Parking System</h2>
                    <p>Payment Receipt</p>
                    <p><?php echo date('F d, Y h:i A', strtotime($payment['payment_date'])); ?></p>
                </div>
                
                <div class="receipt-details">
                    <div class="detail-group">
                        <h3>Customer</h3>
                        <p><?php echo htmlspecialchars($user['name']); ?></p>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    
                    <div class="detail-group">
                        <h3>Booking Details</h3>
                        <p>Spot <?php echo htmlspecialchars($booking['spot_number']); ?> (Floor <?php echo $booking['floor_number']; ?>)</p>
                        <p>Vehicle: <?php echo htmlspecialchars($booking['vehicle_number']); ?></p>
                    </div>
                </div>
                
                <div class="payment-summary">
                    <div class="payment-row">
                        <span class="payment-label">Parking Duration</span>
                        <span class="payment-value">
                            <?php 
                                $start = new DateTime($booking['start_time']);
                                $end = new DateTime($booking['end_time']);
                                $interval = $start->diff($end);
                                $hours = $interval->h + ($interval->days * 24);
                                echo $hours . ' hours';
                            ?>
                        </span>
                    </div>
                    
                    <div class="payment-row">
                        <span class="payment-label">Hourly Rate</span>
                        <span class="payment-value">$<?php echo number_format($booking['hourly_rate'], 2); ?></span>
                    </div>
                    
                    <div class="payment-row">
                        <span class="payment-label">Payment Method</span>
                        <span class="payment-value"><?php echo ucfirst($payment['payment_method']); ?></span>
                    </div>
                    
                    <div class="payment-row">
                        <span class="payment-label">Total Amount</span>
                        <span class="payment-value">$<?php echo number_format($payment['amount'], 2); ?></span>
                    </div>
                </div>
                
                <div class="transaction-info">
                    <p>Transaction ID</p>
                    <div class="transaction-id"><?php echo $payment['transaction_id']; ?></div>
                </div>
                
                <div class="receipt-actions">
                    <button class="btn-print" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                    <button class="btn-download" onclick="generatePDF(<?php echo (int)$booking_id; ?>)">
                        <i class="fas fa-download"></i> Download PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    </script>
    <!-- Client-side PDF generation (no Composer needed) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    function generatePDF(bookingId) {
        const element = document.getElementById('receiptContent');
        const opt = {
            margin:       10,
            filename:     `receipt_booking_${bookingId}.pdf`,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
    </script>
</body>
</html>
