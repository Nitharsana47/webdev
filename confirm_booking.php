<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $train_id = $_POST['train_id'];
    $passenger_name = $_POST['passenger_name'];

    $stmt = $conn->prepare("INSERT INTO bookings (train_id, passenger_name) VALUES (?, ?)");
    $stmt->bind_param("is", $train_id, $passenger_name);

    if ($stmt->execute()) {
        echo "Booking successful!";
    } else {
        echo "Error booking train.";
    }
} else {
    echo "Invalid request.";
}
?>
