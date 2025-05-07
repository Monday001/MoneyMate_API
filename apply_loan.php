<?php
header('Content-Type: application/json');
include 'db_connect.php';

$loanDetails = json_decode($_POST['loan_details'], true);

$borrower_id = $loanDetails['borrower_id'] ?? null;
$amount = $loanDetails['amount'] ?? null;
$purpose = $loanDetails['purpose'] ?? null;

// Check required fields
if (!$borrower_id || !$amount || !$purpose || !isset($_FILES['id_front']) || !isset($_FILES['id_back'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields or images']);
    exit;
}

// Allowed file types
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
$maxFileSize = 2 * 1024 * 1024; // 2MB

// Validate uploaded files
foreach (['id_front', 'id_back'] as $fileKey) {
    $fileType = $_FILES[$fileKey]['type'];
    $fileSize = $_FILES[$fileKey]['size'];

    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => "$fileKey must be JPG or PNG"]);
        exit;
    }

    if ($fileSize > $maxFileSize) {
        echo json_encode(['success' => false, 'message' => "$fileKey exceeds 2MB limit"]);
        exit;
    }
}

// Ensure upload directory exists
$uploadDir = "uploads/";
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Sanitize and generate unique file names
$idFrontExt = pathinfo($_FILES["id_front"]["name"], PATHINFO_EXTENSION);
$idBackExt  = pathinfo($_FILES["id_back"]["name"], PATHINFO_EXTENSION);

$idFrontName = uniqid("front_", true) . "." . $idFrontExt;
$idBackName  = uniqid("back_", true) . "." . $idBackExt;

// Move files
if (
    move_uploaded_file($_FILES["id_front"]["tmp_name"], $uploadDir . $idFrontName) &&
    move_uploaded_file($_FILES["id_back"]["tmp_name"], $uploadDir . $idBackName)
) {
    // Insert into DB
    $sql = "INSERT INTO loan_applications (borrower_id, amount, purpose, id_front, id_back) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idsss", $borrower_id, $amount, $purpose, $idFrontName, $idBackName);

    if ($stmt->execute()) {
        $newLoanId = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Application submitted',
            'loan_id' => $newLoanId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit loan']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload ID images']);
}

$conn->close();
?>
