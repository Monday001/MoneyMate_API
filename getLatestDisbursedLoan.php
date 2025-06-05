<?php
include 'db_connect.php';

$phone_number = $_GET['phone_number'] ?? $_POST['phone_number'] ?? null;

if (!$phone_number) {
    echo json_encode(["success" => false, "message" => "Phone number is required"]);
    exit();
}

// First: fetch the latest disbursed loan + total repaid + balance
$loanSql = "
    SELECT 
        la.loan_id,
        la.phone_number,
        la.full_name,
        la.amount AS requested_amount,
        fd.disbursed_amount,
        fd.disbursed_date,
        fd.disbursement_status,
        COALESCE(SUM(r.amount_paid), 0) AS total_repaid,
        (fd.disbursed_amount - COALESCE(SUM(r.amount_paid), 0)) AS remaining_balance
    FROM loan_applications la
    INNER JOIN funds_disbursement fd ON la.loan_id = fd.loan_id
    LEFT JOIN repayments r ON la.loan_id = r.loan_id
    WHERE la.phone_number = ? AND fd.disbursement_status = 'disbursed'
    GROUP BY la.loan_id
    ORDER BY fd.disbursed_date DESC
    LIMIT 1
";

$stmt = $conn->prepare($loanSql);
$stmt->bind_param("s", $phone_number);
$stmt->execute();
$loanResult = $stmt->get_result();

if ($loanRow = $loanResult->fetch_assoc()) {
    $loan_id = $loanRow['loan_id'];

    // Then: fetch all repayments for this loan
    $repaymentSql = "
        SELECT 
            amount_paid,
            payment_date,
            reference_code,
            lender_id
        FROM repayments
        WHERE loan_id = ?
        ORDER BY payment_date DESC
    ";

    $repaymentStmt = $conn->prepare($repaymentSql);
    $repaymentStmt->bind_param("i", $loan_id);
    $repaymentStmt->execute();
    $repaymentResult = $repaymentStmt->get_result();

    $repayments = [];
    while ($row = $repaymentResult->fetch_assoc()) {
        $repayments[] = $row;
    }

    $loanRow['repayments'] = $repayments;

    echo json_encode(["success" => true, "loan" => $loanRow]);
    $repaymentStmt->close();
} else {
    echo json_encode(["success" => false, "message" => "No disbursed loans found"]);
}

$stmt->close();
$conn->close();
?>
