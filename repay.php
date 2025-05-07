<?php
header('Content-Type: application/json');
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$loan_id = $_POST['loan_id'] ?? null;
$amount = $_POST['amount'] ?? null;

if (!$loan_id || !$amount || !is_numeric($amount) || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid loan_id or amount']);
    exit;
}

// Get current datetime
$paid_on = date('Y-m-d H:i:s');

try {
    // Insert repayment
    $insertSql = "INSERT INTO repayments (loan_id, amount, paid_on) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($insertSql);
    $stmt->bind_param("sd", $loan_id, $amount);
    $stmt->execute();
    $repayment_id = $stmt->insert_id;
    $stmt->close();

    $getSql = "SELECT paid_on FROM repayments WHERE id = ?";
$getStmt = $conn->prepare($getSql);
$getStmt->bind_param("i", $repayment_id);
$getStmt->execute();
$getStmt->bind_result($paid_on);
$getStmt->fetch();
$getStmt->close();


    echo json_encode([
        'success' => true,
        'message' => 'Repayment recorded successfully',
        'repayment_id' => $repayment_id,
        'loan_id' => $loan_id,
        'amount' => $amount,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to insert repayment: ' . $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>
