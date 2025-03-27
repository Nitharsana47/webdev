<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "", "train_booking");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Simulated payment function
function process_payment($amount) {
    return "Payment of ₹$amount processed successfully.";
}

// Generate random seat number
function generate_seat_number() {
    $sections = ['A', 'B', 'C', 'D'];
    $number = rand(1, 30);
    return $sections[array_rand($sections)] . '-' . $number; // e.g., "A-12"
}

// Handle cancellation and allocate to highest-priority waitlist
function handle_cancellation($conn, $train_name, $class_type) {
    $stmt = $conn->prepare("SELECT booking_id, waitlist_priority FROM bookings WHERE train_name = ? AND class_type = ? AND status = 'TQWL' ORDER BY waitlist_priority DESC LIMIT 1");
    $stmt->bind_param("ss", $train_name, $class_type);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $seat_number = generate_seat_number();
        $stmt = $conn->prepare("UPDATE bookings SET status = 'Confirmed', seat_number = ? WHERE booking_id = ?");
        $stmt->bind_param("si", $seat_number, $row['booking_id']);
        $stmt->execute();
        return "Seat $seat_number allocated to booking ID {$row['booking_id']} from TQWL.";
    }
    return "No waitlist entries to allocate.";
}

$trainName = urldecode($_GET['trainName'] ?? 'Unknown');
$route = urldecode($_GET['route'] ?? 'Unknown');
$departure = urldecode($_GET['departure'] ?? 'Unknown');
$arrival = urldecode($_GET['arrival'] ?? 'Unknown');

// Define base and Tatkal prices (Tatkal = base + 30%)
$classPrices = [
    'Cholan Express' => [
        '3A' => ['base' => 980, 'tatkal' => 1274], '2A' => ['base' => 1400, 'tatkal' => 1820], 
        '1A' => ['base' => 2200, 'tatkal' => 2860], 'SL' => ['base' => 350, 'tatkal' => 455], 
        'Seater' => ['base' => 300, 'tatkal' => 390], 'PET' => ['base' => 500, 'tatkal' => 650]
    ],
    'Vande Bharat Express' => [
        'CC' => ['base' => 1750, 'tatkal' => 2275], 'EC' => ['base' => 3200, 'tatkal' => 4160], 
        'PET' => ['base' => 600, 'tatkal' => 780]
    ],
    'Rajdhani Express' => [
        '1A' => ['base' => 5000, 'tatkal' => 6500], '2A' => ['base' => 3200, 'tatkal' => 4160]
    ],
    'Pandian Express' => [
        '3A' => ['base' => 1100, 'tatkal' => 1430], '2A' => ['base' => 1600, 'tatkal' => 2080]
    ],
    'Tejas Express' => [
        'CC' => ['base' => 1800, 'tatkal' => 2340], 'EC' => ['base' => 3000, 'tatkal' => 3900]
    ],
    'Shatabdi Express' => [
        'CC' => ['base' => 1500, 'tatkal' => 1950], 'EC' => ['base' => 2500, 'tatkal' => 3250]
    ]
];

// Fetch compartment data
$compartments_query = "SELECT class_type, available_seats, is_pet_compartment FROM compartments WHERE train_name = ?";
$stmt = $conn->prepare($compartments_query);
$stmt->bind_param("s", $trainName);
$stmt->execute();
$compartments_result = $stmt->get_result();
$compartments = [];
$isPetFriendly = false;
while ($row = $compartments_result->fetch_assoc()) {
    $compartments[$row['class_type']] = [
        'available_seats' => $row['available_seats'],
        'is_pet_compartment' => $row['is_pet_compartment']
    ];
    if ($row['is_pet_compartment']) $isPetFriendly = true;
}

// Fetch Tatkal data
$tatkal_query = "SELECT tatkal_available FROM tatkal_trains WHERE train_name = ?";
$stmt = $conn->prepare($tatkal_query);
$stmt->bind_param("s", $trainName);
$stmt->execute();
$tatkal_result = $stmt->get_result();
$isTatkalTrain = $tatkal_result->num_rows > 0;
$tatkalQuota = $isTatkalTrain ? $tatkal_result->fetch_assoc()['tatkal_available'] : 0;

