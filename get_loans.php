<?php
header('Content-Type: application/json');
include 'db_connect.php';

$borrower_id = isset($_GET['borrower_id']) ? intval($_GET['borrower_id']) : 0;
$lender_id = isset($_GET['lender_id']) ? intval($_GET['lender_id']) : 0;

if ($borrower_id <= 0 || $lender_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid borrower_id or lender_id']);
    exit;
}

$sql = "
    SELECT 
        la.id AS application_id,
        l.id AS loan_id,
        la.full_name,
        la.purpose,
        l.amount,
        l.status AS loan_status,
        l.date_disbursed
    FROM loans l
    INNER JOIN loan_applications la ON l.application_id = la.id
    WHERE la.borrower_id = ? AND l.lender_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $borrower_id, $lender_id);
$stmt->execute();
$result = $stmt->get_result();

$loans = [];
while ($row = $result->fetch_assoc()) {
    $loans[] = $row;
}

echo json_encode(['success' => true, 'loans' => $loans]);
?>
