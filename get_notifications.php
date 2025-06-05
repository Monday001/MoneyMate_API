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

// Fetch notifications for the borrower along with loan and lender info (if available)
$sql = "
    SELECT n.*, l.companyname
    FROM notifications n
    JOIN lenders l ON l.id = n.lender_id
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
        'status' => $row['notification_status'],   // unread/read
        'created_at' => $row['created_at'],
        'loan' => [
            'loan_id' => $row['loan_id'] ? (int)$row['loan_id'] : null,
            'status' => $row['loan_status'] ?? null,
            'amount' => $row['loan_amount'] !== null ? (float)$row['loan_amount'] : null,
        ],
        'lender' => [
            'lender_id' => $row['lender_id'] ? (int)$row['lender_id'] : null,
            'company_name' => $row['lender_name'] ?? null,
        ]
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'notifications' => $notifications
]);
