<?php
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "train_booking");
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed']));
}

$flightId = $_POST['flightId'] ?? 0;
$classType = $_POST['classType'] ?? '';
$seatNumber = $_POST['seatNumber'] ?? '';

$conn->begin_transaction();
try {
    // Updated table name to flight_seats
    $stmt = $conn->prepare("SELECT status, reserved_until FROM flight_seats WHERE flight_id = ? AND class_type = ? AND seat_number = ? FOR UPDATE");
    $stmt->bind_param("iss", $flightId, $classType, $seatNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $seat = $result->fetch_assoc();

    if (!$seat) {
        throw new Exception('Seat not found');
    }

    $currentTime = new DateTime();
    $reservedUntil = $seat['reserved_until'] ? new DateTime($seat['reserved_until']) : null;
    if ($seat['status'] !== 'Available' || ($reservedUntil && $reservedUntil > $currentTime)) {
        throw new Exception('Seat is already booked or reserved');
    }

    $newReservedUntil = (new DateTime())->modify('+5 minutes')->format('Y-m-d H:i:s');
    // Updated table name to flight_seats
    $stmt = $conn->prepare("UPDATE flight_seats SET reserved_until = ? WHERE flight_id = ? AND class_type = ? AND seat_number = ?");
    $stmt->bind_param("siss", $newReservedUntil, $flightId, $classType, $seatNumber);
    $stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>