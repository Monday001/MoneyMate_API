<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
include 'db_connect.php';

// Fetch all lenders
$lenderSql = "SELECT id, companyname FROM lenders";
$lenderResult = $conn->query($lenderSql);

if (!$lenderResult || $lenderResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No lenders found']);
    exit;
}

$companies = [];

while ($lender = $lenderResult->fetch_assoc()) {
    $lenderId = $lender['id'];
    $companyName = $lender['companyname'];

    // Fetch terms for each lender
    $termsSql = "SELECT term_1, term_2, term_3, term_4, term_5 
                 FROM lender_terms 
                 WHERE lender_id = ?";
    $termsStmt = $conn->prepare($termsSql);
    $termsStmt->bind_param("i", $lenderId);
    $termsStmt->execute();
    $termsResult = $termsStmt->get_result();

    if ($termsResult && $termsRow = $termsResult->fetch_assoc()) {
        $termsArray = array_filter([
            $termsRow['term_1'],
            $termsRow['term_2'],
            $termsRow['term_3'],
            $termsRow['term_4'],
            $termsRow['term_5']
        ]);

        $termsText = implode("\n", $termsArray);

        $companies[] = [
            'id' => $lenderId, // ✅ Added this line
            'name' => $companyName,
            'overview' => $termsRow['term_1'],
            'terms' => $termsText
        ];
    }

    $termsStmt->close();
}

echo json_encode([
    'success' => true,
    'companies' => $companies
]);

$conn->close();
?>
