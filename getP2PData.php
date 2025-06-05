<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
include 'db_connect.php';

// Fetch lenders and their terms using LEFT JOIN
$sql = "SELECT l.id, l.companyname, t.term_1, t.term_2, t.term_3, t.term_4, t.term_5
        FROM lenders l
        LEFT JOIN lender_terms t ON l.id = t.lender_id";

$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No lenders found']);
    exit;
}

$companies = [];

while ($row = $result->fetch_assoc()) {
    $termsArray = array_filter([
        $row['term_1'],
        $row['term_2'],
        $row['term_3'],
        $row['term_4'],
        $row['term_5']
    ]);

    $termsText = !empty($termsArray) ? implode("\n", $termsArray) : "No terms available";
    $overview = $row['term_1'] ?? "No overview available";

    $companies[] = [
        'id' => $row['id'],
        'name' => $row['companyname'],
        'overview' => $overview,
        'terms' => $termsText
    ];
}

echo json_encode([
    'success' => true,
    'companies' => $companies
]);

$conn->close();
?>
