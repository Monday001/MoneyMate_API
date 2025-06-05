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

// Lender login: private_key + password
if (!empty($data['private_key']) && !empty($data['password'])) {
    $private_key = $conn->real_escape_string($data['private_key']);
    $password = $data['password'];

    $sql = "SELECT * FROM lenders WHERE private_key = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $private_key);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $lender = $result->fetch_assoc();

        if (password_verify($password, $lender['password'])) {
            echo json_encode([
                "success" => true,
                "message" => "Lender login successful",
                "user_type" => "lender",
                "lender_id" => $lender['id'],
                "username" => $lender['username'] ?? null
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Invalid password for lender"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Lender not found"]);
    }
    exit();
}

// User login: username + password
if (!empty($data['username']) && !empty($data['password'])) {
    $username = $conn->real_escape_string($data['username']);
    $password = $data['password'];

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            echo json_encode([
                "success" => true,
                "message" => "User login successful",
                "user_type" => "user",
                "borrower_id" => $user['id'],
                "username" => $user['username'] ?? null
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Invalid password for user"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "User not found"]);
    }
    exit();
}

// If neither login method matched
echo json_encode(["success" => false, "message" => "Invalid login credentials"]);
