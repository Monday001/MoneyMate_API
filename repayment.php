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

function generateReferenceCode() {
    $date = date('Ymd');
    $rand = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    return "REF-$date-$rand";
}

$referenceCode = generateReferenceCode();

try {
    // Fetch loan info
    $loanSql = "SELECT amount, status, lender_id FROM loans WHERE id = ?";
    $stmt = $conn->prepare($loanSql);
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $stmt->bind_result($loan_amount, $loan_status, $lender_id);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Loan not found']);
        exit;
    }
    $stmt->close();

    if ($loan_status === 'closed') {
        echo json_encode(['success' => false, 'message' => 'Loan is already closed.']);
        exit;
    }

    // Get total repaid so far
    $sumSql = "SELECT COALESCE(SUM(amount_paid), 0) FROM repayments WHERE loan_id = ?";
    $sumStmt = $conn->prepare($sumSql);
    $sumStmt->bind_param("i", $loan_id);
    $sumStmt->execute();
    $sumStmt->bind_result($total_repaid);
    $sumStmt->fetch();
    $sumStmt->close();

    $remaining = $loan_amount - $total_repaid;

    if ($amount > $remaining) {
        echo json_encode([
            'success' => false,
            'message' => 'Payment exceeds remaining loan balance.',
            'remaining_balance' => round($remaining, 2)
        ]);
        exit;
    }

    // **Check if a repayment record for this loan with the same reference code exists**
    // If yes, update the repayment amount & date; else insert new repayment
    $checkSql = "SELECT id, amount_paid FROM repayments WHERE loan_id = ? AND reference_code = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("is", $loan_id, $referenceCode);
    $checkStmt->execute();
    $checkStmt->bind_result($existing_repayment_id, $existing_amount_paid);

    if ($checkStmt->fetch()) {
        // Record exists - update repayment
        $checkStmt->close();

        $new_amount_paid = $existing_amount_paid + $amount;
        if ($new_amount_paid > $remaining + $existing_amount_paid) {
            echo json_encode([
                'success' => false,
                'message' => 'Updated payment exceeds remaining loan balance.',
                'remaining_balance' => round($remaining, 2)
            ]);
            exit;
        }

        $updateSql = "UPDATE repayments SET amount_paid = ?, payment_date = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("di", $new_amount_paid, $existing_repayment_id);
        $updateStmt->execute();
        $updateStmt->close();

        $repayment_id = $existing_repayment_id;

    } else {
        $checkStmt->close();

        // Insert repayment
        $insertSql = "INSERT INTO repayments (loan_id, amount_paid, payment_date, reference_code, lender_id)
                      VALUES (?, ?, NOW(), ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("idsi", $loan_id, $amount, $referenceCode, $lender_id);
        $insertStmt->execute();
        $repayment_id = $insertStmt->insert_id;
        $insertStmt->close();
    }

    // Get payment date
    $dateSql = "SELECT payment_date FROM repayments WHERE id = ?";
    $dateStmt = $conn->prepare($dateSql);
    $dateStmt->bind_param("i", $repayment_id);
    $dateStmt->execute();
    $dateStmt->bind_result($paid_on);
    $dateStmt->fetch();
    $dateStmt->close();

    // Get company name
    $companySql = "SELECT name FROM lenders WHERE id = ?";
    $companyStmt = $conn->prepare($companySql);
    $companyStmt->bind_param("i", $lender_id);
    $companyStmt->execute();
    $companyStmt->bind_result($company_name);
    $companyStmt->fetch();
    $companyStmt->close();

    // Calculate new total repaid and balance again
    $sumStmt = $conn->prepare($sumSql);
    $sumStmt->bind_param("i", $loan_id);
    $sumStmt->execute();
    $sumStmt->bind_result($new_total_repaid);
    $sumStmt->fetch();
    $sumStmt->close();

    $new_balance = $loan_amount - $new_total_repaid;

    if ($new_balance <= 0.001) {
        $updateLoanSql = "UPDATE loans SET status = 'closed', date_updated = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateLoanSql);
        $updateStmt->bind_param("i", $loan_id);
        $updateStmt->execute();
        $updateStmt->close();
        $loan_status = 'closed';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Repayment recorded successfully',
        'repayment_id' => $repayment_id,
        'loan_id' => $loan_id,
        'amount' => $amount,
        'paid_on' => $paid_on,
        'company_name' => $company_name ?? 'N/A',
        'reference_code' => $referenceCode,
        'remaining_balance' => round($new_balance, 2),
        'status' => $loan_status
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
