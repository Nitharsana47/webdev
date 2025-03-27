<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); // Redirect if not accessed via POST
    exit;
}

// Retrieve all POST data
$flight_id = $_POST['flightId'] ?? '';
$flight_name = $_POST['flightName'] ?? '';
$route = $_POST['route'] ?? '';
$departure = $_POST['departure'] ?? '';
$arrival = $_POST['arrival'] ?? '';
$selected_class = $_POST['class'] ?? '';
$selected_seat = $_POST['seat'] ?? '';
$selected_bin = $_POST['bin_slot'] ?? '';
$premium_enabled = $_POST['premium_enabled'] === '1';
$luggage_enabled = $_POST['luggage_enabled'] === '1';
$total_cost = $_POST['total_cost'] ?? 0;

$passenger_name = $_POST['passenger_name'] ?? '';
$passenger_age = $_POST['passenger_age'] ?? '';
$passenger_gender = $_POST['passenger_gender'] ?? '';

$card_number = $_POST['card_number'] ?? '';
$card_holder = $_POST['card_holder'] ?? '';
$expiry_date = $_POST['expiry_date'] ?? '';
$cvv = $_POST['cvv'] ?? '';

// In a real scenario, you'd validate data and update the database here
// For now, we'll assume the booking is successful and display a confirmation

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - SkyVoyage</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e0f7fa, #80deea);
            color: #333;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
        }
        h1 {
            font-size: 2.5rem;
            color: #0288d1;
            margin-bottom: 20px;
        }
        .confirmation-message {
            font-size: 1.2rem;
            color: #4caf50;
            margin-bottom: 20px;
        }
        .details-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 10px;
            text-align: left;
        }
        .details-section h2 {
            font-size: 1.5rem;
            color: #0288d1;
            margin-bottom: 10px;
        }
        .details-section p {
            font-size: 1rem;
            color: #444;
            margin: 5px 0;
        }
        .back-button {
            background: linear-gradient(90deg, #0288d1, #03a9f4);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            margin-top: 20px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .back-button:hover {
            background: linear-gradient(90deg, #0277bd, #039be5);
        }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-check-circle"></i> Booking Confirmed!</h1>
    <p class="confirmation-message">Thank you, <?php echo htmlspecialchars($passenger_name); ?>, for booking with SkyVoyage. Your flight is confirmed!</p>

    <div class="details-section">
        <h2>Flight Details</h2>
        <p><strong>Flight:</strong> <?php echo htmlspecialchars($flight_name); ?></p>
        <p><strong>Route:</strong> <?php echo htmlspecialchars($route); ?></p>
        <p><strong>Departure:</strong> <?php echo htmlspecialchars($departure); ?></p>
        <p><strong>Arrival:</strong> <?php echo htmlspecialchars($arrival); ?></p>
        <p><strong>Class:</strong> <?php echo htmlspecialchars($selected_class); ?></p>
        <p><strong>Seat:</strong> <?php echo htmlspecialchars($selected_seat); ?></p>
        <p><strong>Premium Seat:</strong> <?php echo $premium_enabled ? 'Yes (₹2000)' : 'No'; ?></p>
        <p><strong>Luggage Bin:</strong> <?php echo $luggage_enabled && $selected_bin ? htmlspecialchars($selected_bin) : 'Not Selected'; ?></p>
    </div>

    <div class="details-section">
        <h2>Passenger Details</h2>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($passenger_name); ?></p>
        <p><strong>Age:</strong> <?php echo htmlspecialchars($passenger_age); ?></p>
        <p><strong>Gender:</strong> <?php echo htmlspecialchars($passenger_gender); ?></p>
    </div>

    <div class="details-section">
        <h2>Payment Details</h2>
        <p><strong>Total Cost:</strong> ₹<?php echo number_format($total_cost, 2); ?></p>
        <p><strong>Card Holder:</strong> <?php echo htmlspecialchars($card_holder); ?></p>
        <p><strong>Card Number:</strong> <?php echo htmlspecialchars(substr($card_number, -4)); ?> (Last 4 digits)</p>
        <p><strong>Expiry Date:</strong> <?php echo htmlspecialchars($expiry_date); ?></p>
    </div>

    <a href="index.php" class="back-button">Back to Home</a>
</div>
</body>
</html>