<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "", "train_booking");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch compartments data
$compartments_query = "SELECT train_name, class_type, available_seats, is_pet_compartment, pet_capacity FROM compartments";
$compartments_result = $conn->query($compartments_query);
if (!$compartments_result) {
    die("Compartments query failed: " . $conn->error);
}
$compartments = [];
while ($row = $compartments_result->fetch_assoc()) {
    $train_name = $row['train_name'];
    if (!isset($compartments[$train_name])) {
        $compartments[$train_name] = [];
    }
    if ($row['is_pet_compartment']) {
        $compartments[$train_name]['petFriendly'] = true;
        $compartments[$train_name]['PET'] = $row['pet_capacity'];
    } else {
        $compartments[$train_name][$row['class_type']] = $row['available_seats'];
    }
}

// Fetch Tatkal trains
$tatkal_query = "SELECT train_name, tatkal_available FROM tatkal_trains";
$tatkal_result = $conn->query($tatkal_query);
$tatkal_trains = [];
while ($row = $tatkal_result->fetch_assoc()) {
    $tatkal_trains[$row['train_name']] = $row['tatkal_available'];
}

// Fetch waitlist counts for Tatkal trains
$waitlist_query = "SELECT train_name, class_type, COUNT(*) as waitlist_count 
                   FROM bookings 
                   WHERE status = 'TQWL' 
                   GROUP BY train_name, class_type";
$waitlist_result = $conn->query($waitlist_query);
$waitlist_counts = [];
while ($row = $waitlist_result->fetch_assoc()) {
    $waitlist_counts[$row['train_name']][$row['class_type']] = $row['waitlist_count'];
}

// Define station tips with platform-specific hints
$stations = [
    'Dindigul' => [
        'Platform 1' => 'Auto stand to the right, bus stop 50m ahead.',
        'Platform 2' => 'Food stalls to the left, taxi stand near exit.'
    ],
    'Chennai' => [
        'Platform 1' => 'Taxi stand straight ahead, metro 100m right.',
        'Platform 3' => 'Bus stop to the left, auto stand near gate.'
    ],
    'Trichy' => [
        'Platform 1' => 'Auto stand to the right, bus stop across the road.',
        'Platform 2' => 'Food court to the left, taxi stand 20m ahead.'
    ],
    'Madurai' => [
        'Platform 1' => 'Rickshaws outside, bus stop 30m right.',
        'Platform 3' => 'Auto stand to the left, shops nearby.'
    ],
    'Nellore' => [
        'Platform 1' => 'Auto stand to the right, no bus stop nearby.',
        'Platform 2' => 'Taxi stand straight ahead, waiting room near exit.'
    ],
    'Vijayawada' => [
        'Platform 1' => 'Buses to the left, auto stand near main gate.',
        'Platform 4' => 'Taxi stand to the right, food stalls ahead.'
    ],
    'Delhi' => [
        'Platform 1' => 'Metro access straight ahead, auto stand to the right.',
        'Platform 5' => 'Bus stop 50m left, taxi stand near exit.'
    ],
    'Coimbatore' => [
        'Platform 1' => 'Taxi stand to the right, bus stop 100m ahead.',
        'Platform 2' => 'Auto stand to the left, eateries nearby.'
    ],
    'Bangalore' => [
        'Platform 1' => 'Auto stand to the left, bus stop 20m right.',
        'Platform 3' => 'Taxi stand straight ahead, metro 50m left.'
    ]
];

$classPrices = [
    'Cholan Express' => ['3A' => '₹980', '2A' => '₹1400', '1A' => '₹2200', 'SL' => '₹350', 'Seater' => '₹300', 'PET' => '₹500'],
    'Vande Bharat Express' => ['CC' => '₹1750', 'EC' => '₹3200', 'PET' => '₹600'],
    'Rajdhani Express' => ['1A' => '₹5000', '2A' => '₹3200'],
    'Pandian Express' => ['3A' => '₹1100', '2A' => '₹1600'],
    'Tejas Express' => ['CC' => '₹1800', 'EC' => '₹3000'],
    'Shatabdi Express' => ['CC' => '₹1500', 'EC' => '₹2500']
];

