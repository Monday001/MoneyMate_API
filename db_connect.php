<?php
// Database connection parameters
$host = "localhost";
$user = "root";
$pass = "";
$db   = "moneymate";

// Create the connection
$conn = new mysqli($host, $user, $pass, $db);

// Check the connection
if ($conn->connect_error) {
    // Return a JSON error if the connection fails
    echo json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]);
    exit();
}
?>

