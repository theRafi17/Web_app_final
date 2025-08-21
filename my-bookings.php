<?php
session_start();
require_once 'config.php';

// Update expired bookings
updateExpiredBookings();

// Get current user ID
$user_id = $_SESSION['user_id'];

// Initialize filters
$status_filter = $_GET['status'] ?? 'all';

// Query to get bookings with spot number and payment details
$query = "SELECT b.*, p.spot_number, p.floor_number, p.type, p.hourly_rate, 
          pay.payment_method, pay.payment_status, pay.transaction_id
          FROM bookings b
          JOIN parking_spots p ON b.spot_id = p.id
          LEFT JOIN payments pay ON b.id = pay.booking_id
          WHERE b.user_id = ?";

if ($status_filter !== 'all') {
    $query .= " AND b.status = ?";
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
if ($status_filter !== 'all') {
    $stmt->bind_param('is', $user_id, $status_filter);
} else {
    $stmt->bind_param('i', $user_id);
}
$stmt->execute();
$result = $stmt->get_result();

// Get any messages from session
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : '';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bookings - Smart Parking</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .booking-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #00416A;
            transition: all 0.3s ease;
        }

        .booking-card:hover { transform: translateY(-2px); box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); }
        .booking-card.active { border-left-color: #4CAF50; }
        .booking-card.completed { border-left-color: #2196F3; }
        .booking-card.cancelled { border-left-color: #f44336; }
        .booking-card.pending { border-left-color: #FF9800; }

        .booking-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .booking-title { font-size: 1.2rem; font-weight: 600; color: #2d3436; }

        .status-badge { padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; text-transform: uppercase; }
        .status-active { background: #e8f5e8; color: #2e7d32; }
        .status-completed { background: #e3f2fd; color: #1565c0; }
        .status-cancelled { background: #ffebee; color: #c62828; }
        .status-pending {
            background: #e0f7ff; /* sky color */
            color: #000000;
        }

        .booking-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .detail-item { display: flex; flex-direction: column; }
        .detail-label { font-size: 0.8rem; color: #666; margin-bottom: 0.25rem; }
        .detail-value { font-weight: 500; color: #2d3436; }

        .booking-actions { display: flex; gap: 1rem; flex-wrap: wrap; }

        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease; text-decoration: none; font-size: 0.9rem; }
        .btn-primary { background: #00416A; color: white; }
        .btn-primary:hover { background: #005688; }
        .btn-danger { background: #f44336; color: white; }
        .btn-danger:hover { background: #d32f2f; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-success { background: #4CAF50; color: white; }
        .btn-success:hover { background: #3d8b40; }

        .filters { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        .filters a { padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; color: #666; background: #f8f9fa; transition: all 0.3s ease; font-weight: 500; }
        .filters a:hover, .filters a.active { background: #00416A; color: white; }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .alert-success { background: #e8f5e8; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .alert-info { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }

        .empty-state { text-align: center; padding: 3rem; color: #666; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; color: #ddd; }

        .payment-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; margin-top: 0.5rem; }
        .payment-option { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 8px; cursor: pointer; }
        .payment-option input { margin-right: 0.5rem; }

        .payment-fields { margin-top: 1rem; padding-left: 1.5rem; }
        .form-group { margin-bottom: 0.75rem; }
        .form-group label { font-size: 0.9rem; color: #555; margin-bottom: 0.25rem; }
        .form-group input { width: calc(100% - 1.5rem); padding: 0.75rem; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; }
        .form-group input[type="text"], .form-group input[type="number"] { padding-left: 0.75rem; }
        .form-group input[type="number"] { width: calc(100% - 1.5rem); padding-left: 0.75rem; }
        .form-group input[type="text"]:focus, .form-group input[type="number"]:focus { outline: none; border-color: #00416A; box-shadow: 0 0 5px rgba(0, 65, 106, 0.2); }
        .form-group input[type="text"]:invalid, .form-group input[type="number"]:invalid { border-color: #f44336; }
        .form-group input[type="text"]:valid, .form-group input[type="number"]:valid { border-color: #4CAF50; }

        @media (max-width: 768px) { .booking-details { grid-template-columns: 1fr; } .booking-actions { flex-direction: column; } .filters { justify-content: center; } }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <h1 class="page-title">My Bookings</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filters">
                <a href="?status=all" class="<?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All Bookings
                </a>
                <a href="?status=active" class="<?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                    <i class="fas fa-play-circle"></i> Active
                </a>
                <a href="?status=completed" class="<?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Completed
                </a>
                <a href="?status=cancelled" class="<?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Cancelled
                </a>
                <a href="?status=pending" class="<?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Pending
                </a>
            </div>

            <!-- Bookings List -->
            <div class="bookings-list">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($booking = $result->fetch_assoc()): ?>
                        <div class="booking-card <?php echo $booking['status']; ?>">
                            <div class="booking-header">
                                <h3 class="booking-title">Booking #<?php echo $booking['id']; ?></h3>
                                <span class="status-badge status-<?php echo $booking['status']; ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </div>

                            <div class="booking-details">
                                <div class="detail-item">
                                    <span class="detail-label">Parking Spot</span>
                                    <span class="detail-value"><?php echo $booking['spot_number']; ?> (Floor <?php echo $booking['floor_number']; ?>)</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Vehicle Number</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['vehicle_number']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Start Time</span>
                                    <span class="detail-value"><?php echo date('M d, Y g:i A', strtotime($booking['start_time'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">End Time</span>
                                    <span class="detail-value"><?php echo date('M d, Y g:i A', strtotime($booking['end_time'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Amount</span>
                                    <span class="detail-value">$<?php echo number_format($booking['amount'], 2); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Payment Status</span>
                                    <span class="detail-value"><?php echo ucfirst($booking['payment_status'] ?? 'pending'); ?></span>
                                </div>
                                <?php if ($booking['payment_method']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Payment Method</span>
                                    <span class="detail-value"><?php echo ucfirst($booking['payment_method']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="booking-actions">
                                <?php if ($booking['status'] === 'active'): ?>
                                    <button class="btn btn-danger" onclick="openCancelModal(<?php echo $booking['id']; ?>)">
                                        <i class="fas fa-times"></i> Cancel Booking
                                    </button>
                                    <button class="btn btn-primary" onclick="openExtendModal(<?php echo $booking['id']; ?>)">
                                        <i class="fas fa-clock"></i> Extend Time
                                    </button>
                                    <?php if ($booking['payment_status'] === 'pending'): ?>
                                        <button class="btn btn-success" onclick="openPaymentModal(<?php echo $booking['id']; ?>, '<?php echo number_format((float)$booking['amount'], 2); ?>')">
                                            <i class="fas fa-credit-card"></i> Pay Now
                                        </button>
                                    <?php endif; ?>
                                <?php elseif ($booking['status'] === 'completed'): ?>
                                    <a href="view-receipt.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-secondary">
                                        <i class="fas fa-receipt"></i> View Receipt
                                    </a>
                                    <?php if ($booking['payment_status'] === 'pending'): ?>
                                        <button class="btn btn-success" onclick="openPaymentModal(<?php echo $booking['id']; ?>, '<?php echo number_format((float)$booking['amount'], 2); ?>')">
                                            <i class="fas fa-credit-card"></i> Pay Now
                                        </button>
                                    <?php endif; ?>
                                <?php elseif ($booking['status'] === 'pending'): ?>
                                    <button class="btn btn-danger" onclick="openCancelModal(<?php echo $booking['id']; ?>)">
                                        <i class="fas fa-times"></i> Cancel Booking
                                    </button>
                                    <?php if ($booking['payment_status'] === 'paid'): ?>
                                        <?php if (strtotime($booking['start_time']) <= time()): ?>
                                            <button class="btn btn-primary" onclick="openActivateModal(<?php echo $booking['id']; ?>)">
                                                <i class="fas fa-play"></i> Activate Booking
                                            </button>
                                        <?php else: ?>
                                            <span class="detail-label">Starts at <?php echo date('M d, Y g:i A', strtotime($booking['start_time'])); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-success" onclick="openPaymentModal(<?php echo $booking['id']; ?>, '<?php echo number_format((float)$booking['amount'], 2); ?>')">
                                            <i class="fas fa-credit-card"></i> Pay Now
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No bookings found</h3>
                        <p>You don't have any <?php echo $status_filter !== 'all' ? $status_filter : ''; ?> bookings yet.</p>
                        <?php if ($status_filter === 'all' || $status_filter === 'active'): ?>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Book a Parking Spot
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cancel Booking Modal -->
            <div id="cancelModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeCancelModal()">&times;</span>
                    <h2><i class="fas fa-exclamation-triangle"></i> Cancel Booking</h2>
                    <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
                    <form id="cancelForm" action="cancel-booking.php" method="POST">
                        <input type="hidden" name="booking_id" id="cancelBookingId">
                        <div class="modal-buttons">
                            <button type="button" class="btn btn-secondary" onclick="closeCancelModal()">
                                <i class="fas fa-times"></i> No, Keep Booking
                            </button>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-check"></i> Yes, Cancel Booking
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Extend Booking Modal -->
            <div id="extendModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeExtendModal()">&times;</span>
                    <h2><i class="fas fa-clock"></i> Extend Booking</h2>
                    <p>How many additional hours would you like to extend your booking?</p>
                    <form id="extendForm" action="extend-booking.php" method="POST">
                        <input type="hidden" name="booking_id" id="extendBookingId">
                        <div class="form-group">
                            <label for="extendDuration">Additional Hours:</label>
                            <input type="number" name="extend_hours" id="extendDuration" min="1" max="24" value="1" required>
                        </div>
                        <div class="modal-buttons">
                            <button type="button" class="btn btn-secondary" onclick="closeExtendModal()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Confirm Extension
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Activate Booking Modal -->
            <div id="activateModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeActivateModal()">&times;</span>
                    <h2><i class="fas fa-play"></i> Activate Booking</h2>
                    <p>Are you sure you want to activate this booking? Once activated, your parking spot will be reserved and the booking will become active.</p>
                    <form id="activateForm" action="activate-booking.php" method="POST">
                        <input type="hidden" name="booking_id" id="activateBookingId">
                        <div class="modal-buttons">
                            <button type="button" class="btn btn-secondary" onclick="closeActivateModal()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Activate Booking
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payment Modal -->
            <div id="paymentModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closePaymentModal()">&times;</span>
                    <h2><i class="fas fa-credit-card"></i> Choose Payment Method</h2>
                    <p>Total Amount: $<span id="paymentAmount">0.00</span></p>
                    <form id="paymentForm" action="process-payment.php" method="POST">
                        <input type="hidden" name="booking_id" id="paymentBookingId">
                        <div class="form-group">
                            <label>Select Method:</label>
                            <div class="payment-options">
                                <label class="payment-option"><input type="radio" name="payment_method" value="bkash" required onchange="togglePaymentFields()"> bKash</label>
                                <label class="payment-option"><input type="radio" name="payment_method" value="nagad" required onchange="togglePaymentFields()"> Nagad</label>
                                <label class="payment-option"><input type="radio" name="payment_method" value="card" required onchange="togglePaymentFields()"> Card</label>
                            </div>
                        </div>
                        
                        <!-- bKash Fields -->
                        <div id="bkashFields" class="payment-fields" style="display: none;">
                            <div class="form-group">
                                <label for="bkashNumber">bKash Number:</label>
                                <input type="text" name="bkash_number" id="bkashNumber" placeholder="01XXXXXXXXX" pattern="01[0-9]{9}" maxlength="11">
                            </div>
                            <div class="form-group">
                                <label for="bkashTransaction">bKash Transaction ID:</label>
                                <input type="text" name="bkash_transaction" id="bkashTransaction" placeholder="e.g., 8N7A6B5C4D3E" maxlength="12">
                            </div>
                        </div>
                        
                        <!-- Nagad Fields -->
                        <div id="nagadFields" class="payment-fields" style="display: none;">
                            <div class="form-group">
                                <label for="nagadNumber">Nagad Number:</label>
                                <input type="text" name="nagad_number" id="nagadNumber" placeholder="01XXXXXXXXX" pattern="01[0-9]{9}" maxlength="11">
                            </div>
                            <div class="form-group">
                                <label for="nagadTransaction">Nagad Transaction ID:</label>
                                <input type="text" name="nagad_transaction" id="nagadTransaction" placeholder="e.g., NGD123456789" maxlength="15">
                            </div>
                        </div>
                        
                        <!-- Card Fields -->
                        <div id="cardFields" class="payment-fields" style="display: none;">
                            <div class="form-group">
                                <label for="cardNumber">Card Number:</label>
                                <input type="text" name="card_number" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19">
                            </div>
                            <div class="form-group">
                                <label for="cardExpiry">Expiry Date:</label>
                                <input type="text" name="card_expiry" id="cardExpiry" placeholder="MM/YY" maxlength="5">
                            </div>
                            <div class="form-group">
                                <label for="cardCvv">CVV:</label>
                                <input type="text" name="card_cvv" id="cardCvv" placeholder="123" maxlength="4" pattern="[0-9]{3,4}">
                            </div>
                        </div>
                        
                        <div class="modal-buttons">
                            <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-lock"></i> Pay Securely
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openCancelModal(bookingId) {
            document.getElementById('cancelBookingId').value = bookingId;
            const modal = document.getElementById('cancelModal');
            modal.style.display = 'flex';
            setTimeout(() => { modal.style.opacity = '1'; modal.querySelector('.modal-content').style.transform = 'translateY(0)'; }, 10);
        }
        function closeCancelModal() { const modal = document.getElementById('cancelModal'); modal.querySelector('.modal-content').style.transform = 'translateY(20px)'; modal.style.opacity = '0'; setTimeout(() => { modal.style.display = 'none'; }, 300); }

        function openExtendModal(bookingId) {
            document.getElementById('extendBookingId').value = bookingId;
            const modal = document.getElementById('extendModal');
            modal.style.display = 'flex';
            setTimeout(() => { modal.style.opacity = '1'; modal.querySelector('.modal-content').style.transform = 'translateY(0)'; }, 10);
        }
        function closeExtendModal() { const modal = document.getElementById('extendModal'); modal.querySelector('.modal-content').style.transform = 'translateY(20px)'; modal.style.opacity = '0'; setTimeout(() => { modal.style.display = 'none'; }, 300); }

        function openActivateModal(bookingId) {
            document.getElementById('activateBookingId').value = bookingId;
            const modal = document.getElementById('activateModal');
            modal.style.display = 'flex';
            setTimeout(() => { modal.style.opacity = '1'; modal.querySelector('.modal-content').style.transform = 'translateY(0)'; }, 10);
        }
        function closeActivateModal() { const modal = document.getElementById('activateModal'); modal.querySelector('.modal-content').style.transform = 'translateY(20px)'; modal.style.opacity = '0'; setTimeout(() => { modal.style.display = 'none'; }, 300); }

        function openPaymentModal(bookingId, amount) {
            document.getElementById('paymentBookingId').value = bookingId;
            document.getElementById('paymentAmount').innerText = amount;
            const modal = document.getElementById('paymentModal');
            modal.style.display = 'flex';
            setTimeout(() => { modal.style.opacity = '1'; modal.querySelector('.modal-content').style.transform = 'translateY(0)'; }, 10);
        }
        function closePaymentModal() { const modal = document.getElementById('paymentModal'); modal.querySelector('.modal-content').style.transform = 'translateY(20px)'; modal.style.opacity = '0'; setTimeout(() => { modal.style.display = 'none'; }, 300); }

        function togglePaymentFields() {
            const bKashFields = document.getElementById('bkashFields');
            const nagadFields = document.getElementById('nagadFields');
            const cardFields = document.getElementById('cardFields');
            const selected = document.querySelector('input[name="payment_method"]:checked').value;
            if (selected === 'bkash') { bKashFields.style.display = 'block'; nagadFields.style.display = 'none'; cardFields.style.display = 'none'; }
            else if (selected === 'nagad') { bKashFields.style.display = 'none'; nagadFields.style.display = 'block'; cardFields.style.display = 'none'; }
            else { bKashFields.style.display = 'none'; nagadFields.style.display = 'none'; cardFields.style.display = 'block'; }
        }

        // Close modals when clicking outside the modal content
        window.addEventListener('click', function(event) {
            const cancelModal = document.getElementById('cancelModal');
            const extendModal = document.getElementById('extendModal');
            const activateModal = document.getElementById('activateModal');
            const paymentModal = document.getElementById('paymentModal');
            if (event.target === cancelModal) closeCancelModal();
            if (event.target === extendModal) closeExtendModal();
            if (event.target === activateModal) closeActivateModal();
            if (event.target === paymentModal) closePaymentModal();
        });

        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => { setTimeout(() => { alert.style.opacity = '0'; setTimeout(() => alert.remove(), 300); }, 5000); });
    </script>
</body>
</html>