// Fetch current Tatkal bookings
$bookings_query = "SELECT COUNT(*) as tatkal_used FROM bookings WHERE train_name = ? AND is_tatkal = 1";
$stmt = $conn->prepare($bookings_query);
$stmt->bind_param("s", $trainName);
$stmt->execute();
$tatkal_used = $stmt->get_result()->fetch_assoc()['tatkal_used'] ?? 0;
$tatkal_remaining = $tatkalQuota - $tatkal_used;

// Fetch waitlist counts
$waitlist_query = "SELECT class_type, COUNT(*) as waitlist_count FROM bookings WHERE train_name = ? AND status = 'TQWL' GROUP BY class_type";
$stmt = $conn->prepare($waitlist_query);
$stmt->bind_param("s", $trainName);
$stmt->execute();
$waitlist_result = $stmt->get_result();
$waitlist_counts = [];
while ($row = $waitlist_result->fetch_assoc()) {
    $waitlist_counts[$row['class_type']] = $row['waitlist_count'];
}

// Simulate Tatkal open status (for demo)
$isTatkalOpen = $isTatkalTrain; // In production, tie to departure date

$bookingMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedClass = $_POST['class_type'] ?? '';
    $passengerName = $_POST['passenger_name'] ?? '';
    $age = $_POST['age'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $cardNumber = $_POST['card_number'] ?? '';
    $expiry = $_POST['expiry'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    $useTatkal = isset($_POST['use_tatkal']) && $isTatkalTrain && $isTatkalOpen;
    $hasPet = $isPetFriendly && isset($_POST['has_pet']) && $_POST['has_pet'] === 'yes';
    $petSize = $hasPet ? ($_POST['pet_size'] ?? '') : null;
    $extraPayment = $useTatkal ? ($_POST['extra_payment'] ?? 0) : 0;

    if (!empty($selectedClass) && !empty($passengerName) && !empty($age) && !empty($gender) && 
        !empty($cardNumber) && !empty($expiry) && !empty($cvv) && (!$hasPet || !empty($petSize))) {
        $available = $compartments[$selectedClass]['available_seats'] ?? 0;
        $isPetCompartment = $compartments[$selectedClass]['is_pet_compartment'] ?? false;
        $basePrice = $classPrices[$trainName][$selectedClass]['base'] ?? 0;
        $tatkalPrice = $classPrices[$trainName][$selectedClass]['tatkal'] ?? 0;
        $totalPrice = $useTatkal ? $tatkalPrice + $extraPayment : $basePrice;

        // Pet validation
        if ($hasPet && !$isPetCompartment) {
            $bookingMessage = "Error: You must select the PET compartment when traveling with a pet.";
        } else {
            $conn->begin_transaction();
            try {
                if ($available > 0 && !$useTatkal) {
                    // Regular booking
                    $seatNumber = generate_seat_number();
                    $status = 'Confirmed';
                    $stmt = $conn->prepare("INSERT INTO bookings (train_name, class_type, passenger_name, age, gender, card_number, expiry, cvv, seat_number, status, payment_amount, has_pet, pet_size, is_tatkal) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $isTatkalFlag = 0;
                    $stmt->bind_param("sssisssisdisis", $trainName, $selectedClass, $passengerName, $age, $gender, $cardNumber, $expiry, $cvv, $seatNumber, $status, $totalPrice, $hasPet, $petSize, $isTatkalFlag);
                    $stmt->execute();

                    $stmt = $conn->prepare("UPDATE compartments SET available_seats = available_seats - 1 WHERE train_name = ? AND class_type = ?");
                    $stmt->bind_param("ss", $trainName, $selectedClass);
                    $stmt->execute();

                    $bookingMessage = "Booking confirmed for $passengerName on $trainName ($selectedClass)! Seat: $seatNumber. " . process_payment($totalPrice);
                    if ($hasPet) $bookingMessage .= " Pet size: $petSize.";
                } elseif ($isTatkalTrain && $isTatkalOpen) {
                    // Tatkal booking
                    if ($tatkal_remaining > 0) {
                        $seatNumber = generate_seat_number();
                        $status = 'Confirmed';
                        $stmt = $conn->prepare("INSERT INTO bookings (train_name, class_type, passenger_name, age, gender, card_number, expiry, cvv, seat_number, status, payment_amount, has_pet, pet_size, is_tatkal) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $isTatkalFlag = 1;
                        $stmt->bind_param("sssisssisdisis", $trainName, $selectedClass, $passengerName, $age, $gender, $cardNumber, $expiry, $cvv, $seatNumber, $status, $totalPrice, $hasPet, $petSize, $isTatkalFlag);
                        $stmt->execute();

                        $stmt = $conn->prepare("UPDATE tatkal_trains SET tatkal_available = tatkal_available - 1 WHERE train_name = ?");
                        $stmt->bind_param("s", $trainName);
                        $stmt->execute();

                        $bookingMessage = "Tatkal booking confirmed for $passengerName on $trainName ($selectedClass)! Seat: $seatNumber. " . process_payment($totalPrice);
                        if ($hasPet) $bookingMessage .= " Pet size: $petSize.";
                    } else {
                        // Tatkal waitlist
                        $waitlistCount = ($waitlist_counts[$selectedClass] ?? 0) + 1;
                        $priority = $extraPayment == 400 ? $waitlistCount + 2 : ($extraPayment == 200 ? $waitlistCount + 1 : $waitlistCount);

                        $stmt = $conn->prepare("INSERT INTO bookings (train_name, class_type, passenger_name, age, gender, card_number, expiry, cvv, status, waitlist_priority, payment_amount, has_pet, pet_size, is_tatkal) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'TQWL', ?, ?, ?, ?, ?)");
                        $isTatkalFlag = 1;
                        $stmt->bind_param("sssisssisdisis", $trainName, $selectedClass, $passengerName, $age, $gender, $cardNumber, $expiry, $cvv, $priority, $totalPrice, $hasPet, $petSize, $isTatkalFlag);
                        $stmt->execute();

                        $bookingMessage = "Added to Tatkal waitlist for $passengerName on $trainName ($selectedClass). TQWL position: $priority. " . process_payment($totalPrice);
                        if ($hasPet) $bookingMessage .= " Pet size: $petSize.";
                    }
                } else {
                    $bookingMessage = "Error: No seats available for $trainName ($selectedClass). Non-Tatkal trains cannot be waitlisted.";
                }
                $conn->commit();

                // Simulate cancellation (for testing)
                if (rand(0, 1) && $isTatkalTrain && $isTatkalOpen) {
                    $stmt = $conn->prepare("DELETE FROM bookings WHERE train_name = ? AND class_type = ? AND status = 'Confirmed' AND is_tatkal = 1 LIMIT 1");
                    $stmt->bind_param("ss", $trainName, $selectedClass);
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) {
                        $bookingMessage .= " " . handle_cancellation($conn, $trainName, $selectedClass);
                        $stmt = $conn->prepare("UPDATE tatkal_trains SET tatkal_available = tatkal_available + 1 WHERE train_name = ?");
                        $stmt->bind_param("s", $trainName);
                        $stmt->execute();
                    }
                }
            } catch (Exception $e) {
                $conn->rollback();
                $bookingMessage = "Error: " . $e->getMessage();
            }
        }
    } else {
        $bookingMessage = "Error: Please fill all required fields" . ($hasPet ? " including pet size." : ".");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Train - <?php echo htmlspecialchars($trainName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; color: #333; }
        .container { width: 50%; margin: auto; background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0px 8px 20px rgba(0, 0, 0, 0.1); }
        h2 { color: #007bff; font-size: 24px; margin-bottom: 15px; }
        p { font-size: 16px; color: #666; margin: 5px 0; }
        label { display: block; margin: 10px 0 5px; font-weight: bold; font-size: 16px; color: #444; }
        input, select { width: 100%; padding: 8px; margin-bottom: 10px; border-radius: 4px; border: 1px solid #ddd; font-size: 16px; }
        button { background: linear-gradient(45deg, #007bff, #0056b3); color: white; padding: 10px; border: none; border-radius: 6px; cursor: pointer; width: 100%; font-weight: bold; font-size: 16px; }
        button:hover { background: linear-gradient(45deg, #0056b3, #003f7f); }
        .message { margin-top: 20px; padding: 10px; border-radius: 5px; font-size: 14px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
        #pet-size-field { display: none; }
        .form-group { margin-bottom: 15px; }
        .tatkal-option { color: #ff9800; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h2>Book Train: <?php echo htmlspecialchars($trainName); ?></h2>
    <p>Route: <?php echo htmlspecialchars($route); ?></p>
    <p>Departure: <?php echo htmlspecialchars($departure); ?> | Arrival: <?php echo htmlspecialchars($arrival); ?></p>

    <form method="POST" id="booking-form">
        <div class="form-group">
            <label for="class_type">Class Type:</label>
            <select name="class_type" id="class_type" required>
                <?php foreach ($compartments as $class => $data): ?>
                    <option value="<?php echo $class; ?>">
                        <?php 
                        echo $class . " (" . ($isTatkalOpen && $isTatkalTrain ? "Tatkal ₹" . number_format($classPrices[$trainName][$class]['tatkal'], 0) : "₹" . number_format($classPrices[$trainName][$class]['base'], 0)) . ")";
                        echo $data['available_seats'] > 0 ? " Available: {$data['available_seats']}" : ($isTatkalTrain && $isTatkalOpen ? " TQWL " . ($waitlist_counts[$class] ?? 0) : " Sold Out");
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="passenger_name">Passenger Name:</label>
            <input type="text" name="passenger_name" id="passenger_name" required>
        </div>

        <div class="form-group">
            <label for="age">Age:</label>
            <input type="number" name="age" id="age" min="1" max="120" required>
        </div>

        <div class="form-group">
            <label for="gender">Gender:</label>
            <select name="gender" id="gender" required>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <?php if ($isPetFriendly): ?>
            <div class="form-group">
                <label>Do you have a pet?</label>
                <input type="radio" name="has_pet" value="yes" id="pet-yes" onclick="togglePetField(true)"> Yes
                <input type="radio" name="has_pet" value="no" id="pet-no" onclick="togglePetField(false)" checked> No
            </div>
            <div class="form-group" id="pet-size-field">
                <label for="pet_size">Pet Size (e.g., Small, Medium, Large):</label>
                <input type="text" name="pet_size" id="pet_size">
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="card_number">Credit Card Number:</label>
            <input type="text" name="card_number" id="card_number" maxlength="16" pattern="\d{16}" required placeholder="16 digits">
        </div>

        <div class="form-group">
            <label for="expiry">Expiry Date (MM/YY):</label>
            <input type="text" name="expiry" id="expiry" pattern="\d{2}/\d{2}" required placeholder="MM/YY">
        </div>

        <div class="form-group">
            <label for="cvv">CVV:</label>
            <input type="text" name="cvv" id="cvv" maxlength="3" pattern="\d{3}" required placeholder="3 digits">
        </div>

        <?php if ($isTatkalTrain && $isTatkalOpen): ?>
            <div class="form-group tatkal-option">
                <label><input type="checkbox" name="use_tatkal" checked> Book under Tatkal (Higher fare)</label>
            </div>
            <div class="form-group tatkal-option">
                <label>Extra Payment for Tatkal Priority:</label>
                <select name="extra_payment" id="extra_payment">
                    <option value="0">None</option>
                    <option value="200">₹200 (Higher Priority)</option>
                    <option value="400">₹400 (Highest Priority)</option>
                </select>
            </div>
        <?php endif; ?>

        <button type="submit">Confirm Booking</button>
    </form>

    <?php if ($bookingMessage): ?>
        <div class="message <?php echo strpos($bookingMessage, 'Error') === false ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($bookingMessage); ?>
        </div>
    <?php endif; ?>
</div>

<script>
    function togglePetField(show) {
        const petSizeField = document.getElementById('pet-size-field');
        if (petSizeField) {
            petSizeField.style.display = show ? 'block' : 'none';
            document.getElementById('pet_size').required = show;

            const classSelect = document.getElementById('class_type');
            if (show) {
                for (let option of classSelect.options) {
                    option.disabled = option.value !== 'PET';
                }
                classSelect.value = 'PET';
            } else {
                for (let option of classSelect.options) {
                    option.disabled = false;
                }
            }
        }
    }
</script>
</body>
</html>
<?php $conn->close(); ?>