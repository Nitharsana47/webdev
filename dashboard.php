<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "", "train_booking");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$passengerEmail = "madhu@example.com";
$query = "SELECT fp.passenger_id, fp.flight_id, fp.departure, fp.flight_status, fp.concierge_actions, fp.seat_number, fp.reserved_bin_slot, f.flight_name, f.route 
          FROM flight_passengers fp 
          JOIN flights f ON fp.flight_id = f.flight_id 
          WHERE fp.passenger_email = '$passengerEmail'";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .booking { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
        .status { font-weight: bold; }
        .actions { margin-top: 10px; }
    </style>
</head>
<body>
    <h1>Your Bookings</h1>
    <button onclick="window.location.href='check_flight_status.php'">Check Flight Status Now</button>
    <?php while ($booking = $result->fetch_assoc()) { ?>
        <div class="booking">
            <h3>Flight: <?php echo htmlspecialchars($booking['flight_name']); ?> (<?php echo htmlspecialchars($booking['route']); ?>)</h3>
            <p>Departure: <?php echo htmlspecialchars($booking['departure']); ?></p>
            <p>Seat: <?php echo htmlspecialchars($booking['seat_number']); ?></p>
            <?php if ($booking['reserved_bin_slot']) { ?>
                <p><strong>Reserved Overhead Bin:</strong> <?php echo htmlspecialchars($booking['reserved_bin_slot']); ?></p>
            <?php } ?>
            <p class="status">Status: <?php echo htmlspecialchars($booking['flight_status']); ?></p>
            <?php if ($booking['concierge_actions']) { 
                $actions = json_decode($booking['concierge_actions'], true); ?>
                <div class="actions">
                    <h4>Concierge Assistance:</h4>
                    <ul>
                        <?php foreach ($actions as $action) { ?>
                            <li><?php echo htmlspecialchars($action); ?></li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</body>
</html>
<?php $conn->close(); ?>