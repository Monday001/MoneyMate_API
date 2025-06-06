<?php
header('Content-Type: application/json');
include 'db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_GET['borrower_id']) || empty($_GET['borrower_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing borrower_id']);
    exit;
}

$borrower_id = intval($_GET['borrower_id']);

if ($borrower_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid borrower_id']);
    exit;
}

// Updated SQL with proper joins and aliases
$sql = "
    SELECT 
        n.id AS notification_id,
        n.message,
        n.status AS notification_status,
        n.created_at,
        l.id AS loan_id,
        l.status AS loan_status,
        l.amount AS loan_amount,
        n.lender_id,
        le.companyname AS lender_name
    FROM notifications n
    LEFT JOIN loans l ON n.loan_id = l.id
    LEFT JOIN lenders le ON n.lender_id = le.id
    WHERE n.borrower_id = ?
    ORDER BY n.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $borrower_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'notification_id' => (int)$row['notification_id'],
        'message' => $row['message'],
        'status' => $row['notification_status'],
        'created_at' => $row['created_at'],
        'loan' => [
            'loan_id' => isset($row['loan_id']) ? (int)$row['loan_id'] : null,
            'status' => $row['loan_status'] ?? null,
            'amount' => isset($row['loan_amount']) ? (float)$row['loan_amount'] : null,
        ],
        'lender' => [
            'lender_id' => isset($row['lender_id']) ? (int)$row['lender_id'] : null,
            'companyname' => $row['lender_name'] ?? null,
        ]
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'notifications' => $notifications
]);
