<?php

ob_start();
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
include 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);
$lender_id = $data['lender_id'] ?? null;

if (!$lender_id) {
    echo json_encode(['success' => false, 'message' => 'Missing lender_id']);
    exit;
}

// Get lender name and amount (assuming this is in the `lenders` table)
$lenderSql = "SELECT name, amount FROM lenders WHERE id = ?";
$lenderStmt = $conn->prepare($lenderSql);
$lenderStmt->bind_param("i", $lender_id);
$lenderStmt->execute();
$lenderResult = $lenderStmt->get_result();

if (!$lenderResult || !$lenderRow = $lenderResult->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Lender not found']);
    exit;
}

$lenderName = $lenderRow['name'];
$amount = $lenderRow['amount'];

// Get lender terms
$termsSql = "SELECT term_1, term_2, term_3, term_4, term_5 
             FROM lender_terms 
             WHERE lender_id = ?";
$termsStmt = $conn->prepare($termsSql);
$termsStmt->bind_param("i", $lender_id);
$termsStmt->execute();
$termsResult = $termsStmt->get_result();

if ($termsResult && $row = $termsResult->fetch_assoc()) {
    $termsArray = array_filter([
        $row['term_1'],
        $row['term_2'],
        $row['term_3'],
        $row['term_4'],
        $row['term_5']
    ]);
    $termsText = implode("\n", $termsArray);

    $companies = [
        [
            'name' => $lenderName,
            'amount' => $amount,
            'overview' => "Offering Loans from $amount @ 1.8%",
            'terms' => $termsText
        ]
    ];

    echo json_encode([
        'success' => true,
        'companies' => $companies
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No terms found']);
}

$lenderStmt->close();
$termsStmt->close();
$conn->close();
?>
