<?php
header('Content-Type: application/json');
include 'db_connect.php';

// Get raw POST data
$data = json_decode(file_get_contents("php://input"), true);

// Validate lender_id
$lender_id = $data['lender_id'] ?? null;

if (!$lender_id) {
    echo json_encode(['success' => false, 'message' => 'Missing lender_id']);
    exit;
}

// Extract terms, allowing null or empty values
$term_1 = $data['term_1'] ?? null;
$term_2 = $data['term_2'] ?? null;
$term_3 = $data['term_3'] ?? null;
$term_4 = $data['term_4'] ?? null;
$term_5 = $data['term_5'] ?? null;

// Prepare SQL statement
$sql = "INSERT INTO lender_terms (lender_id, term_1, term_2, term_3, term_4, term_5)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            term_1 = VALUES(term_1),
            term_2 = VALUES(term_2),
            term_3 = VALUES(term_3),
            term_4 = VALUES(term_4),
            term_5 = VALUES(term_5)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isssss", $lender_id, $term_1, $term_2, $term_3, $term_4, $term_5);

// Execute query
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Terms saved']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save terms']);
}

$stmt->close();
$conn->close();
?>
