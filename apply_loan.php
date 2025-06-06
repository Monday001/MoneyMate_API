<?php

header('Content-Type: application/json');

error_reporting(E_ALL);  
ini_set('display_errors', 1);
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/error_log.txt");

include 'db_connect.php';

// Function to normalize phone number
function normalizePhone($number) {
    return preg_replace('/\D+/', '', trim($number));
}

try {
    $loanDetails = json_decode($_POST['loan_details'] ?? '', true);

    if (!is_array($loanDetails)) {
        error_log("Invalid loan_details JSON: " . ($_POST['loan_details'] ?? ''));
        echo json_encode(['success' => false, 'message' => 'Invalid or missing loan_details']);
        exit;
    }

    // Extract and validate fields
    $requiredFields = ['lender_id', 'borrower_id', 'full_name', 'email_address', 'phonenumber', 'amount', 'purpose'];
    foreach ($requiredFields as $field) {
        if (empty($loanDetails[$field])) {
            error_log("Missing field: $field");
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit;
        }
    }

    if (!isset($_FILES['id_front']) || !isset($_FILES['id_back'])) {
        echo json_encode(['success' => false, 'message' => 'Both ID front and back images are required']);
        exit;
    }

    $lender_id = $loanDetails['lender_id'];
    $borrower_id = $loanDetails['borrower_id'];
    $full_name = $loanDetails['full_name'];
    $email_address = $loanDetails['email_address'];
    $submitted_phone = $loanDetails['phonenumber'];
    $amount = $loanDetails['amount'];
    $purpose = $loanDetails['purpose'];

    // Validate image types and sizes
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    $maxFileSize = 2 * 1024 * 1024; // 2MB

    foreach (['id_front', 'id_back'] as $fileKey) {
        $fileType = $_FILES[$fileKey]['type'] ?? '';
        $fileSize = $_FILES[$fileKey]['size'] ?? 0;

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

    // Generate unique filenames
    $idFrontName = uniqid("front_", true) . "." . pathinfo($_FILES["id_front"]["name"], PATHINFO_EXTENSION);
    $idBackName  = uniqid("back_", true) . "." . pathinfo($_FILES["id_back"]["name"], PATHINFO_EXTENSION);

    if (
        !move_uploaded_file($_FILES["id_front"]["tmp_name"], $uploadDir . $idFrontName) ||
        !move_uploaded_file($_FILES["id_back"]["tmp_name"], $uploadDir . $idBackName)
    ) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload ID images']);
        exit;
    }

    // Validate borrower
    $checkUser = $conn->prepare("SELECT phonenumber FROM users WHERE id = ?");
    $checkUser->bind_param("i", $borrower_id);
    $checkUser->execute();
    $result = $checkUser->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid borrower_id (user does not exist)']);
        exit;
    }

    $userData = $result->fetch_assoc();
    $registeredPhone = $userData['phonenumber'];
    $checkUser->close();

    if (normalizePhone($submitted_phone) !== normalizePhone($registeredPhone)) {
        echo json_encode(['success' => false, 'message' => 'Phone number mismatch — must match registered number']);
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    // Insert loan
    $loanStmt = $conn->prepare("INSERT INTO loans (borrower_id, amount, purpose) VALUES (?, ?, ?)");
    $loanStmt->bind_param("ids", $borrower_id, $amount, $purpose);
    
    if (!$loanStmt->execute()) {
        throw new Exception("Loan insert failed: " . $loanStmt->error);
    }

    $loan_id = $conn->insert_id;
    $loanStmt->close();

    // Insert loan application
    $appStmt = $conn->prepare("INSERT INTO loan_applications 
        (loan_id, lender_id, borrower_id, amount, purpose, id_front, id_back, phone_number, full_name, email_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $appStmt->bind_param("iiidssssss", 
        $loan_id, 
        $lender_id, 
        $borrower_id, 
        $amount, 
        $purpose, 
        $idFrontName, 
        $idBackName, 
        $submitted_phone, 
        $full_name, 
        $email_address
    );

    if (!$appStmt->execute()) {
        throw new Exception("Application insert failed: " . $appStmt->error);
    }

    $conn->commit();
    $appStmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully',
        'loan_id' => $loan_id,
        'id_front' => $idFrontName,
        'id_back' => $idBackName
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    error_log("Unhandled Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred',
        'error' => $e->getMessage() // Consider removing in production
    ]);
}

$conn->close();