// Train data with correct intermediate stops
$trains = [
    ['name' => 'Cholan Express', 'route' => 'Dindigul to Chennai', 'depart' => '8:00 AM', 'arrive' => '12:00 PM', 'duration' => '4h 00m', 'intermediates' => ['Trichy']],
    ['name' => 'Vande Bharat Express', 'route' => 'Madurai to Chennai', 'depart' => '6:00 AM', 'arrive' => '2:00 PM', 'duration' => '8h 00m', 'intermediates' => ['Trichy', 'Nellore']],
    ['name' => 'Rajdhani Express', 'route' => 'Chennai to Delhi', 'depart' => '6:00 AM', 'arrive' => '8:00 AM (Next Day)', 'duration' => '26h 00m', 'intermediates' => ['Vijayawada', 'Nellore']],
    ['name' => 'Pandian Express', 'route' => 'Madurai to Chennai', 'depart' => '9:00 PM', 'arrive' => '6:00 AM', 'duration' => '9h 00m', 'intermediates' => ['Trichy']],
    ['name' => 'Tejas Express', 'route' => 'Chennai to Coimbatore', 'depart' => '6:00 AM', 'arrive' => '12:00 PM', 'duration' => '6h 00m', 'intermediates' => ['Trichy']],
    ['name' => 'Shatabdi Express', 'route' => 'Bangalore to Chennai', 'depart' => '6:00 AM', 'arrive' => '11:00 AM', 'duration' => '5h 00m', 'intermediates' => []]
];

// Unique stations for filters
$uniqueStations = array_unique(array_merge(
    array_map(fn($train) => explode(' to ', $train['route'])[0], $trains),
    array_map(fn($train) => explode(' to ', $train['route'])[1], $trains)
));
sort($uniqueStations);

