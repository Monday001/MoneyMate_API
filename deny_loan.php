<?php
require 'db_connect.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id = $_POST['loan_id'] ?? null;
    $reason = $_POST['reason'] ?? '';

    if (!$loan_id || empty($reason)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing loan_id or reason']);
        exit;
    }

    // Fetch borrower_id and lender_id from loans
    $stmt = $conn->prepare("SELECT borrower_id, lender_id FROM loans WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $stmt->bind_result($borrower_id, $lender_id);
    $stmt->fetch();
    $stmt->close();

    if ($borrower_id) {
        // Update loan status to denied
        $stmt = $conn->prepare("UPDATE loans SET status = 'denied' WHERE id = ?");
        $stmt->bind_param("i", $loan_id);
        $stmt->execute();
        $stmt->close();

        // Insert notification for denial
        $message = "Your loan request was denied: " . $reason;
        $stmt = $conn->prepare("INSERT INTO notifications (borrower_id, loan_id, lender_id, message, status) VALUES (?, ?, ?, ?, 'unread')");
        $stmt->bind_param("iiis", $borrower_id, $loan_id, $lender_id, $message);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['status' => 'success', 'message' => 'Loan denied and borrower notified']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Borrower not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
