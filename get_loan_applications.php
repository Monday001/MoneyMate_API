<?php
header('Content-Type: application/json');
include 'db_connect.php';

$lenderId = isset($_GET['lender_id']) ? intval($_GET['lender_id']) : 0;

if ($lenderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing lender_id']);
    exit;
}

$sql = "SELECT 
            la.loan_id AS loan_id,
            la.full_name,
            la.phone_number,
            la.email_address,
            la.amount,
            la.purpose,
            la.application_status,
            la.date_submitted,
            la.id_front,
            la.id_back,
            u.username,
            fd.disbursed_amount,
            fd.disbursement_status
        FROM loan_applications la
        INNER JOIN users u ON la.borrower_id = u.id
        LEFT JOIN funds_disbursement fd ON la.loan_id = fd.loan_id
        WHERE la.lender_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lenderId);
$stmt->execute();
$result = $stmt->get_result();

$applications = [];

while ($row = $result->fetch_assoc()) {
    $loanId = $row['loan_id'];

    // Fetch payment history
    $paymentQuery = "SELECT reference_code, payment_date, amount_paid FROM repayments WHERE loan_id = ?";
    $stmt2 = $conn->prepare($paymentQuery);
    $stmt2->bind_param("i", $loanId);
    $stmt2->execute();
    $paymentResult = $stmt2->get_result();

    $payments = [];
    while ($paymentRow = $paymentResult->fetch_assoc()) {
        $payments[] = [
            'ref' => $paymentRow['reference_code'],
            'date' => $paymentRow['payment_date'],
            'amount' => $paymentRow['amount_paid']
        ];
    }

    $row['paymentHistory'] = $payments;

    // Default values if no disbursement record exists
    $row['disbursed_amount'] = $row['disbursed_amount'] ?? "0.00";
    $row['disbursement_status'] = $row['disbursement_status'] ?? "pending";

    $applications[] = $row;
}

if (!empty($applications)) {
    echo json_encode(['success' => true, 'applications' => $applications]);
} else {
    echo json_encode(['success' => false, 'message' => 'No loan applications found for this lender']);
}

$conn->close();
?>
