<?php
ob_start();
error_reporting(0);
header('Content-Type: application/json');

$host = "localhost";
$user = "root";
$password = "";
$db = "moneymate";

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Check for common fields
$password = $data['password'] ?? null;
$hashedPassword = $password ? password_hash($password, PASSWORD_DEFAULT) : null;

if (!$password) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

// Try to determine user type based on fields
$isLender = isset($data['companyname'], $data['license'], $data['email']);
$isUser = isset($data['username'], $data['phonenumber']);

if ($isLender) {
    // Lender registration
    $companyname = $data['companyname'];
    $license = $data['license'];
    $email = $data['email'];

    // Generate a unique private key
    $private_key = bin2hex(random_bytes(16));

    $sql = "INSERT INTO lenders (companyname, license, email, password, private_key) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $companyname, $license, $email, $hashedPassword, $private_key);

    if ($stmt->execute()) {
        // Send private key via email
        $subject = "Your Unique Private Key - MoneyMate";
        $message = "Hello $companyname,\n\nThank you for registering as a lender on MoneyMate.\n\nYour unique private key is:\n$private_key\n\nPlease keep it safe.\n\nRegards,\nMoneyMate Team";
        $headers = "From: no-reply@moneymate.com\r\n" .
                   "Reply-To: support@moneymate.com\r\n" .
                   "X-Mailer: PHP/" . phpversion();

                   if (!@mail($email, $subject, $message, $headers)) {
                    // Log the error silently or send a fallback email
                    error_log("Failed to send email to $email");
                    // You could optionally notify user with a non-breaking message
                }
                ob_clean();
                
        echo json_encode([
            'success' => true,
            'message' => "Lender registered successfully. Unique key sent to $email",
            'private_key' => $private_key
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lender registration failed']);
    }

    $stmt->close();

} elseif ($isUser) {
    // User registration
    $username = $data['username'];
    $phonenumber = $data['phonenumber'];

    $sql = "INSERT INTO users (username, phonenumber, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $phonenumber, $hashedPassword);

    if ($stmt->execute()) {
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'User signup successful']);
    } else {
        echo json_encode(['success' => false, 'message' => 'User signup failed']);
    }

    $stmt->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Unable to determine user type. Please check your input fields.']);
}

$conn->close();
?>
