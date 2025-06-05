<?php
include 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_GET['borrower_id'])) {
    echo json_encode(['status' => false, 'message' => 'Missing borrower_id']);
    exit;
}

$borrower_id = intval($_GET['borrower_id']);

$stmt = $conn->prepare("SELECT phone_number FROM loan_applications WHERE borrower_id = ? ORDER BY date_submitted DESC LIMIT 1");
$stmt->bind_param("i", $borrower_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(['status' => true, 'phone_number' => $row['phone_number']]);
} else {
    echo json_encode(['status' => false, 'message' => 'Phone number not found']);
}
