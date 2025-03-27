<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "", "train_booking");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$flightId = $_GET['flightId'];
$classType = $_GET['classType'];

$query = "SELECT seat_number, seat_type, extra_cost, status, reserved_until, is_premium 
          FROM flight_seats 
          WHERE flight_id = ? AND class_type = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $flightId, $classType);
$stmt->execute();
$result = $stmt->get_result();

$seats = [];
while ($row = $result->fetch_assoc()) {
    $seats[$row['seat_number']] = [
        'seat_type' => $row['seat_type'],
        'extra_cost' => $row['extra_cost'],
        'status' => $row['status'],
        'reserved_until' => $row['reserved_until'],
        'is_premium' => $row['is_premium']
    ];
}

echo json_encode($seats);

$stmt->close();
$conn->close();
?>