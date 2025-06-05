<?php
header('Content-Type: application/json');
include 'db_connect.php';

$lender_id = $_GET['lender_id'] ?? null; // Get lender ID from query string 

if (!$lender_id) {
    echo json_encode(['success' => false, 'message' => 'Lender ID is required']);
    exit;
}

//  Fetch total number of unique users with approved loans
$user_sql = "
    SELECT COUNT(DISTINCT l.borrower_id) as user_count
    FROM loans l
    INNER JOIN loan_applications la ON l.id = la.loan_id
    WHERE la.lender_id = ? AND l.status = 'approved'
";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $lender_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_count = $user_result->fetch_assoc()['user_count'] ?? 0;

// Fetch loan status counts
$loan_status_sql = "
    SELECT 
        SUM(CASE WHEN l.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN l.status = 'rejected' THEN 1 ELSE 0 END) as denied_count,
        SUM(CASE WHEN l.status = 'pending' THEN 1 ELSE 0 END) as pending_count
    FROM loans l
    INNER JOIN loan_applications la ON l.id = la.loan_id
    WHERE la.lender_id = ?
";
$loan_status_stmt = $conn->prepare($loan_status_sql);
$loan_status_stmt->bind_param("i", $lender_id);
$loan_status_stmt->execute();
$loan_status_result = $loan_status_stmt->get_result();
$loan_status = $loan_status_result->fetch_assoc() ?? [
    'approved_count' => 0,
    'denied_count' => 0,
    'pending_count' => 0
];

// Fetch loan applications with approved status
$sql = "
    SELECT la.*, l.amount, l.purpose, l.status, l.date_applied 
    FROM loan_applications la
    INNER JOIN loans l ON la.loan_id = l.id
    WHERE la.lender_id = ? AND l.status = 'approved'
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lender_id);
$stmt->execute();
$result = $stmt->get_result();

$loans = [];
while ($row = $result->fetch_assoc()) {
    $loan_id = $row['loan_id'];

    // Get repayment history for this loan
    $repayment_sql = "SELECT payment_date, amount_paid FROM repayments WHERE loan_id = ? AND lender_id = ?";
    $repayment_stmt = $conn->prepare($repayment_sql);
    $repayment_stmt->bind_param("ii", $loan_id, $lender_id);
    $repayment_stmt->execute();
    $repayment_result = $repayment_stmt->get_result();

    $repayments = [];
    while ($repayment_row = $repayment_result->fetch_assoc()) {
        $repayments[] = [
            'payment_date' => $repayment_row['payment_date'],
            'amount_paid' => $repayment_row['amount_paid']
        ];
    }

    // Add loan + repayment data
    $loans[] = [
        'loan_id' => $row['loan_id'],
        'borrower_id' => $row['borrower_id'],
        'amount' => $row['amount'],
        'purpose' => $row['purpose'],
        'status' => $row['status'],
        'date_submitted' => $row['date_submitted'],
        'id_front' => $row['id_front'],
        'id_back' => $row['id_back'],
        'payment_history' => $repayments
    ];
}

// Output result
echo json_encode([
    'success' => true,
    'user_count' => $user_count,
    'loan_status' => $loan_status,
    'loans' => $loans
]);

$stmt->close();
$conn->close();
?>
