<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Update expired bookings
updateExpiredBookings();

// Get user information
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Get active bookings for the user
$bookings_sql = "SELECT b.*, p.spot_number, p.floor_number, p.type
                 FROM bookings b
                 JOIN parking_spots p ON b.spot_id = p.id
                 WHERE b.user_id = ? AND b.status = 'active'
                 ORDER BY b.start_time DESC";
$bookings_stmt = $conn->prepare($bookings_sql);
$bookings_stmt->bind_param("i", $user_id);
$bookings_stmt->execute();
$active_bookings = $bookings_stmt->get_result();

// Get all parking spots (we'll check availability based on time range when user selects it)
$spots_sql = "SELECT * FROM parking_spots ORDER BY floor_number, spot_number";
$available_spots = $conn->query($spots_sql);

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_spot'])) {
    date_default_timezone_set('Asia/Dhaka'); // Set to match client timezone

    // Get spot IDs - handle both array and string formats
    $spot_ids = isset($_POST['spot_ids']) ? (is_array($_POST['spot_ids']) ? $_POST['spot_ids'] : explode(',', $_POST['spot_ids'])) : [];
    $vehicle_numbers = isset($_POST['vehicle_numbers']) ? $_POST['vehicle_numbers'] : [];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $total_amount = floatval($_POST['total_amount']);
    $hourly_rate = floatval($_POST['hourly_rate']);
    $user_id = $_SESSION['user_id'];

    // Validate all required fields
    if (empty($spot_ids) || empty($vehicle_numbers) || empty($start_time) || empty($end_time) || $total_amount <= 0 || $hourly_rate <= 0) {
        $_SESSION['message'] = "All fields are required and amounts must be greater than 0.";
        $_SESSION['message_type'] = "error";
        header("Location: dashboard.php");
        exit();
    }

    // Check if we have a vehicle number for each spot
    if (count($spot_ids) != count($vehicle_numbers)) {
        $_SESSION['message'] = "Please provide a vehicle number for each selected spot.";
        $_SESSION['message_type'] = "error";
        header("Location: dashboard.php");
        exit();
    }

    $conn->begin_transaction();

    try {
        // Calculate duration using UTC timestamps
        $start_utc = (new DateTime($start_time, new DateTimeZone('Asia/Dhaka')))->getTimestamp();
        $end_utc = (new DateTime($end_time, new DateTimeZone('Asia/Dhaka')))->getTimestamp();
        $total_seconds = $end_utc - $start_utc;

        if ($total_seconds <= 0) throw new Exception("Invalid time range");

        $billable_hours = ceil($total_seconds / 3600); // Exact hour calculation
        $expected_amount = 0;

        // Verify each spot and calculate total amount
        foreach ($spot_ids as $spot_id) {
            // Convert spot_id to integer
            $spot_id = intval($spot_id);

            $spot_stmt = $conn->prepare("SELECT hourly_rate FROM parking_spots WHERE id = ?");
            $spot_stmt->bind_param("i", $spot_id);
            $spot_stmt->execute();
            $spot_result = $spot_stmt->get_result()->fetch_assoc();

            if (!$spot_result) {
                throw new Exception("Invalid parking spot ID: " . $spot_id);
            }

            $actual_rate = floatval($spot_result['hourly_rate']);
            $expected_amount += round($billable_hours * $actual_rate, 2);
        }

        // Allow for small floating-point differences
        if (abs($expected_amount - $total_amount) > 0.01) {
            throw new Exception("Amount calculation error. Expected: " . number_format($expected_amount, 2) . ", Received: " . number_format($total_amount, 2));
        }

        // Insert bookings for each spot
        $insert_stmt = $conn->prepare("INSERT INTO bookings (user_id, spot_id, vehicle_number, start_time, end_time, amount) VALUES (?, ?, ?, ?, ?, ?)");
        $update_spot_stmt = $conn->prepare("UPDATE parking_spots SET is_occupied = 1, vehicle_number = ? WHERE id = ?");

        foreach ($spot_ids as $index => $spot_id) {
            $spot_stmt = $conn->prepare("SELECT hourly_rate FROM parking_spots WHERE id = ?");
            $spot_stmt->bind_param("i", $spot_id);
            $spot_stmt->execute();
            $spot_result = $spot_stmt->get_result()->fetch_assoc();
            $spot_amount = round($billable_hours * floatval($spot_result['hourly_rate']), 2);

            $vehicle_number = $vehicle_numbers[$index];

            $insert_stmt->bind_param("iisssd", $user_id, $spot_id, $vehicle_number, $start_time, $end_time, $spot_amount);

            if (!$insert_stmt->execute()) {
                throw new Exception("Database insertion failed: " . $conn->error);
            }

            // Update parking spot status to occupied
            $update_spot_stmt->bind_param("si", $vehicle_number, $spot_id);
            if (!$update_spot_stmt->execute()) {
                throw new Exception("Failed to update parking spot status: " . $conn->error);
            }
        }

        $conn->commit();
        $_SESSION['message'] = "Booking successful! Amount: " . number_format($expected_amount, 2);
        header("Location: my-bookings.php");

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "Error: " . $e->getMessage();
        header("Location: dashboard.php");
    }
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
    <title>Dashboard - Smart Parking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <!-- Header -->
            <header>
                <div class="header-content">
                    <h1>Welcome, <?php echo ($user && isset($user['name'])) ? htmlspecialchars($user['name']) : 'User'; ?>!</h1>
                    <div class="user-info">
                        <img src="https://ui-avatars.com/api/?name=<?php echo ($user && isset($user['name'])) ? urlencode($user['name']) : 'User'; ?>&background=random" alt="User Avatar">
                        <span><?php echo ($user && isset($user['email'])) ? htmlspecialchars($user['email']) : ''; ?></span>
                    </div>
                </div>
            </header>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Dashboard Stats -->
            <div class="stats-container">
                <div class="stat-card">
                    <i class="fas fa-car"></i>
                    <div class="stat-info">
                        <h3>Available Spots</h3>
                        <p><?php echo $available_spots->num_rows; ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <div class="stat-info">
                        <h3>Active Bookings</h3>
                        <p><?php echo $active_bookings->num_rows; ?></p>
                    </div>
                </div>
            </div>

            <!-- Booking Form -->
            <div class="booking-section">
                <h2>Book a Parking Spot</h2>

                <form method="POST" action="" class="booking-form" onsubmit="return validateBooking()">
                    <input type="hidden" name="spot_ids" id="spot_ids" required>
                    <input type="hidden" name="total_amount" id="total_amount" required>
                    <input type="hidden" name="hourly_rate" id="hourly_rate" required>
                    <input type="hidden" name="book_spot" value="1">

                    <!-- Step 1: Time Range Selection -->
                    <div class="booking-step" id="step1">
                        <h3>Step 1: Select Time Range</h3>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Please select your desired parking time range. Spots will be shown as available if they're not booked during your selected time.
                        </div>
                        <div class="form-group">
                            <label for="start_time">Start Time:</label>
                            <input type="datetime-local" id="start_time" name="start_time" required onchange="checkTimeRange()">
                        </div>
                        <div class="form-group">
                            <label for="end_time">End Time:</label>
                            <input type="datetime-local" id="end_time" name="end_time" required onchange="checkTimeRange()">
                        </div>
                        <div id="time-range-error" class="error-message"></div>
                        <button type="button" id="check-availability-btn" class="btn btn-secondary" onclick="checkAvailability()" disabled>
                            Check Available Spots
                        </button>
                    </div>

                    <!-- Visual Parking Layout -->
                    <div class="parking-layout-container" id="parking-layout" style="display: none;">
                        <div class="layout-controls">
                            <div class="layout-selector">
                                <label>View Layout:</label>
                                <div class="layout-options">
                                    <button type="button" class="layout-option active" data-layout="grid">
                                        <i class="fas fa-th"></i> Grid View
                                    </button>
                                    <button type="button" class="layout-option" data-layout="realistic">
                                        <i class="fas fa-car-side"></i> Realistic View
                                    </button>
                                </div>
                            </div>

                            <div class="floor-tabs">
                                <?php
                                // Get distinct floor numbers
                                $floors_sql = "SELECT DISTINCT floor_number FROM parking_spots ORDER BY floor_number";
                                $floors_result = $conn->query($floors_sql);
                                $first_floor = true;

                                while($floor = $floors_result->fetch_assoc()) {
                                    $floor_num = $floor['floor_number'];
                                    $active_class = $first_floor ? 'active' : '';
                                    echo "<button class='floor-tab $active_class' data-floor='$floor_num'>Floor $floor_num</button>";
                                    $first_floor = false;
                                }
                                ?>
                            </div>
                        </div>

                        <div class="parking-layout">
                            <?php
                            // Get all floors and their spots
                            // Initially show all spots as available until time range is selected
                            $spots_by_floor_sql = "SELECT p.*, 0 as is_booked
                                FROM parking_spots p ORDER BY p.floor_number, p.spot_number";
                            $all_spots_result = $conn->query($spots_by_floor_sql);

                            $spots_by_floor = [];
                            while($spot = $all_spots_result->fetch_assoc()) {
                                $floor_num = $spot['floor_number'];
                                if(!isset($spots_by_floor[$floor_num])) {
                                    $spots_by_floor[$floor_num] = [];
                                }
                                $spots_by_floor[$floor_num][] = $spot;
                            }

                            $first_floor = true;
                            foreach($spots_by_floor as $floor_num => $spots) {
                                $display = $first_floor ? 'block' : 'none';
                                echo "<div class='floor-layout' id='floor-$floor_num' style='display: $display;'>";
                                echo "<h3>Floor $floor_num Layout</h3>";

                                // Grid layout view
                                echo "<div class='parking-visualization' data-layout-type='grid'>";
                                echo "<div class='spots-grid'>";

                                foreach($spots as $spot) {
                                    $spot_id = $spot['id'];
                                    $spot_num = $spot['spot_number'];
                                    $spot_type = ucfirst($spot['type']);
                                    $hourly_rate = $spot['hourly_rate'];
                                    $is_occupied = $spot['is_booked'] > 0;

                                    // Initially all spots are shown as available but not selectable until time range is checked
                                    $spot_class = 'spot available time-not-selected';
                                    $spot_status = 'Available';
                                    $spot_data_status = 'time-not-selected';
                                    $onclick = '';

                                    echo "<div class='$spot_class' data-spot-id='$spot_id' data-status='$spot_data_status' data-rate='$hourly_rate'>";
                                    echo "<div class='spot-number'>$spot_num</div>";
                                    echo "<div class='spot-details'>";
                                    echo "<div class='spot-type'>$spot_type</div>";
                                    echo "<div class='spot-rate'>$" . number_format($hourly_rate, 2) . "/hr</div>";
                                    echo "<div class='spot-status'>$spot_status</div>";
                                    echo "</div>";
                                    echo "<div class='vehicle-preview'></div>";
                                    if (!$is_occupied) {
                                        echo "<button type='button' class='select-spot-btn' onclick='$onclick'>Select</button>";
                                    }
                                    echo "</div>";
                                }

                                echo "</div>"; // End spots-grid
                                echo "</div>"; // End parking-visualization

                                // Realistic layout view
                                echo "<div class='parking-visualization realistic-layout' data-layout-type='realistic' style='display:none;'>";
                                echo "<div class='parking-lot'>";
                                echo "<div class='entrance-exit'><span>Entrance/Exit</span></div>";

                                // Group spots into rows
                                $spotsPerRow = 5;
                                $spotRows = array_chunk($spots, $spotsPerRow);

                                foreach($spotRows as $rowIndex => $rowSpots) {
                                    echo "<div class='parking-row'>";
                                    echo "<div class='row-label'>Row " . ($rowIndex + 1) . "</div>";
                                    echo "<div class='parking-spaces'>";

                                    foreach($rowSpots as $spot) {
                                        $spot_id = $spot['id'];
                                        $spot_num = $spot['spot_number'];
                                        $spot_type = ucfirst($spot['type']);
                                        $hourly_rate = $spot['hourly_rate'];
                                        $is_occupied = $spot['is_booked'] > 0;

                                        // Initially all spots are shown as available but not selectable until time range is checked
                                        $spot_class = 'parking-space available time-not-selected';
                                        $spot_status = 'Available';
                                        $spot_data_status = 'time-not-selected';
                                        $onclick = '';

                                        echo "<div class='$spot_class' data-spot-id='$spot_id' data-status='$spot_data_status'>";
                                        echo "<div class='space-number'>$spot_num</div>";
                                        echo "<div class='status-text'>$spot_status</div>";
                                        echo "<div class='vehicle-icon'></div>";
                                        if (!$is_occupied) {
                                            echo "<button type='button' class='select-spot-btn realistic' onclick='$onclick'>Select</button>";
                                        }
                                        echo "</div>";
                                    }

                                    echo "</div>"; // End parking-spaces
                                    echo "</div>"; // End parking-row
                                }

                                echo "<div class='driving-path'></div>";
                                echo "</div>"; // End parking-lot
                                echo "</div>"; // End realistic-layout

                                echo "</div>"; // End floor-layout
                                $first_floor = false;
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Step 2: Vehicle Details -->
                    <div class="booking-step" id="step2" style="display: none;">
                        <h3>Step 2: Enter Vehicle Details</h3>
                        <div id="selected-spot-info">
                            <p>Please select a parking spot from the layout above</p>
                        </div>
                        <div id="vehicle-inputs-container">
                            <!-- Vehicle inputs will be dynamically added here -->
                        </div>
                        <div class="form-group">
                            <label>Total Amount:</label>
                            <div id="total_amount_display" class="amount-display">$0.00</div>
                        </div>
                        <button type="submit" id="book-button" class="btn btn-primary" disabled>Confirm Booking</button>
                    </div>
                </form>
            </div>

            <!-- Active Bookings -->
            <div class="active-bookings">
                <h2>Active Bookings</h2>
                <div class="bookings-grid">
                    <?php if ($active_bookings->num_rows > 0): ?>
                        <?php while($booking = $active_bookings->fetch_assoc()): ?>
                        <div class="booking-card">
                            <div class="booking-header">
                                <h3>Spot <?php echo htmlspecialchars($booking['spot_number']); ?></h3>
                                <span class="badge">Active</span>
                            </div>
                            <div class="booking-details">
                                <p><i class="fas fa-car"></i> <?php echo htmlspecialchars($booking['vehicle_number']); ?></p>
                                <p><i class="fas fa-building"></i> Floor <?php echo $booking['floor_number']; ?></p>
                                <p><i class="fas fa-clock"></i> <?php echo date('M d, g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('M d, g:i A', strtotime($booking['end_time'])); ?></p>
                            </div>
                            <button class="btn-cancel" onclick="confirmCancel(<?php echo $booking['id']; ?>)">
                                <i class="fas fa-times"></i> Cancel Booking
                            </button>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-bookings">
                            <i class="fas fa-calendar-times"></i>
                            <p>No active bookings found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3>Cancel Booking</h3>
            <p>Are you sure you want to cancel this booking?</p>
            <div class="modal-buttons">
                <button onclick="closeModal()" class="btn-secondary">No, Keep it</button>
                <button onclick="proceedWithCancel()" class="btn-danger">Yes, Cancel</button>
            </div>
        </div>
    </div>

    <script>
    let currentBookingId = null;
    let selectedSpots = new Map(); // Store selected spots with their details
    const MAX_SELECTED_SPOTS = 4;

    document.addEventListener('DOMContentLoaded', function() {
        const layoutOptions = document.querySelectorAll('.layout-option');
        const floorTabs = document.querySelectorAll('.floor-tab');

        // Set minimum date time for booking
        const now = new Date();
        const minDateTime = now.toISOString().slice(0, 16);

        document.getElementById('start_time').min = minDateTime;
        document.getElementById('end_time').min = minDateTime;

        // Make parking spots clickable
        setupParkingSpotClickHandlers();

        // Layout switching
        layoutOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove active class from all options
                layoutOptions.forEach(opt => opt.classList.remove('active'));

                // Add active class to clicked option
                this.classList.add('active');

                // Get the selected layout type
                const layoutType = this.getAttribute('data-layout');

                // Hide all layout visualizations
                const visualizations = document.querySelectorAll('.parking-visualization');
                visualizations.forEach(vis => {
                    vis.style.display = 'none';
                });

                // Show the selected layout visualization
                const activeFloorNum = document.querySelector('.floor-tab.active').getAttribute('data-floor');
                const activeFloor = document.getElementById('floor-' + activeFloorNum);

                if (activeFloor) {
                    const selectedVisualizations = activeFloor.querySelectorAll(`.parking-visualization[data-layout-type="${layoutType}"]`);
                    selectedVisualizations.forEach(vis => {
                        vis.style.display = 'flex';
                    });
                }
            });
        });

        // Floor tab switching
        floorTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                floorTabs.forEach(t => t.classList.remove('active'));

                // Add active class to clicked tab
                this.classList.add('active');

                // Hide all floor layouts
                const floorLayouts = document.querySelectorAll('.floor-layout');
                floorLayouts.forEach(layout => {
                    layout.style.display = 'none';
                });

                // Show the selected floor layout
                const floorNum = this.getAttribute('data-floor');
                document.getElementById('floor-' + floorNum).style.display = 'block';

                // Maintain the current layout view
                const activeLayoutType = document.querySelector('.layout-option.active').getAttribute('data-layout');
                const activeFloor = document.getElementById('floor-' + floorNum);

                if (activeFloor) {
                    const visualizations = activeFloor.querySelectorAll('.parking-visualization');
                    visualizations.forEach(vis => {
                        vis.style.display = 'none';
                    });

                    const selectedVisualizations = activeFloor.querySelectorAll(`.parking-visualization[data-layout-type="${activeLayoutType}"]`);
                    selectedVisualizations.forEach(vis => {
                        vis.style.display = 'flex';
                    });
                }
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const confirmModal = document.getElementById('confirmModal');
            if (event.target === confirmModal) {
                closeModal();
            }
        };

        // Form validation
        const bookingForm = document.querySelector('.booking-form');
        bookingForm.addEventListener('submit', function(e) {
            if (!selectedSpots.size) {
                e.preventDefault();
                alert('Please select at least one parking spot from the layout');
                return false;
            }

            // Check if all vehicle numbers are filled
            let allVehicleNumbersFilled = true;
            const vehicleInputs = document.querySelectorAll('input[name^="vehicle_numbers"]');
            vehicleInputs.forEach(input => {
                if (!input.value.trim()) {
                    allVehicleNumbersFilled = false;
                    input.setCustomValidity('Vehicle number is required');
                } else {
                    input.setCustomValidity('');
                }
            });

            if (!allVehicleNumbersFilled) {
                e.preventDefault();
                alert('Please enter vehicle numbers for all selected spots');
                return false;
            }

            // Update spot statuses to booked
            selectedSpots.forEach((spot, spotId) => {
                updateSpotStatus(spotId, 'booked');
            });
        });

        // Add event listeners for time inputs
        document.getElementById('start_time').addEventListener('change', calculateTotalAmount);
        document.getElementById('end_time').addEventListener('change', calculateTotalAmount);
    });

    // Function to check time range validity
    function checkTimeRange() {
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        const errorElement = document.getElementById('time-range-error');
        const checkBtn = document.getElementById('check-availability-btn');

        if (startTime && endTime) {
            const start = new Date(startTime);
            const end = new Date(endTime);
            const now = new Date();

            // Check if start time is in the future
            if (start < now) {
                errorElement.textContent = 'Start time must be in the future';
                checkBtn.disabled = true;
                return false;
            }

            // Check if end time is after start time
            if (end <= start) {
                errorElement.textContent = 'End time must be after start time';
                checkBtn.disabled = true;
                return false;
            }

            // Check if duration is reasonable (e.g., not more than 24 hours)
            const durationHours = (end - start) / (1000 * 60 * 60);
            if (durationHours > 24) {
                errorElement.textContent = 'Booking duration cannot exceed 24 hours';
                checkBtn.disabled = true;
                return false;
            }

            // All checks passed
            errorElement.textContent = '';
            checkBtn.disabled = false;
            return true;
        } else {
            checkBtn.disabled = true;
            return false;
        }
    }

    function checkAvailability() {
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;

        // Show loading state
        const checkBtn = document.getElementById('check-availability-btn');
        checkBtn.disabled = true;
        checkBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';

        // Make AJAX call to check availability
        fetch('check_availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                start_time: startTime,
                end_time: endTime
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update spot statuses based on availability
                updateAvailableSpots(data.available_spots);

                // Show parking layout and step 2
                document.getElementById('parking-layout').style.display = 'block';

                // Remove any existing time range info
                const existingInfo = document.getElementById('time-range-info');
                if (existingInfo) {
                    existingInfo.remove();
                }

                // Add the time range info at the top of the parking layout
                const timeRangeInfo = document.createElement('div');
                timeRangeInfo.className = 'alert alert-success';
                timeRangeInfo.id = 'time-range-info';
                timeRangeInfo.innerHTML = `
                    <i class="fas fa-clock"></i>
                    Showing available spots for: <strong>${new Date(startTime).toLocaleString()}</strong> to <strong>${new Date(endTime).toLocaleString()}</strong>
                `;

                const parkingLayout = document.getElementById('parking-layout');
                parkingLayout.insertBefore(timeRangeInfo, parkingLayout.firstChild);

                // Reset selected spots when searching again
                selectedSpots.clear();
                updateSelectedSpotsDisplay();
            } else {
                alert('Error checking availability: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error checking availability. Please try again.');
        })
        .finally(() => {
            // Reset button state
            checkBtn.disabled = false;
            checkBtn.innerHTML = 'Check Available Spots';
        });
    }

    function updateAvailableSpots(availableSpots) {
        // Get all spots
        const allSpots = document.querySelectorAll('.spot, .parking-space');

        // First reset all spots to their default state
        allSpots.forEach(spot => {
            const spotId = spot.getAttribute('data-spot-id');

            // Reset spot appearance
            spot.classList.remove('available', 'occupied', 'processing', 'time-not-selected');
            spot.classList.add('occupied'); // Default to occupied
            spot.setAttribute('data-status', 'occupied');

            // Update status text
            const statusElement = spot.querySelector('.spot-status');
            if (statusElement) {
                statusElement.textContent = 'Occupied';
            }

            const statusTextElement = spot.querySelector('.status-text');
            if (statusTextElement) {
                statusTextElement.textContent = 'Occupied';
            }

            // Disable select buttons by default
            const selectBtn = spot.querySelector('.select-spot-btn');
            if (selectBtn) {
                selectBtn.disabled = true;
                selectBtn.style.backgroundColor = '#e74c3c';
                selectBtn.textContent = 'Occupied';
                selectBtn.onclick = null;
            }
        });

        // Then update available spots based on the API response
        allSpots.forEach(spot => {
            const spotId = spot.getAttribute('data-spot-id');
            // Convert spotId to string for comparison since availableSpots contains string IDs
            const isAvailable = availableSpots.includes(spotId);

            // Update spot status
            if (isAvailable) {
                spot.classList.remove('occupied', 'processing', 'time-not-selected');
                spot.classList.add('available');
                spot.setAttribute('data-status', 'available');

                // Update status text
                const statusElement = spot.querySelector('.spot-status');
                if (statusElement) {
                    statusElement.textContent = 'Available';
                }

                const statusTextElement = spot.querySelector('.status-text');
                if (statusTextElement) {
                    statusTextElement.textContent = 'Available';
                }

                // Enable select button
                const selectBtn = spot.querySelector('.select-spot-btn');
                if (selectBtn) {
                    selectBtn.disabled = false;
                    selectBtn.style.backgroundColor = '';
                    selectBtn.textContent = 'Select';

                    // Add onclick handler for available spots
                    const spotNumber = spot.querySelector('.spot-number, .space-number').textContent;
                    const floorNumber = spot.closest('.floor-layout').getAttribute('id').replace('floor-', '');
                    const spotType = spot.querySelector('.spot-type')?.textContent || 'Standard';
                    const hourlyRate = spot.querySelector('.spot-rate')?.textContent.replace('$', '').replace('/hr', '') || '2.00';

                    selectBtn.onclick = () => selectSpot(spotId, spotNumber, floorNumber, spotType, hourlyRate);
                }
            }
        });

        // Setup click handlers for the updated spots
        setupParkingSpotClickHandlers();
    }

    // Setup click handlers for parking spots
    function setupParkingSpotClickHandlers() {
        // Get all spots from both grid and realistic layouts
        const allSpots = document.querySelectorAll('.spot, .parking-space');

        allSpots.forEach(spot => {
            // Only make available spots clickable
            if (spot.classList.contains('available')) {
                spot.addEventListener('click', function(e) {
                    // Prevent click if the click was on the select button (to avoid double triggering)
                    if (e.target.classList.contains('select-spot-btn')) {
                        return;
                    }

                    // Get spot data
                    const spotId = this.getAttribute('data-spot-id');
                    const spotNumber = this.querySelector('.spot-number, .space-number').textContent;
                    const floorNumber = this.closest('.floor-layout').getAttribute('id').replace('floor-', '');

                    // Get spot type and hourly rate
                    let spotType, hourlyRate;
                    if (this.classList.contains('spot')) {
                        // Grid layout
                        spotType = this.querySelector('.spot-type').textContent;
                        hourlyRate = this.querySelector('.spot-rate').textContent.replace('$', '').replace('/hr', '');
                    } else {
                        // Realistic layout - need to find the corresponding grid spot to get details
                        const gridSpot = document.querySelector(`.spot[data-spot-id="${spotId}"]`);
                        if (gridSpot) {
                            spotType = gridSpot.querySelector('.spot-type').textContent;
                            hourlyRate = gridSpot.querySelector('.spot-rate').textContent.replace('$', '').replace('/hr', '');
                        } else {
                            // Default values if grid spot not found
                            spotType = 'Standard';
                            hourlyRate = '2.00';
                        }
                    }

                    // Call the selectSpot function
                    selectSpot(spotId, spotNumber, floorNumber, spotType, hourlyRate);
                });
            }
        });
    }

    // Function to select a parking spot
    function selectSpot(spotId, spotNumber, floorNumber, spotType, hourlyRate) {
        // Only allow selection if spot is available
        const spot = document.querySelector(`[data-spot-id="${spotId}"]`);
        const spotStatus = spot ? spot.getAttribute('data-status') : null;

        // Prevent selection if spot is not available or time range is not selected
        if (!spot || spotStatus !== 'available' || spotStatus === 'time-not-selected') {
            if (spotStatus === 'time-not-selected') {
                alert('Please select a time range and check availability first.');
            }
            return;
        }

        // Check if we've reached the maximum number of spots
        if (selectedSpots.size >= MAX_SELECTED_SPOTS && !selectedSpots.has(spotId)) {
            alert(`You can only select up to ${MAX_SELECTED_SPOTS} spots at a time.`);
            return;
        }

        // If spot is already selected, deselect it
        if (selectedSpots.has(spotId)) {
            selectedSpots.delete(spotId);
            updateSpotStatus(spotId, 'available');
            updateSelectedSpotsDisplay();
            return;
        }

        // Add spot to selected spots
        selectedSpots.set(spotId, {
            spotId,
            spotNumber,
            floorNumber,
            spotType,
            hourlyRate: parseFloat(hourlyRate)
        });

        // Update spot status to processing
        updateSpotStatus(spotId, 'processing');
        updateSelectedSpotsDisplay();

        // Recalculate total amount
        calculateTotalAmount();
    }

    // Function to update the display of selected spots and create vehicle inputs
    function updateSelectedSpotsDisplay() {
        const selectedSpotInfo = document.getElementById('selected-spot-info');
        const vehicleInputsContainer = document.getElementById('vehicle-inputs-container');
        const spotsList = Array.from(selectedSpots.values());
        const spotIdsInput = document.getElementById('spot_ids');

        // Update hidden spot_ids input
        spotIdsInput.value = Array.from(selectedSpots.keys()).join(',');

        // Show step 2 if spots are selected
        document.getElementById('step2').style.display = selectedSpots.size > 0 ? 'block' : 'none';

        if (spotsList.length === 0) {
            selectedSpotInfo.innerHTML = '<p>Please select parking spots from the layout above</p>';
            vehicleInputsContainer.innerHTML = '';
            document.getElementById('book-button').disabled = true;
            return;
        }

        let html = `
            <div class="selected-spots-header">
                <h4>Selected Spots (${spotsList.length}/${MAX_SELECTED_SPOTS})</h4>
            </div>
            <div class="selected-spots-list">
        `;

        spotsList.forEach((spot, index) => {
            html += `
                <div class="selected-spot-item">
                    <div class="spot-header">
                        <span class="spot-badge">Floor ${spot.floorNumber} - Spot ${spot.spotNumber}</span>
                        <button type="button" class="remove-spot-btn" onclick="removeSpot('${spot.spotId}')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="spot-details">
                        <p><strong>Type:</strong> ${spot.spotType}</p>
                        <p><strong>Rate:</strong> $${spot.hourlyRate.toFixed(2)}/hour</p>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        selectedSpotInfo.innerHTML = html;

        // Create vehicle inputs for each selected spot
        let vehicleInputsHtml = '';
        spotsList.forEach((spot, index) => {
            vehicleInputsHtml += `
                <div class="form-group vehicle-input">
                    <label for="vehicle_number_${index}">Vehicle Number for Spot ${spot.spotNumber}:</label>
                    <input type="text" id="vehicle_number_${index}" name="vehicle_numbers[${index}]" required
                           placeholder="Enter vehicle number" class="vehicle-number-input">
                </div>
            `;
        });

        vehicleInputsContainer.innerHTML = vehicleInputsHtml;

        // Add event listeners to the new vehicle inputs
        const vehicleInputs = document.querySelectorAll('.vehicle-number-input');
        vehicleInputs.forEach(input => {
            input.addEventListener('input', function() {
                validateVehicleNumbers();
            });
        });

        // Enable book button if all vehicle numbers are entered
        validateVehicleNumbers();
    }

    // Function to validate vehicle numbers
    function validateVehicleNumbers() {
        const vehicleInputs = document.querySelectorAll('.vehicle-number-input');
        let allValid = true;

        vehicleInputs.forEach(input => {
            const vehicleNumber = input.value.trim();
            const isValidFormat = /^[A-Z0-9-]+$/.test(vehicleNumber);

            if (!vehicleNumber) {
                input.setCustomValidity('Vehicle number is required');
                allValid = false;
            } else if (!isValidFormat) {
                input.setCustomValidity('Please enter a valid vehicle number');
                allValid = false;
            } else {
                input.setCustomValidity('');
            }
        });

        document.getElementById('book-button').disabled = !allValid || vehicleInputs.length === 0;
    }

    // Function to remove a spot from selection
    function removeSpot(spotId) {
        selectedSpots.delete(spotId);
        updateSpotStatus(spotId, 'available');
        updateSelectedSpotsDisplay();

        // Recalculate total amount
        calculateTotalAmount();
    }

    // Function to update spot status
    function updateSpotStatus(spotId, status) {
        // Get all spots with the same ID across all layouts
        const spots = document.querySelectorAll(`[data-spot-id="${spotId}"]`);

        spots.forEach(spot => {
            // Remove previous status classes
            spot.classList.remove('available', 'processing', 'booked', 'time-not-selected');

            // Add new status class
            spot.classList.add(status);

            // Update data-status attribute
            spot.setAttribute('data-status', status);

            // Update status text in grid view
            const statusElement = spot.querySelector('.spot-status');
            if (statusElement) {
                statusElement.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            }

            // Update status text in realistic view
            const statusTextElement = spot.querySelector('.status-text');
            if (statusTextElement) {
                statusTextElement.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            }

            // Update select button
            const selectBtn = spot.querySelector('.select-spot-btn');
            if (selectBtn) {
                if (status === 'available') {
                    selectBtn.disabled = false;
                    selectBtn.textContent = 'Select';
                    selectBtn.style.backgroundColor = '';
                } else if (status === 'processing') {
                    selectBtn.disabled = false;
                    selectBtn.textContent = 'Selected';
                    selectBtn.style.backgroundColor = '#27ae60';
                } else {
                    selectBtn.disabled = true;
                    selectBtn.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    selectBtn.style.backgroundColor = '#e74c3c';
                }
            }
        });
    }

    // Function to calculate total amount
    function calculateTotalAmount() {
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;

        if (!startTime || !endTime || selectedSpots.size === 0) {
            document.getElementById('total_amount_display').textContent = '$0.00';
            document.getElementById('total_amount').value = 0;
            return;
        }

        const start = new Date(startTime);
        const end = new Date(endTime);
        const durationHours = Math.ceil((end - start) / (1000 * 60 * 60)); // Round up to nearest hour

        let totalAmount = 0;
        selectedSpots.forEach(spot => {
            totalAmount += spot.hourlyRate * durationHours;
        });

        document.getElementById('total_amount_display').textContent = '$' + totalAmount.toFixed(2);
        document.getElementById('total_amount').value = totalAmount.toFixed(2);
        document.getElementById('hourly_rate').value = Array.from(selectedSpots.values())[0]?.hourlyRate || 0;

        // Highlight the amount to draw attention to the change
        const amountDisplay = document.getElementById('total_amount_display');
        amountDisplay.classList.add('amount-highlight');
        setTimeout(() => {
            amountDisplay.classList.remove('amount-highlight');
        }, 1000);
    }

    function confirmCancel(bookingId) {
        currentBookingId = bookingId;
        document.getElementById('confirmModal').style.display = 'flex';
    }

    function proceedWithCancel() {
        window.location.href = 'cancel-booking.php?id=' + currentBookingId;
    }

    function closeModal() {
        document.getElementById('confirmModal').style.display = 'none';
    }

    // Add CSS for improved styling
    const style = document.createElement('style');
    style.textContent = `
        .amount-highlight {
            animation: highlight 1s ease-in-out;
        }

        @keyframes highlight {
            0% { background-color: rgba(0, 65, 106, 0.1); }
            50% { background-color: rgba(0, 65, 106, 0.3); }
            100% { background-color: transparent; }
        }

        .booking-step {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .booking-step h3 {
            margin-top: 0;
            color: #00416A;
            margin-bottom: 1.5rem;
        }

        /* Time not selected state styling */
        .time-not-selected {
            opacity: 0.7;
            position: relative;
        }

        .time-not-selected::after {
            content: "Select time first";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }

        .time-not-selected:hover::after {
            opacity: 1;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 16px;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 16px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 16px;
        }

        .vehicle-input {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            border-left: 3px solid #00416A;
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>