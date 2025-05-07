<?php
header("Content-Type: application/json");

// Read and decode raw JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "moneymate";

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Connection failed"]);
    exit();
}

// Check if required fields exist in JSON
if (isset($data['phonenumber']) && isset($data['password'])) {
    $phone = $conn->real_escape_string($data['phonenumber']);
    $newPassword = $conn->real_escape_string($data['password']);

    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update query
    $sql = "UPDATE users SET password='$hashedPassword' WHERE phonenumber='$phone'";
    $result = $conn->query($sql);

    if ($conn->affected_rows > 0) {
        echo json_encode(["success" => true, "message" => "Password reset successful"]);
    } else {
        echo json_encode(["success" => false, "message" => "Phone number not found or password update failed"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Missing phone number or new password"]);
}

$conn->close();
?>
