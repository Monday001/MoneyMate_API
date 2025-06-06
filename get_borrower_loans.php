<?php
include 'db_connect.php';

header('Content-Type: application/json');

$borrower_id = $_GET['borrower_id'];

if (!$borrower_id) {
    echo json_encode(["status" => false, "message" => "Missing borrower_id"]);
    exit();
}

$sql = "SELECT 
    l.id AS loan_id,
    l.amount,
    la.purpose,
    l.status,
    l.date_applied,
    l.date_updated,
    l.company_name
FROM loans l
JOIN loan_applications la ON l.id = la.loan_id
WHERE la.borrower_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $borrower_id);
$stmt->execute();
$result = $stmt->get_result();

$loans = [];
while ($row = $result->fetch_assoc()) {
    $loans[] = $row;
}

echo json_encode(["status" => true, "loans" => $loans]);
?>
