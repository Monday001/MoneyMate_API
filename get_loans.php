<?php
include 'db.php';

$borrower_id = $_GET['borrower_id'];

$sql = "SELECT id, amount, purpose, status, company_name, date_applied FROM loans WHERE borrower_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $borrower_id);
$stmt->execute();
$result = $stmt->get_result();

$loans = [];
while ($row = $result->fetch_assoc()) {
    $loans[] = $row;
}

echo json_encode($loans);
?>
