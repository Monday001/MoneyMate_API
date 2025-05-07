<?php
include 'db.php';

$loan_id = $_GET['loan_id'];

$sql = "SELECT amount_paid, payment_date FROM repayments WHERE loan_id = ? ORDER BY payment_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$result = $stmt->get_result();

$repayments = [];
while ($row = $result->fetch_assoc()) {
    $repayments[] = $row;
}

echo json_encode($repayments);
?>
