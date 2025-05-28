<?php
header('Content-Type: application/json');

require_once 'db_connect.php'; // Connect to your DB

if (isset($_GET['email'])) {
    $email = $_GET['email'];

    $stmt = $conn->prepare("SELECT id FROM lenders WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($lenderId);

    if ($stmt->fetch()) {
        echo json_encode([
            "success" => true,
            "lenderId" => $lenderId
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Lender not found"
        ]);
    }

    $stmt->close();
} else {
    echo json_encode([
        "success" => false,
        "message" => "Email not provided"
    ]);
}
$conn->close();
?>
