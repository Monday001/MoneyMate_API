<?php
include 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['loan_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing loan_id']);
        exit;
    }

    $loan_id = intval($_POST['loan_id']);
    if ($loan_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid loan_id']);
        exit;
    }

    $conn->begin_transaction();

    try {
        // Get amount, borrower_id, and lender_id from loans table
        $stmt = $conn->prepare("SELECT amount, borrower_id, lender_id FROM loans WHERE id = ?");
        $stmt->bind_param("i", $loan_id);
        $stmt->execute();
        $stmt->bind_result($amount, $borrower_id, $lender_id);
        if (!$stmt->fetch()) {
            throw new Exception("Loan not found");
        }
        $stmt->close();

        // Insert disbursement referencing correct loan_id
        $stmt1 = $conn->prepare("INSERT INTO funds_disbursement (loan_id, disbursed_amount, disbursement_status) VALUES (?, ?, 'disbursed')");
        $stmt1->bind_param("id", $loan_id, $amount);
        $stmt1->execute();

        // Update loans table status only (removed balance update)
        $stmt2 = $conn->prepare("UPDATE loans SET status = 'disbursed' WHERE id = ?");
        $stmt2->bind_param("i", $loan_id);
        $stmt2->execute();

        // Insert notification for approval
        $message = "Congratulations! Your loan request has been approved and disbursed.";
        $stmt3 = $conn->prepare("INSERT INTO notifications (borrower_id, loan_id, lender_id, message, status) VALUES (?, ?, ?, ?, 'unread')");
        $stmt3->bind_param("iiis", $borrower_id, $loan_id, $lender_id, $message);
        $stmt3->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Loan disbursed and borrower notified successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Disbursement failed', 'error' => $e->getMessage()]);
    }
}
?>
