<?php
include 'db_connect.php';
header('Content-Type: application/json');

$borrower_id = $_GET['borrower_id'];

if (!$borrower_id) {
    echo json_encode(["status" => false, "message" => "Missing borrower_id"]);
    exit();
}

$sql = "SELECT 
            id AS loan_id,
            amount,
            purpose,
            status,
            date_applied,
            date_updated,
            company_name
        FROM loans
        WHERE borrower_id = ?";

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
