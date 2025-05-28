<?php

header('Content-Type: application/json');

error_reporting(E_ALL);  
ini_set('display_errors', 1);

ini_set("log_errors", 1);
ini_set("error_log", "error_log.txt");


include 'db_connect.php';

$loanDetails = json_decode($_POST['loan_details'] ?? '', true);

if (!$loanDetails) {
    echo json_encode(['success' => false, 'message' => 'Invalid loan_details JSON']);
    exit;
}


$lender_id = $loanDetails['lender_id'] ?? null;
$borrower_id = $loanDetails['borrower_id'] ?? null;
$amount = $loanDetails['amount'] ?? null;
$purpose = $loanDetails['purpose'] ?? null;

// Check required fields
if (!$borrower_id || !$lender_id || !$amount || !$purpose || !isset($_FILES['id_front']) || !isset($_FILES['id_back'])) {
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

    $checkUser = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $checkUser->bind_param("i", $borrower_id);
    $checkUser->execute();
    $result = $checkUser->get_result();

    if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid borrower_id (user does not exist)']);
    exit;
    }
    $checkUser->close();

    // Insert into loans table first
    $loanSql = "INSERT INTO loans (borrower_id, amount, purpose) VALUES (?, ?, ?)";
    $loanStmt = $conn->prepare($loanSql);
    $loanStmt->bind_param("ids", $borrower_id, $amount, $purpose);

    if ($loanStmt->execute()) {
        $loan_id = $conn->insert_id;
        $loanStmt->close();

        // Insert into loan_applications using the loan_id
       $appSql = "INSERT INTO loan_applications (loan_id, lender_id, borrower_id, amount, purpose, id_front, id_back)
           VALUES (?, ?, ?, ?, ?, ?, ?)";
        $appStmt = $conn->prepare($appSql);
        $appStmt->bind_param("iiidsss", $loan_id, $lender_id, $borrower_id, $amount, $purpose, $idFrontName, $idBackName);


        file_put_contents("debug_log.txt", json_encode($_POST) . PHP_EOL, FILE_APPEND);
        file_put_contents("debug_log.txt", json_encode($_FILES) . PHP_EOL, FILE_APPEND);


        if ($appStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Application submitted successfully',
                'loan_id' => $loan_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit loan application', 'error' => $appStmt->error]);
        }

        $appStmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create loan record', 'error' => $loanStmt->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload ID images']);
}

$conn->close();
?>
