<?php
require_once 'config.php';

// Fetch bookings
$bookings_query = "SELECT * FROM bookings";
$bookings_result = $conn->query($bookings_query);

// Fetch parking spots
$spots_query = "SELECT * FROM parking_spots";
$spots_result = $conn->query($spots_query);

// Display bookings
echo '<h2>Bookings</h2>';
if ($bookings_result->num_rows > 0) {
    echo '<table border="1">';
    echo '<tr><th>ID</th><th>User ID</th><th>Spot ID</th><th>Vehicle Number</th><th>Start Time</th><th>End Time</th><th>Status</th><th>Amount</th><th>Payment Status</th><th>Created At</th></tr>';
    while ($row = $bookings_result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $row['id'] . '</td>';
        echo '<td>' . $row['user_id'] . '</td>';
        echo '<td>' . $row['spot_id'] . '</td>';
        echo '<td>' . $row['vehicle_number'] . '</td>';
        echo '<td>' . $row['start_time'] . '</td>';
        echo '<td>' . $row['end_time'] . '</td>';
        echo '<td>' . $row['status'] . '</td>';
        echo '<td>' . $row['amount'] . '</td>';
        echo '<td>' . $row['payment_status'] . '</td>';
        echo '<td>' . $row['created_at'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo 'No bookings found';
}

// Display parking spots
echo '<h2>Parking Spots</h2>';
if ($spots_result->num_rows > 0) {
    echo '<table border="1">';
    echo '<tr><th>ID</th><th>Spot Number</th><th>Floor Number</th><th>Is Occupied</th><th>Vehicle Number</th><th>Type</th><th>Hourly Rate</th><th>Created At</th></tr>';
    while ($row = $spots_result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $row['id'] . '</td>';
        echo '<td>' . $row['spot_number'] . '</td>';
        echo '<td>' . $row['floor_number'] . '</td>';
        echo '<td>' . ($row['is_occupied'] ? 'Yes' : 'No') . '</td>';
        echo '<td>' . $row['vehicle_number'] . '</td>';
        echo '<td>' . $row['type'] . '</td>';
        echo '<td>' . $row['hourly_rate'] . '</td>';
        echo '<td>' . $row['created_at'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo 'No parking spots found';
}

$conn->close();
?>
