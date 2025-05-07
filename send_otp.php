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
$email = $data['email'] ?? '';

if (empty($phonenumber) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Phone number and email are required']);
    exit;
}

// Check if phone number exists
$stmt = $conn->prepare("SELECT * FROM users WHERE phonenumber = ?");
$stmt->bind_param("s", $phonenumber);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Generate OTP
    $otp = rand(10000, 99999);

    // Store OTP in password_resets table
    $insert = $conn->prepare("INSERT INTO password_resets (phonenumber, otp) VALUES (?, ?)");
    $insert->bind_param("ss", $phonenumber, $otp);
    $insert->execute();

    // Send OTP via email using PHPMailer
    $mail = new PHPMailer;
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
    file_put_contents('smtp_debug.log', "Level $level: $str\n", FILE_APPEND);
    };
    $mail->isSMTP();
    $mail->Host = 'smtp-relay.brevo.com';
    $mail->SMTPAuth = true;
    $mail->Username = '8ba55e001@smtp-brevo.com'; // Your Gmail
    $mail->Password = 'k9bcsHCGwrmJKpvR'; // Your Gmail password or App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Use TLS encryption
    $mail->Port       = 587;

    $mail->setFrom('moneymate321@gmail.com', 'MoneyMate');
    $mail->addAddress($email);
    $mail->Subject = 'Password Reset OTP';
    $mail->Body    = "Your OTP code is: $otp";

    if (!$mail->send()) {
        echo json_encode(['success' => false, 'message' => 'Mailer Error: ' . $mail->ErrorInfo]);
    }
     else {
        echo json_encode(['success' => true, 'message' => 'OTP sent to email']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'User not registered']);
}

$conn->close();
?>
