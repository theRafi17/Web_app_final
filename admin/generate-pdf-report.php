<?php
session_start();
require_once '../config.php';
require_once '../vendor/autoload.php'; // Make sure to install TCPDF via composer

use TCPDF;

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Get report parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'revenue';

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Smart Parking System');
$pdf->SetTitle('Revenue Report - ' . $date_from . ' to ' . $date_to);

// Set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, 'Smart Parking System', 'Revenue Report');

// Set header and footer fonts
$pdf->setHeaderFont(Array('helvetica', '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array('helvetica', '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Get revenue statistics
$revenue_sql = "SELECT
                SUM(p.amount) as total_revenue,
                COUNT(p.id) as total_transactions,
                AVG(p.amount) as average_transaction,
                SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount ELSE 0 END) as cash_revenue,
                SUM(CASE WHEN p.payment_method = 'card' THEN p.amount ELSE 0 END) as card_revenue,
                SUM(CASE WHEN p.payment_method = 'paypal' THEN p.amount ELSE 0 END) as paypal_revenue,
                SUM(CASE WHEN p.payment_method = 'bank_transfer' THEN p.amount ELSE 0 END) as bank_transfer_revenue
                FROM payments p
                WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                AND p.payment_status = 'paid'";

$revenue_stmt = $conn->prepare($revenue_sql);
$revenue_stmt->bind_param("ss", $date_from, $date_to);
$revenue_stmt->execute();
$revenue_stats = $revenue_stmt->get_result()->fetch_assoc();

// Add report title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Revenue Report', 0, 1, 'C');
$pdf->Ln(5);

// Add date range
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Period: ' . date('F d, Y', strtotime($date_from)) . ' to ' . date('F d, Y', strtotime($date_to)), 0, 1, 'C');
$pdf->Ln(5);

// Add summary statistics
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Summary Statistics', 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(60, 10, 'Total Revenue:', 0, 0);
$pdf->Cell(0, 10, '$' . number_format($revenue_stats['total_revenue'], 2), 0, 1);

$pdf->Cell(60, 10, 'Total Transactions:', 0, 0);
$pdf->Cell(0, 10, number_format($revenue_stats['total_transactions']), 0, 1);

$pdf->Cell(60, 10, 'Average Transaction:', 0, 0);
$pdf->Cell(0, 10, '$' . number_format($revenue_stats['average_transaction'], 2), 0, 1);

$pdf->Ln(5);

// Add payment method breakdown
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Payment Method Breakdown', 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(60, 10, 'Cash Payments:', 0, 0);
$pdf->Cell(0, 10, '$' . number_format($revenue_stats['cash_revenue'], 2), 0, 1);

$pdf->Cell(60, 10, 'Card Payments:', 0, 0);
$pdf->Cell(0, 10, '$' . number_format($revenue_stats['card_revenue'], 2), 0, 1);

$pdf->Cell(60, 10, 'PayPal Payments:', 0, 0);
$pdf->Cell(0, 10, '$' . number_format($revenue_stats['paypal_revenue'], 2), 0, 1);

$pdf->Cell(60, 10, 'Bank Transfers:', 0, 0);
$pdf->Cell(0, 10, '$' . number_format($revenue_stats['bank_transfer_revenue'], 2), 0, 1);

$pdf->Ln(5);

// Add daily revenue breakdown
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Daily Revenue Breakdown', 0, 1, 'L');
$pdf->Ln(2);

$daily_revenue_sql = "SELECT
                      DATE(p.payment_date) as day,
                      SUM(p.amount) as daily_revenue
                      FROM payments p
                      WHERE p.payment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                      AND p.payment_status = 'paid'
                      GROUP BY day
                      ORDER BY day ASC";

$daily_revenue_stmt = $conn->prepare($daily_revenue_sql);
$daily_revenue_stmt->bind_param("ss", $date_from, $date_to);
$daily_revenue_stmt->execute();
$daily_revenue_result = $daily_revenue_stmt->get_result();

// Create table header
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(80, 10, 'Date', 1, 0, 'C');
$pdf->Cell(80, 10, 'Revenue', 1, 1, 'C');

// Add table data
$pdf->SetFont('helvetica', '', 12);
while ($row = $daily_revenue_result->fetch_assoc()) {
    $pdf->Cell(80, 10, date('F d, Y', strtotime($row['day'])), 1, 0, 'C');
    $pdf->Cell(80, 10, '$' . number_format($row['daily_revenue'], 2), 1, 1, 'C');
}

// Output the PDF
$pdf->Output('revenue_report_' . $date_from . '_to_' . $date_to . '.pdf', 'D'); 