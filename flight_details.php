<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); // Redirect if not accessed via POST
    exit;
}

// Retrieve flight details from POST
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

// Connect to database to fetch additional details if needed
$conn = new mysqli("localhost", "root", "", "train_booking");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch base price for the selected class
$class_query = "SELECT price FROM flight_classes WHERE flight_id = ? AND class_type = ?";
$stmt = $conn->prepare($class_query);
$stmt->bind_param("ss", $flight_id, $selected_class);
$stmt->execute();
$class_result = $stmt->get_result()->fetch_assoc();
$base_price = $class_result['price'] ?? 0;

// Calculate total cost
$total_cost = $base_price;
if ($premium_enabled) $total_cost += 2000;
if ($luggage_enabled) $total_cost += 300;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Booking Details - SkyVoyage</title>
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
        }
        h1 {
            font-size: 2rem;
            color: #0288d1;
            text-align: center;
            margin-bottom: 20px;
        }
        .section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 10px;
        }
        .section h2 {
            font-size: 1.5rem;
            color: #0288d1;
            margin-bottom: 10px;
        }
        .section p {
            font-size: 1rem;
            color: #444;
            margin: 5px 0;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 0.9rem;
        }
        .confirm-button {
            background: linear-gradient(90deg, #4caf50, #66bb6a);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            transition: all 0.3s ease;
        }
        .confirm-button:hover {
            background: linear-gradient(90deg, #43a047, #5cb85c);
        }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-plane"></i> Flight Booking Details</h1>

    <div class="section">
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

    <form action="book_flight.php" method="POST">
        <input type="hidden" name="flightId" value="<?php echo htmlspecialchars($flight_id); ?>">
        <input type="hidden" name="flightName" value="<?php echo htmlspecialchars($flight_name); ?>">
        <input type="hidden" name="route" value="<?php echo htmlspecialchars($route); ?>">
        <input type="hidden" name="departure" value="<?php echo htmlspecialchars($departure); ?>">
        <input type="hidden" name="arrival" value="<?php echo htmlspecialchars($arrival); ?>">
        <input type="hidden" name="class" value="<?php echo htmlspecialchars($selected_class); ?>">
        <input type="hidden" name="seat" value="<?php echo htmlspecialchars($selected_seat); ?>">
        <input type="hidden" name="bin_slot" value="<?php echo htmlspecialchars($selected_bin); ?>">
        <input type="hidden" name="premium_enabled" value="<?php echo $premium_enabled ? '1' : '0'; ?>">
        <input type="hidden" name="luggage_enabled" value="<?php echo $luggage_enabled ? '1' : '0'; ?>">
        <input type="hidden" name="total_cost" value="<?php echo $total_cost; ?>">

        <div class="section">
            <h2>Passenger Details</h2>
            <div class="form-group">
                <label for="passenger-name">Name:</label>
                <input type="text" id="passenger-name" name="passenger_name" placeholder="Enter your name" required>
            </div>
            <div class="form-group">
                <label for="passenger-age">Age:</label>
                <input type="number" id="passenger-age" name="passenger_age" placeholder="Enter your age" min="1" max="120" required>
            </div>
            <div class="form-group">
                <label for="passenger-gender">Gender:</label>
                <select id="passenger-gender" name="passenger_gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>

        <div class="section">
            <h2>Payment Details</h2>
            <p><strong>Total Cost:</strong> ₹<?php echo number_format($total_cost, 2); ?></p>
            <div class="form-group">
                <label for="card-number">Credit Card Number:</label>
                <input type="text" id="card-number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" required>
            </div>
            <div class="form-group">
                <label for="card-holder">Card Holder Name:</label>
                <input type="text" id="card-holder" name="card_holder" placeholder="John Doe" required>
            </div>
            <div class="form-group">
                <label for="expiry-date">Expiry Date:</label>
                <input type="text" id="expiry-date" name="expiry_date" placeholder="MM/YY" maxlength="5" required>
            </div>
            <div class="form-group">
                <label for="cvv">CVV:</label>
                <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="3" required>
            </div>
            <button type="submit" class="confirm-button">Confirm Payment</button>
        </div>
    </form>
</div>
</body>
</html>