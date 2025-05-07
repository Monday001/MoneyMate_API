<?php
header('Content-Type: application/json');
include 'db_connect.php';

// Get loan_id from the request
$loan_id = $_GET['loan_id'] ?? null;

if (!$loan_id) {
    echo json_encode(['success' => false, 'message' => 'Missing loan_id']);
    exit;
}

// Query to get repayment details for a specific loan_id
$sql = "SELECT r.id, r.loan_id, r.amount, r.paid_on, le.company_name
        FROM repayments r
        JOIN loan_applications l ON r.loan_id = l.id
        JOIN lenders le ON l.lender_id = le.id
        WHERE r.loan_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loan_id);
$stmt->execute();

$result = $stmt->get_result();
$repayments = [];

while ($row = $result->fetch_assoc()) {
    $repayments[] = [
        'id' => $row['id'],
        'loan_id' => $row['loan_id'],
        'amount' => $row['amount'],
        'paid_on' => $row['paid_on'],
        'company_name' => $row['company_name']
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'repayments' => $repayments
]);
?>
