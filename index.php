<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "", "train_booking");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch flights
$flights_query = "SELECT * FROM flights";
$flights_result = $conn->query($flights_query);
$flights = [];
while ($row = $flights_result->fetch_assoc()) {
    $flights[$row['flight_id']] = $row;
}

// Fetch flight classes
$classes_query = "SELECT flight_id, class_type, available_seats, price FROM flight_classes";
$classes_result = $conn->query($classes_query);
$flight_classes = [];
while ($row = $classes_result->fetch_assoc()) {
    $flight_classes[$row['flight_id']][$row['class_type']] = [
        'available_seats' => $row['available_seats'],
        'price' => $row['price']
    ];
}

// Fetch seats (including premium seats)
$seats_query = "SELECT flight_id, class_type, seat_number, seat_type, extra_cost, status, reserved_until, is_premium FROM flight_seats";
$seats_result = $conn->query($seats_query);
$seats = [];
while ($row = $seats_result->fetch_assoc()) {
    $seats[$row['flight_id']][$row['class_type']][$row['seat_number']] = [
        'seat_type' => $row['seat_type'],
        'extra_cost' => $row['extra_cost'],
        'status' => $row['status'],
        'reserved_until' => $row['reserved_until'],
        'is_premium' => $row['is_premium'] ?? 0
    ];
}

