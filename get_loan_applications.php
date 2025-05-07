<?php
header('Content-Type: application/json');
include 'db_connect.php';

$loanId = isset($_GET['loan_id']) ? intval($_GET['loan_id']) : 0;

if ($loanId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing loan_id']);
    exit;
}

$sql = "SELECT 
            loan_applications.id, 
            loan_applications.full_name, 
            loan_applications.phone_number, 
            loan_applications.email_address, 
            loan_applications.amount, 
            loan_applications.purpose, 
            loan_applications.application_status, 
            loan_applications.date_submitted, 
            loan_applications.id_front, 
            loan_applications.id_back, 
            users.username 
        FROM loan_applications 
        INNER JOIN users ON loan_applications.borrower_id = users.id 
        WHERE loan_applications.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $loanId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Fetch payment history
    $paymentQuery = "SELECT reference, payment_date, amount_paid FROM repayments WHERE loan_id = ?";
    $stmt2 = $conn->prepare($paymentQuery);
    $stmt2->bind_param("i", $loanId);
    $stmt2->execute();
    $paymentResult = $stmt2->get_result();

    $payments = [];
    while ($paymentRow = $paymentResult->fetch_assoc()) {
        $payments[] = [
            'ref' => $paymentRow['reference'],
            'date' => $paymentRow['payment_date'],
            'amount' => $paymentRow['amount_paid']
        ];
    }

    $row['paymentHistory'] = $payments;

    echo json_encode(['success' => true, 'application' => $row]);
} else {
    echo json_encode(['success' => false, 'message' => 'Loan not found']);
}

$conn->close();
?>
