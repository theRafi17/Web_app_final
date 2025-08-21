<?php
session_start();
require_once 'config.php';

// Composer autoload (TCPDF). Provide friendly error if missing
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    header('Content-Type: text/plain');
    http_response_code(500);
    echo "Missing Composer autoload. Please run 'composer install' in the project root (smart_parking-master).\n";
    echo "1) Open a terminal in C:\\xampp\\htdocs\\smart_parking-master\n";
    echo "2) Run: composer install\n";
    echo "3) Reload this page.";
    exit;
}
require_once $autoloadPath;

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Validate booking_id
if (!isset($_GET['booking_id'])) {
    $_SESSION['message'] = 'Missing booking ID.';
    $_SESSION['message_type'] = 'error';
    header('Location: my-bookings.php');
    exit();
}

$booking_id = (int)$_GET['booking_id'];

// Verify booking belongs to user and fetch details
$booking_sql = "SELECT b.*, p.spot_number, p.floor_number, p.type, p.hourly_rate 
                FROM bookings b 
                JOIN parking_spots p ON b.spot_id = p.id 
                WHERE b.id = ? AND b.user_id = ?";
$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param('ii', $booking_id, $user_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

if ($booking_result->num_rows === 0) {
    $_SESSION['message'] = 'Invalid booking.';
    $_SESSION['message_type'] = 'error';
    header('Location: my-bookings.php');
    exit();
}

$booking = $booking_result->fetch_assoc();

// Fetch latest payment for booking
$payment_sql = "SELECT * FROM payments WHERE booking_id = ? ORDER BY payment_date DESC LIMIT 1";
$payment_stmt = $conn->prepare($payment_sql);
$payment_stmt->bind_param('i', $booking_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();

if ($payment_result->num_rows === 0) {
    $_SESSION['message'] = 'No payment found for this booking.';
    $_SESSION['message_type'] = 'error';
    header('Location: my-bookings.php');
    exit();
}

$payment = $payment_result->fetch_assoc();

// Fetch user details
$user_sql = 'SELECT * FROM users WHERE id = ?';
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Calculate duration (in hours)
$start = new DateTime($booking['start_time']);
$end = new DateTime($booking['end_time']);
$interval = $start->diff($end);
$hours = $interval->h + ($interval->days * 24);

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Smart Parking System');
$pdf->SetTitle('Payment Receipt - Booking #' . $booking_id);

// Header/Footer settings
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, 'Smart Parking System', 'Payment Receipt');
$pdf->setHeaderFont(array('helvetica', '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(array('helvetica', '', PDF_FONT_SIZE_DATA));
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Build receipt HTML
$html = '<style>
    .title { font-size: 20px; font-weight: bold; text-align: center; margin-bottom: 6px; }
    .subtitle { text-align: center; color: #555; margin-bottom: 12px; }
    .section-title { font-size: 14px; font-weight: bold; margin: 12px 0 6px; }
    .row { display: block; margin: 4px 0; }
    .label { color: #555; width: 180px; display: inline-block; }
    .value { color: #111; }
    .hr { border-bottom: 1px dashed #999; margin: 10px 0; }
    .txid { background: #eee; padding: 4px 8px; border-radius: 10px; display: inline-block; }
</style>';

$html .= '<div class="title">Payment Receipt</div>';
$html .= '<div class="subtitle">' . date('F d, Y h:i A', strtotime($payment['payment_date'])) . '</div>';

// Customer and Booking details
$html .= '<div class="section-title">Customer</div>';
$html .= '<div class="row"><span class="label">Name</span><span class="value">' . htmlspecialchars($user['name']) . '</span></div>';
$html .= '<div class="row"><span class="label">Email</span><span class="value">' . htmlspecialchars($user['email']) . '</span></div>';

$html .= '<div class="section-title">Booking Details</div>';
$html .= '<div class="row"><span class="label">Booking ID</span><span class="value">#' . $booking_id . '</span></div>';
$html .= '<div class="row"><span class="label">Spot</span><span class="value">' . htmlspecialchars($booking['spot_number']) . ' (Floor ' . (int)$booking['floor_number'] . ')</span></div>';
$html .= '<div class="row"><span class="label">Vehicle</span><span class="value">' . htmlspecialchars($booking['vehicle_number']) . '</span></div>';
$html .= '<div class="row"><span class="label">Start Time</span><span class="value">' . date('M d, Y g:i A', strtotime($booking['start_time'])) . '</span></div>';
$html .= '<div class="row"><span class="label">End Time</span><span class="value">' . date('M d, Y g:i A', strtotime($booking['end_time'])) . '</span></div>';
$html .= '<div class="row"><span class="label">Duration</span><span class="value">' . $hours . ' hours</span></div>';

$html .= '<div class="section-title">Payment Summary</div>';
$html .= '<div class="row"><span class="label">Hourly Rate</span><span class="value">$' . number_format((float)$booking['hourly_rate'], 2) . '</span></div>';
$html .= '<div class="row"><span class="label">Payment Method</span><span class="value">' . ucfirst($payment['payment_method']) . '</span></div>';
$html .= '<div class="row"><span class="label">Total Amount</span><span class="value">$' . number_format((float)$payment['amount'], 2) . '</span></div>';

$html .= '<div class="hr"></div>';
$html .= '<div class="row"><span class="label">Transaction ID</span><span class="value txid">' . htmlspecialchars($payment['transaction_id']) . '</span></div>';

// Output HTML
$pdf->writeHTML($html, true, false, true, false, '');

// Output the PDF for download
$filename = 'receipt_booking_' . $booking_id . '_' . date('YmdHis') . '.pdf';
$pdf->Output($filename, 'D');
