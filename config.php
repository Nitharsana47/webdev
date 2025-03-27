<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "train_schedule"; // Updated database name

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
