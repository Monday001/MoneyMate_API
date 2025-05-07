<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

header('Content-Type: application/json');

// Database connection
$conn = new mysqli('localhost', 'root', '', 'moneymate');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$phonenumber = $data['phonenumber'] ?? '';
$otp = $data['otp'] ?? '';  // OTP entered by the user

if (empty($phonenumber) || empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Phone number and OTP are required']);
    exit;
}

// Check if phone number exists in password_resets table
$stmt = $conn->prepare("SELECT * FROM password_resets WHERE phonenumber = ? AND otp = ?");
$stmt->bind_param("ss", $phonenumber, $otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // OTP is correct, proceed to password reset or another step
    echo json_encode(['success' => true, 'message' => 'OTP verified successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP or phone number']);
}

$conn->close();
?>
