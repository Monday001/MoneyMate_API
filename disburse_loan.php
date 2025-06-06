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
        // Get amount and borrower_id from loans, and lender_id from loan_applications
        $stmt = $conn->prepare("
            SELECT l.amount, l.borrower_id, la.lender_id
            FROM loans l
            JOIN loan_applications la ON l.id = la.loan_id
            WHERE l.id = ?
        ");
        $stmt->bind_param("i", $loan_id);
        $stmt->execute();
        $stmt->bind_result($amount, $borrower_id, $lender_id);
        if (!$stmt->fetch()) {
            throw new Exception("Loan or application not found");
        }
        $stmt->close();

        // Insert disbursement
        $stmt1 = $conn->prepare("INSERT INTO funds_disbursement (loan_id, disbursed_amount, disbursement_status) VALUES (?, ?, 'disbursed')");
        $stmt1->bind_param("id", $loan_id, $amount);
        $stmt1->execute();

        // Update loan status
        $stmt2 = $conn->prepare("UPDATE loans SET status = 'disbursed' WHERE id = ?");
        $stmt2->bind_param("i", $loan_id);
        $stmt2->execute();

        // Send notification
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
