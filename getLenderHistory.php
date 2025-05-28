<?php
header('Content-Type: application/json');
include 'db_connect.php'; // Include your database connection file

$lender_id = $_GET['lender_id'] ?? null; // Get lender ID from query string 

if (!$lender_id) {
    echo json_encode(['success' => false, 'message' => 'Lender ID is required']);
    exit;
}

// Fetch total number of users linked to the lender
$user_sql = "SELECT COUNT(*) as user_count FROM repayments WHERE lender_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $lender_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_count = $user_result->fetch_assoc()['user_count'];

// Fetch the loan counts by status for the given lender
$loan_status_sql = "
    SELECT 
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'Denied' THEN 1 ELSE 0 END) as denied_count,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count
    FROM loans 
    WHERE lender_id = ?
";
$loan_status_stmt = $conn->prepare($loan_status_sql);
$loan_status_stmt->bind_param("i", $lender_id);
$loan_status_stmt->execute();
$loan_status_result = $loan_status_stmt->get_result();
$loan_status = $loan_status_result->fetch_assoc();

// Fetch the loan applications for the given lender (optional, as previously implemented)
$sql = "SELECT * FROM loan_applications WHERE lender_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lender_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if loans are found
$loans = [];
while ($row = $result->fetch_assoc()) {
    // Get payment history (as previously implemented)
    $loan_id = $row['loan_id'];
    $payment_sql = "SELECT * FROM payments WHERE loan_id = ?";
    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param("i", $loan_id);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();
    
    $payments = [];
    while ($payment_row = $payment_result->fetch_assoc()) {
        $payments[] = [
            'payment_date' => $payment_row['payment_date'],
            'amount_paid' => $payment_row['amount_paid']
        ];
    }
    
    // Add loan info along with payment history
    $loans[] = [
        'loan_id' => $row['loan_id'],
        'borrower_id' => $row['borrower_id'],
        'amount' => $row['amount'],
        'purpose' => $row['purpose'],
        'status' => $row['status'],
        'date_submitted' => $row['date_submitted'],
        'id_front' => $row['id_front'],
        'id_back' => $row['id_back'],
        'payment_history' => $payments
    ];
}

// Return all the data including user count and loan status counts
echo json_encode([
    'success' => true,
    'user_count' => $user_count,
    'loan_status' => $loan_status,
    'loans' => $loans
]);

$stmt->close();
$conn->close();
?>