// Simulate Tatkal availability
$currentDate = new DateTime('2025-03-26');
$isTatkalOpen = false; // Replace with real logic if departure dates are added
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Train Schedule</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; color: #333; }
        .container { width: 85%; margin: auto; }
        .train-card { background: #ffffff; border-radius: 12px; box-shadow: 0px 8px 20px rgba(0, 0, 0, 0.1); padding: 20px; margin-bottom: 25px; }
        .train-header { display: flex; justify-content: space-between; align-items: center; font-size: 22px; font-weight: 600; color: #007bff; margin-bottom: 12px; }
        .train-details { display: flex; justify-content: space-between; font-size: 16px; color: #666; padding-bottom: 10px; border-bottom: 1px solid #ddd; }
        .train-info { display: flex; gap: 15px; overflow-x: auto; white-space: nowrap; padding-bottom: 10px; }
        .train-info::-webkit-scrollbar { height: 8px; }
        .train-info::-webkit-scrollbar-thumb { background: #888; border-radius: 4px; }
        .info-box { padding: 15px; border-radius: 8px; text-align: center; background: #f9f9f9; flex-shrink: 0; min-width: 220px; height: 90px; font-size: 16px; font-weight: 500; color: #444; }
        .info-box strong { font-size: 18px; color: #000; }
        .tatkal { background: #ff9800; padding: 6px 10px; border-radius: 5px; font-weight: 600; font-size: 14px; color: white; }
        .button { background: linear-gradient(45deg, #007bff, #0056b3); color: white; padding: 10px 15px; border: none; cursor: pointer; border-radius: 6px; margin-top: 12px; display: block; width: 160px; text-align: center; font-weight: bold; }
        .button:hover { background: linear-gradient(45deg, #0056b3, #003f7f); }
        .station-details { margin-top: 12px; font-size: 16px; color: #555; }
        .station-info { display: flex; justify-content: space-between; margin-top: 6px; padding: 10px 0; border-top: 1px solid #ddd; font-weight: 500; }
        .pet-friendly { background: #28a745; padding: 6px 10px; border-radius: 5px; font-weight: 600; font-size: 14px; color: white; margin-left: 10px; }
        .feature-section { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0px 8px 20px rgba(0, 0, 0, 0.1); }
        .feature-section h2 { color: #007bff; font-size: 20px; margin-bottom: 15px; }
        form { display: flex; flex-direction: column; gap: 10px; }
        label { font-weight: bold; }
        input, select, button { padding: 8px; font-size: 16px; border-radius: 4px; border: 1px solid #ddd; }
        .station-tips { margin-top: 10px; }
        .station-tips button { background: #28a745; padding: 6px 12px; width: auto; display: inline-block; }
        .station-tips button:hover { background: #218838; }
        .tips-content { display: none; padding: 10px; background: #f9f9f9; border-radius: 4px; margin-top: 5px; font-size: 14px; color: #444; }
        .tips-content.active { display: block; }
        .pet-compartment { background: #28a745; color: white; }
        .filter-section { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0px 8px 20px rgba(0, 0, 0, 0.1); margin-bottom: 25px; display: flex; gap: 15px; align-items: center; }
        .filter-section input, .filter-section select { width: 200px; }
        .filter-section button { width: auto; padding: 10px 20px; }
        .waitlist-info { font-size: 12px; color: #ff9800; margin-top: 5px; }

        /* Attractive Chatbot Styles */
        #chatbot-container { 
            position: fixed; bottom: 80px; right: 20px; width: 350px; background: linear-gradient(135deg, #fff, #f0f8ff); 
            border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); z-index: 1000; display: none; overflow: hidden; 
            border: 2px solid #007bff; animation: slideIn 0.5s ease-out; 
        }
        #chatbot-header { 
            background: linear-gradient(45deg, #007bff, #00c4ff); color: white; padding: 15px; font-size: 18px; 
            font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center; 
            border-radius: 20px 20px 0 0; 
        }
        #chatbot-header span { font-size: 20px; }
        #chatbot-body { 
            max-height: 400px; overflow-y: auto; padding: 20px; background: #fff; border-radius: 0 0 20px 20px; 
        }
        #chatbot-input { 
            display: flex; padding: 15px; background: #f9f9f9; border-top: 1px solid #ddd; 
        }
        #chatbot-input input { 
            flex-grow: 1; border: 1px solid #007bff; outline: none; padding: 10px; border-radius: 15px; 
            font-size: 14px; transition: border-color 0.3s; 
        }
        #chatbot-input input:focus { border-color: #00c4ff; }
        #chatbot-input button { 
            background: linear-gradient(45deg, #007bff, #00c4ff); color: white; border: none; padding: 10px 20px; 
            border-radius: 15px; margin-left: 10px; cursor: pointer; font-weight: bold; transition: transform 0.2s; 
        }
        #chatbot-input button:hover { transform: scale(1.05); background: linear-gradient(45deg, #0056b3, #007bff); }
        .chat-message { 
            margin: 10px 0; padding: 12px; border-radius: 15px; font-size: 14px; max-width: 80%; word-wrap: break-word; 
            animation: fadeIn 0.3s ease-in; 
        }
        .user-message { 
            background: linear-gradient(45deg, #007bff, #00c4ff); color: white; text-align: right; margin-left: auto; 
        }
        .bot-message { 
            background: #e9f7ff; color: #333; text-align: left; margin-right: auto; border: 1px solid #007bff; 
        }
        #chatbot-toggle { 
            position: fixed; bottom: 20px; right: 20px; background: linear-gradient(45deg, #007bff, #00c4ff); 
            color: white; padding: 15px; border-radius: 50%; cursor: pointer; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); 
            font-size: 24px; transition: transform 0.3s; 
        }
        #chatbot-toggle:hover { transform: scale(1.1); }
        @keyframes slideIn { from { transform: translateY(100px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>
<div class="container">
    <div class="filter-section">
        <input type="text" id="searchTrain" placeholder="Search by train name...">
        <select id="filterFrom">
            <option value="">From (All)</option>
            <?php foreach ($uniqueStations as $station) { ?>
                <option value="<?php echo $station; ?>"><?php echo $station; ?></option>
            <?php } ?>
        </select>
        <select id="filterTo">
            <option value="">To (All)</option>
            <?php foreach ($uniqueStations as $station) { ?>
                <option value="<?php echo $station; ?>"><?php echo $station; ?></option>
            <?php } ?>
        </select>
        <button onclick="filterTrains()">Filter</button>
    </div>

    <div id="trainList">
    <?php
    foreach ($trains as $train) {
        $trainName = $train['name'];
        $source = explode(' to ', $train['route'])[0];
        $destination = explode(' to ', $train['route'])[1];
        $intermediates = $train['intermediates'];
        $isTatkalTrain = isset($tatkal_trains[$trainName]);

        echo "<div class='train-card' data-train='$trainName' data-source='$source' data-destination='$destination'>";
        echo "<div class='train-header'>";
        echo "<div>{$train['name']} - {$train['route']}</div>";
        if ($isTatkalTrain) {
            echo "<div class='tatkal'>TATKAL</div>";
        }
        if (isset($compartments[$trainName]['petFriendly']) && $compartments[$trainName]['petFriendly']) {
            echo "<div class='pet-friendly'>Pet Compartment Available</div>";
        }
        echo "</div>";
        echo "<div class='train-details'>";
        echo "<div>Departs: <strong>{$train['depart']}</strong> | Arrives: <strong>{$train['arrive']}</strong></div>";
        echo "<div>Duration: <strong>{$train['duration']}</strong></div>";
        echo "</div>";
        echo "<div class='train-info'>";
        if (isset($compartments[$trainName]) && !empty($compartments[$trainName])) {
            foreach ($compartments[$trainName] as $class => $data) {
                if ($class !== 'petFriendly') {
                    $price = $classPrices[$trainName][$class] ?? 'N/A';
                    $boxClass = $class === 'PET' ? 'pet-compartment' : '';
                    $available = $data;
                    $waitlistCount = $waitlist_counts[$trainName][$class] ?? 0;
                    echo "<div class='info-box $boxClass'><strong>$class</strong><br>$price<br>";
                    if ($available > 0) {
                        echo "Available: $available";
                    } elseif ($isTatkalTrain && $isTatkalOpen) {
                        echo "Tatkal TQWL $waitlistCount";
                    } else {
                        echo "Sold Out";
                    }
                    echo "</div>";
                    if ($available <= 0 && $isTatkalTrain && $isTatkalOpen) {
                        echo "<div class='waitlist-info'>Tatkal Waitlist: TQWL $waitlistCount</div>";
                    }
                }
            }
        } else {
            echo "<p>No compartment data available for $trainName.</p>";
        }
        echo "</div>";
        echo "<div class='station-details'>";
        echo "<div><strong>Stations:</strong></div>";
        echo "<div class='station-info'><div>Source: $source</div><div>Destination: $destination</div></div>";
        echo "<div class='station-tips'>";
        echo "<button class='toggle-tips'>Station Tips</button>";
        echo "<div class='tips-content'>";
        // Source station tips
        echo "<p><strong>$source:</strong></p>";
        foreach ($stations[$source] as $platform => $tips) {
            echo "<p>$platform: $tips</p>";
        }
        // Intermediate stops tips
        foreach ($intermediates as $intermediate) {
            echo "<p><strong>$intermediate:</strong></p>";
            foreach ($stations[$intermediate] as $platform => $tips) {
                echo "<p>$platform: $tips</p>";
            }
        }
        // Destination station tips
        echo "<p><strong>$destination:</strong></p>";
        foreach ($stations[$destination] as $platform => $tips) {
            echo "<p>$platform: $tips</p>";
        }
        echo "</div>";
        echo "</div>";
        echo "</div>";
        echo "<button class='button' onclick=\"openBookingPage('{$train['name']}', '{$train['route']}', '{$train['depart']}', '{$train['arrive']}')\">Book now</button>";
        echo "</div>";
    }
    $conn->close();
    ?>
    </div>
</div>

<!-- Super Attractive Chatbot -->
<div id="chatbot-container">
    <div id="chatbot-header" onclick="toggleChatbot()">🚂 TrainBuddy <span>×</span></div>
    <div id="chatbot-body"></div>
    <div id="chatbot-input">
        <input type="text" id="chatbot-message" placeholder="Ask me anything!!">
        <button onclick="sendMessage()">Send</button>
    </div>
</div>
<div id="chatbot-toggle" onclick="toggleChatbot()">🚂</div>

<script>
    function openBookingPage(trainName, route, departure, arrival) {
        const url = `book.php?trainName=${encodeURIComponent(trainName)}&route=${encodeURIComponent(route)}&departure=${encodeURIComponent(departure)}&arrival=${encodeURIComponent(arrival)}`;
        window.location.href = url;
    }

    function filterTrains() {
        const search = document.getElementById('searchTrain').value.toLowerCase();
        const from = document.getElementById('filterFrom').value;
        const to = document.getElementById('filterTo').value;
        const trainCards = document.querySelectorAll('.train-card');

        trainCards.forEach(card => {
            const trainName = card.getAttribute('data-train').toLowerCase();
            const source = card.getAttribute('data-source');
            const destination = card.getAttribute('data-destination');
            const matchesSearch = search === '' || trainName.includes(search);
            const matchesFrom = from === '' || source === from;
            const matchesTo = to === '' || destination === to;
            card.style.display = (matchesSearch && matchesFrom && matchesTo) ? 'block' : 'none';
        });
    }

    window.onload = function() {
        document.querySelectorAll('.toggle-tips').forEach(button => {
            button.addEventListener('click', function() {
                const tipsContent = this.nextElementSibling;
                tipsContent.classList.toggle('active');
                this.textContent = tipsContent.classList.contains('active') ? 'Hide Tips' : 'Station Tips';
            });
        });

        document.getElementById('searchTrain').addEventListener('input', filterTrains);
        initializeChatbot();
    };

    function initializeChatbot() {
        const chatbotBody = document.getElementById('chatbot-body');
        const chatbotInput = document.getElementById('chatbot-message');
        if (!chatbotBody || !chatbotInput) return;

        function toggleChatbot() {
            const container = document.getElementById('chatbot-container');
            const toggle = document.getElementById('chatbot-toggle');
            if (container.style.display === 'block') {
                container.style.display = 'none';
                toggle.style.display = 'block';
            } else {
                container.style.display = 'block';
                toggle.style.display = 'none';
                if (!chatbotBody.innerHTML) {
                    addBotMessage("Hey there, daa! I’m TrainBuddy 🚂! Ask me about trains, pets, or bookings!");
                }
            }
        }

        function addBotMessage(message) {
            const div = document.createElement('div');
            div.className = 'chat-message bot-message';
            div.textContent = message;
            chatbotBody.appendChild(div);
            chatbotBody.scrollTop = chatbotBody.scrollHeight;
        }

        function addUserMessage(message) {
            const div = document.createElement('div');
            div.className = 'chat-message user-message';
            div.textContent = message;
            chatbotBody.appendChild(div);
            chatbotBody.scrollTop = chatbotBody.scrollHeight;
        }

        function sendMessage() {
            const message = chatbotInput.value.trim();
            if (!message) return;
            addUserMessage(message);
            chatbotInput.value = '';

            const lowerMessage = message.toLowerCase();
            let response = "Oops, I didn’t catch that! Try asking about trains, pets, or bookings, daa!";
            <?php foreach ($trains as $train) { ?>
                if (lowerMessage.includes('<?php echo strtolower($train['name']); ?>')) {
                    response = "🚂 <?php echo $train['name']; ?> runs from <?php echo $train['route']; ?>. Departs at <?php echo $train['depart']; ?>, arrives at <?php echo $train['arrive']; ?>. Duration: <?php echo $train['duration']; ?>!";
                    <?php if (isset($compartments[$train['name']]['petFriendly']) && $compartments[$train['name']]['petFriendly']) { ?>
                        response += " It’s pet-friendly too! 🐾";
                    <?php } ?>
                    <?php if (isset($tatkal_trains[$train['name']])) { ?>
                        response += " Tatkal available—book fast! ⚡";
                    <?php } ?>
                }
            <?php } ?>
            if (lowerMessage.includes('pet')) response = "🐾 Pet-friendly trains? Cholan Express and Vande Bharat Express have PET compartments! Check booking for details!";
            else if (lowerMessage.includes('book')) response = "📅 Click 'Book now', fill your details, and you’re set! Easy peasy!";
            else if (lowerMessage.includes('tatkal')) response = "⚡ Tatkal’s for Cholan, Rajdhani, and Pandian Express—higher fares, opens 1 day before!";
            else if (lowerMessage.includes('hi') || lowerMessage.includes('hello')) response = "Hey, daa! 🚂 TrainBuddy’s here to help—what’s up?";

            setTimeout(() => addBotMessage(response), 500);
        }

        chatbotInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage();
        });
        document.getElementById('chatbot-toggle').addEventListener('click', toggleChatbot);
        document.querySelector('#chatbot-input button').addEventListener('click', sendMessage);
    }
</script>
</body>
</html>