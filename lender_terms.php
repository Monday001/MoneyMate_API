<?php
header("Content-Type: application/json");

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

// Get raw JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate input data
if (empty($data['lender_id']) || empty($data['terms']) || !is_array($data['terms']) || count($data['terms']) != 5) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid input data. Please provide lender_id and exactly 5 terms."
    ]);
    exit();
}

// Extract lender ID and terms
$lender_id = $conn->real_escape_string($data['lender_id']);
$terms = $data['terms']; // Expected to be an array with 5 terms

// Prepare SQL query to insert terms into lender_terms table
$sql = "INSERT INTO lender_terms (lender_id, term_1, term_2, term_3, term_4, term_5) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

// Bind parameters
$stmt->bind_param("isssss", $lender_id, $terms[0], $terms[1], $terms[2], $terms[3], $terms[4]);

// Execute query
if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Lender terms saved successfully"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to save lender terms"
    ]);
}

$stmt->close();
$conn->close();
?>