// Fetch available overhead bin slots for each flight
$bins_query = "SELECT flight_id, bin_slot, status FROM overhead_bins WHERE status = 'Available'";
$bins_result = $conn->query($bins_query);
$bins = [];
while ($row = $bins_result->fetch_assoc()) {
    $bins[$row['flight_id']][] = [
        'bin_slot' => $row['bin_slot'],
        'status' => $row['status']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Schedule - SkyVoyage</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same styles as before, no changes needed here */
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
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .header h1 {
            font-size: 2.5rem;
            color: #0288d1;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        }
        .flight-card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            padding: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .flight-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }
        .flight-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .flight-header h2 {
            font-size: 1.5rem;
            color: #0288d1;
            font-weight: 600;
        }
        .flight-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }
        .flight-info {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        .info-box {
            background: #f5f5f5;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            flex-shrink: 0;
            min-width: 150px;
            font-size: 0.9rem;
            color: #444;
        }
        .info-box strong {
            display: block;
            font-size: 1.1rem;
            color: #0288d1;
        }
        .seat-map {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 10px;
            border: 1px solid #ddd;
        }
        .seat-map label {
            font-weight: 600;
            margin-right: 10px;
            font-size: 1rem;
        }
        .seat-map select {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 0.9rem;
            margin-right: 10px;
            width: 200px;
        }
        .seat-grid, .bin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(45px, 1fr));
            gap: 8px;
            margin-top: 15px;
        }
        .seat, .bin-slot {
            padding: 10px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .seat.available {
            background: #4caf50;
            color: white;
        }
        .seat.booked {
            background: #f44336;
            color: white;
            cursor: not-allowed;
        }
        .seat.reserved {
            background: #ff9800;
            color: white;
            cursor: not-allowed;
        }
        .seat.premium {
            background: #9c27b0;
            color: white;
            border: 2px solid #ab47bc;
        }
        .seat.vip-service::after, .seat.enhanced-privacy::after {
            content: attr(data-label);
            position: absolute;
            top: -5px;
            right: -5px;
            background: #d81b60;
            color: white;
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 3px;
        }
        .seat.enhanced-privacy::after {
            background: #0288d1;
        }
        .seat.selected, .bin-slot.selected {
            border: 3px solid #0288d1;
            box-shadow: 0 0 8px rgba(2, 136, 209, 0.3);
        }
        .seat:hover.available {
            background: #43a047;
        }
        .bin-slot {
            background: #0288d1;
            color: white;
        }
        .bin-slot:hover {
            background: #0277bd;
        }
        .seat-legend {
            margin-top: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.8rem;
        }
        .legend-item {
            display: flex;
            align-items: center;
        }
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 5px;
            margin-right: 5px;
        }
        .option-button {
            background: linear-gradient(90deg, #ff9800, #ffb300);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }
        .option-button:hover {
            background: linear-gradient(90deg, #fb8c00, #ffa000);
        }
        .option-button.active {
            background: linear-gradient(90deg, #4caf50, #66bb6a);
        }
        .premium-section, .luggage-section {
            margin-top: 15px;
            padding: 15px;
            background: #f0f4f8;
            border-radius: 10px;
            border: 1px solid #ddd;
        }
        .premium-section h4, .luggage-section h4 {
            font-size: 1.2rem;
            color: #0288d1;
            margin-bottom: 10px;
        }
        .book-button {
            background: linear-gradient(90deg, #0288d1, #03a9f4);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        .book-button:hover {
            background: linear-gradient(90deg, #0277bd, #039be5);
        }
        /* Chatbot Styles unchanged */
        .chatbot-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 350px;
            height: 500px;
            background: linear-gradient(135deg, #87CEEB, #4682B4);
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 1000;
        }
        .chatbot-header {
            background: #0288d1;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: 600;
        }
        .chatbot-header .close-chatbot {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            font-size: 20px;
        }
        .chatbot-body {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background: url('https://www.transparenttextures.com/patterns/clouds.png');
            background-size: cover;
            position: relative;
        }
        .chatbot-body::before {
            content: '✈️';
            position: absolute;
            font-size: 30px;
            animation: fly 10s linear infinite;
        }
        @keyframes fly {
            0% { left: -30px; top: 20%; }
            100% { left: 100%; top: 80%; }
        }
        .chat-message {
            margin-bottom: 10px;
            padding: 8px 12px;
            border-radius: 10px;
            max-width: 80%;
            word-wrap: break-word;
        }
        .chat-message.bot {
            background: #ffffff;
            color: #333;
            margin-right: 20%;
        }
        .chat-message.user {
            background: #0288d1;
            color: white;
            margin-left: 20%;
            text-align: right;
        }
        .chatbot-input {
            display: flex;
            padding: 10px;
            background: #f8f9fa;
            border-top: 1px solid #ddd;
        }
        .chatbot-input input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-right: 10px;
        }
        .chatbot-input button {
            background: #0288d1;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        .chatbot-input button:hover {
            background: #0277bd;
        }
        .chatbot-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #0288d1;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            font-size: 24px;
        }
        .chatbot-toggle:hover {
            background: #0277bd;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-plane-departure"></i> SkyVoyage - Book Your Flight</h1>
    </div>
    <div id="flightList">
    <?php
    foreach ($flights as $flight_id => $flight) {
        $flightName = $flight['flight_name'];
        $source = explode(' to ', $flight['route'])[0];
        $destination = explode(' to ', $flight['route'])[1];

        echo "<div class='flight-card'>";
        echo "<div class='flight-header'>";
        echo "<h2><i class='fas fa-plane'></i> {$flight['flight_name']} - {$flight['route']}</h2>";
        echo "</div>";
        echo "<div class='flight-details'>";
        echo "<div>Departs: <strong>{$flight['depart_time']}</strong> | Arrives: <strong>{$flight['arrive_time']}</strong></div>";
        echo "<div>Duration: <strong>{$flight['duration']}</strong></div>";
        echo "</div>";
        echo "<div class='flight-info'>";
        if (isset($flight_classes[$flight_id]) && !empty($flight_classes[$flight_id])) {
            foreach ($flight_classes[$flight_id] as $class => $data) {
                $price = $data['price'];
                $available = $data['available_seats'];
                echo "<div class='info-box'><strong>$class</strong><br>₹" . number_format($price, 2) . "<br>";
                echo "Available: $available";
                echo "</div>";
            }
        } else {
            echo "<p>No class data available for $flightName.</p>";
        }
        echo "</div>";

        echo "<form id='booking-form-$flight_id' action='flight_details.php' method='POST'>";
        echo "<input type='hidden' name='flightId' value='$flight_id'>";
        echo "<input type='hidden' name='flightName' value='{$flight['flight_name']}'>";
        echo "<input type='hidden' name='route' value='{$flight['route']}'>";
        echo "<input type='hidden' name='departure' value='{$flight['depart_time']}'>";
        echo "<input type='hidden' name='arrival' value='{$flight['arrive_time']}'>";
        echo "<input type='hidden' id='selected-class-$flight_id' name='class' value=''>";
        echo "<input type='hidden' id='selected-seat-$flight_id' name='seat' value=''>";
        echo "<input type='hidden' id='selected-bin-$flight_id' name='bin_slot' value=''>";
        echo "<input type='hidden' id='premium-enabled-$flight_id' name='premium_enabled' value='0'>";
        echo "<input type='hidden' id='luggage-enabled-$flight_id' name='luggage_enabled' value='0'>";

        echo "<div class='seat-map'>";
        echo "<label>Select Class:</label>";
        echo "<select onchange='showSeats($flight_id, this.value)'>";
        echo "<option value=''>Select Class</option>";
        foreach ($flight_classes[$flight_id] as $class => $data) {
            echo "<option value='$class'>$class</option>";
        }
        echo "</select>";
        echo "<div id='seat-map-$flight_id' class='seat-map-content'></div>";

        echo "<div class='premium-section'>";
        echo "<button type='button' class='option-button' id='premium-button-$flight_id' onclick='togglePremiumSeats($flight_id)'>Unlock Premium Seats (₹2000)</button>";
        echo "<div id='premium-seats-$flight_id' class='premium-seats'>";
        echo "<h4>Premium Seats (VIP Service & Enhanced Privacy)</h4>";
        echo "<div id='premium-seat-grid-$flight_id' class='seat-grid'></div>";
        echo "</div>";
        echo "</div>";

        echo "<div class='luggage-section'>";
        echo "<button type='button' class='option-button' id='luggage-button-$flight_id' onclick='toggleLuggageOptions($flight_id)'>Reserve Luggage Space (₹300)</button>";
        echo "<div id='luggage-options-$flight_id' class='luggage-options'>";
        echo "<h4>Available Bin Slots</h4>";
        echo "<div id='bin-grid-$flight_id' class='bin-grid'>";
        if (isset($bins[$flight_id]) && !empty($bins[$flight_id])) {
            foreach ($bins[$flight_id] as $bin) {
                $binSlot = $bin['bin_slot'];
                echo "<div class='bin-slot' onclick='selectBin($flight_id, \"$binSlot\")'>$binSlot</div>";
            }
        } else {
            echo "<p>No bin slots available for this flight.</p>";
        }
        echo "</div>";
        echo "</div>";
        echo "</div>";

        echo "<div class='seat-legend'>";
        echo "<div class='legend-item'><span class='legend-color' style='background: #4caf50;'></span> Available</div>";
        echo "<div class='legend-item'><span class='legend-color' style='background: #f44336;'></span> Booked</div>";
        echo "<div class='legend-item'><span class='legend-color' style='background: #ff9800;'></span> Reserved</div>";
        echo "<div class='legend-item'><span class='legend-color' style='background: #9c27b0;'></span> Premium (₹2000)</div>";
        echo "<div class='legend-item'><span class='legend-color' style='background: #d81b60;'></span> VIP Service</div>";
        echo "<div class='legend-item'><span class='legend-color' style='background: #0288d1;'></span> Enhanced Privacy</div>";
        echo "</div>";
        echo "</div>";

        echo "<button type='submit' class='book-button' onclick='return validateForm($flight_id)'>Book Now</button>";
        echo "</form>";
        echo "</div>";
    }
    $conn->close();
    ?>
    </div>
</div>

<!-- Chatbot Interface -->
<div class="chatbot-toggle" onclick="toggleChatbot()">✈️</div>
<div class="chatbot-container" id="chatbot">
    <div class="chatbot-header">
        Sky Assistant
        <span class="close-chatbot" onclick="toggleChatbot()">×</span>
    </div>
    <div class="chatbot-body" id="chatbot-body">
        <div class="chat-message bot">Hello! I’m Sky Assistant. How can I help you today? ✈️</div>
    </div>
    <div class="chatbot-input">
        <input type='text' id='chatbot-input' placeholder='Type your message...' onkeypress='if(event.keyCode==13) sendMessage()'>
        <button onclick='sendMessage()'>Send</button>
    </div>
</div>

<script>
    function showSeats(flightId, classType) {
        if (!classType) {
            document.getElementById(`seat-map-${flightId}`).innerHTML = '';
            document.getElementById(`premium-seat-grid-${flightId}`).innerHTML = '';
            return;
        }

        document.getElementById(`selected-class-${flightId}`).value = classType;

        fetch(`get_seats.php?flightId=${flightId}&classType=${classType}`)
            .then(response => response.json())
            .then(seats => {
                const seatMap = document.getElementById(`seat-map-${flightId}`);
                const premiumSeatGrid = document.getElementById(`premium-seat-grid-${flightId}`);
                seatMap.setAttribute('data-selected-class', classType);

                let standardHtml = '<h4>Standard Seats</h4><div class="seat-grid">';
                let premiumHtml = '';

                for (const seatNumber in seats) {
                    const seat = seats[seatNumber];
                    const isAvailable = seat.status === 'Available' && (!seat.reserved_until || new Date(seat.reserved_until) < new Date());
                    const isReserved = seat.status === 'Available' && seat.reserved_until && new Date(seat.reserved_until) >= new Date();
                    const seatClass = isAvailable ? 'available' : (isReserved ? 'reserved' : 'booked');
                    const isPremium = seat.is_premium == 1;
                    const premiumClass = isPremium ? 'premium' : '';
                    const extraCost = seat.extra_cost > 0 ? ` (₹${seat.extra_cost})` : '';

                    let luxuryFeature = '';
                    if (isPremium) {
                        if (['1A', '1B', '1C'].includes(seatNumber)) {
                            luxuryFeature = 'vip-service';
                        } else if (['3A', '3B', '3C'].includes(seatNumber)) {
                            luxuryFeature = 'enhanced-privacy';
                        }
                    }

                    if (isPremium) {
                        premiumHtml += `<div class='seat ${seatClass} ${premiumClass} ${luxuryFeature}' data-label='${luxuryFeature === 'vip-service' ? 'VIP' : 'EP'}' onclick='selectSeat(${flightId}, "${seatNumber}", ${isAvailable})'>${seatNumber}${extraCost}</div>`;
                    } else {
                        standardHtml += `<div class='seat ${seatClass}' onclick='selectSeat(${flightId}, "${seatNumber}", ${isAvailable})'>${seatNumber}${extraCost}</div>`;
                    }
                }
                standardHtml += '</div>';
                seatMap.innerHTML = standardHtml;
                premiumSeatGrid.innerHTML = premiumHtml;
            })
            .catch(error => console.error('Error fetching seats:', error));
    }

    function selectSeat(flightId, seatNumber, isAvailable) {
        if (!isAvailable) {
            alert('This seat is already booked or reserved.');
            return;
        }
        document.getElementById(`selected-seat-${flightId}`).value = seatNumber;
        const seats = document.querySelectorAll(`#seat-map-${flightId} .seat, #premium-seat-grid-${flightId} .seat`);
        seats.forEach(seat => seat.classList.remove('selected'));
        const selectedSeat = Array.from(seats).find(seat => seat.textContent.includes(seatNumber));
        if (selectedSeat) selectedSeat.classList.add('selected');
    }

    function togglePremiumSeats(flightId) {
        const premiumButton = document.getElementById(`premium-button-${flightId}`);
        const premiumSeatsDiv = document.getElementById(`premium-seats-${flightId}`);
        const premiumEnabledInput = document.getElementById(`premium-enabled-${flightId}`);
        const isActive = premiumButton.classList.toggle('active');

        premiumSeatsDiv.style.display = isActive ? 'block' : 'none';
        premiumEnabledInput.value = isActive ? '1' : '0';
        if (!isActive) {
            document.getElementById(`selected-seat-${flightId}`).value = '';
            document.querySelectorAll(`#premium-seat-grid-${flightId} .seat`).forEach(seat => seat.classList.remove('selected'));
        }
    }

    function toggleLuggageOptions(flightId) {
        const luggageButton = document.getElementById(`luggage-button-${flightId}`);
        const luggageOptionsDiv = document.getElementById(`luggage-options-${flightId}`);
        const luggageEnabledInput = document.getElementById(`luggage-enabled-${flightId}`);
        const isActive = luggageButton.classList.toggle('active');

        luggageOptionsDiv.style.display = isActive ? 'block' : 'none';
        luggageEnabledInput.value = isActive ? '1' : '0';
        if (!isActive) {
            document.getElementById(`selected-bin-${flightId}`).value = '';
            document.querySelectorAll(`#bin-grid-${flightId} .bin-slot`).forEach(slot => slot.classList.remove('selected'));
        }
    }

    function selectBin(flightId, binSlot) {
        document.getElementById(`selected-bin-${flightId}`).value = binSlot;
        const binSlots = document.querySelectorAll(`#bin-grid-${flightId} .bin-slot`);
        binSlots.forEach(slot => slot.classList.remove('selected'));
        const selectedBin = Array.from(binSlots).find(slot => slot.textContent === binSlot);
        if (selectedBin) selectedBin.classList.add('selected');
    }

    function validateForm(flightId) {
        const selectedClass = document.getElementById(`selected-class-${flightId}`).value;
        const selectedSeat = document.getElementById(`selected-seat-${flightId}`).value;
        if (!selectedClass || !selectedSeat) {
            alert('Please select a class and seat before booking.');
            return false;
        }
        return true;
    }

    function toggleChatbot() {
        const chatbot = document.getElementById('chatbot');
        chatbot.style.display = chatbot.style.display === 'flex' ? 'none' : 'flex';
    }

    function sendMessage() {
        const input = document.getElementById('chatbot-input');
        const message = input.value.trim();
        if (!message) return;

        const chatBody = document.getElementById('chatbot-body');
        chatBody.innerHTML += `<div class="chat-message user">${message}</div>`;
        input.value = '';
        chatBody.scrollTop = chatBody.scrollHeight;

        setTimeout(() => {
            let botResponse = '';
            const lowerMessage = message.toLowerCase();
            if (lowerMessage.includes('flight status')) {
                botResponse = 'Please provide your flight number (e.g., AI123) to check status.';
            } else if (lowerMessage.includes('seat')) {
                botResponse = 'Select a seat from the seat map. Premium seats (₹2000) are available—unlock them!';
            } else if (lowerMessage.includes('luggage') || lowerMessage.includes('bin')) {
                botResponse = 'Reserve bin space for ₹300 by clicking "Reserve Luggage Space".';
            } else if (lowerMessage.includes('recommend')) {
                botResponse = 'I recommend premium seats (₹2000) for comfort and a bin slot for luggage.';
            } else {
                botResponse = 'Ask about flight status, seats (premium ₹2000), or luggage. How can I assist? ✈️';
            }
            chatBody.innerHTML += `<div class="chat-message bot">${botResponse}</div>`;
            chatBody.scrollTop = chatBody.scrollHeight;
        }, 1000);
    }
</script>
</body>
</html> 