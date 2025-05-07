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

// Generate a reference code: e.g., REF-20250506-ABCDE123
function generateReferenceCode() {
    $date = date('Ymd');
    $rand = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    return "REF-$date-$rand";
}

$referenceCode = generateReferenceCode();

try {
    // Insert repayment with reference code
    $insertSql = "INSERT INTO repayments (loan_id, amount_paid, payment_date, reference_code) VALUES (?, ?, NOW(), ?)";
    $stmt = $conn->prepare($insertSql);
    $stmt->bind_param("sds", $loan_id, $amount, $referenceCode);
    $stmt->execute();
    $repayment_id = $stmt->insert_id;
    $stmt->close();

    // Get the payment date
    $getPaidOnSql = "SELECT payment_date FROM repayments WHERE id = ?";
    $getStmt = $conn->prepare($getPaidOnSql);
    $getStmt->bind_param("i", $repayment_id);
    $getStmt->execute();
    $getStmt->bind_result($paid_on);
    $getStmt->fetch();
    $getStmt->close();

    // Get the company/lender name
    $getCompanySql = "
        SELECT lenders.name 
        FROM loans 
        JOIN lenders ON loans.lender_id = lenders.id 
        WHERE loans.id = ?
    ";
    $companyStmt = $conn->prepare($getCompanySql);
    $companyStmt->bind_param("i", $loan_id);
    $companyStmt->execute();
    $companyStmt->bind_result($company_name);
    $companyStmt->fetch();
    $companyStmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Repayment recorded successfully',
        'repayment_id' => $repayment_id,
        'loan_id' => $loan_id,
        'amount' => $amount,
        'paid_on' => $paid_on,
        'company_name' => $company_name ?? 'N/A',
        'reference_code' => $referenceCode
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to insert repayment: ' . $e->getMessage()]);
}

$conn->close();
?>
