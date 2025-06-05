<?php
header('Content-Type: application/json');
include 'db_connect.php';

$response = [];

if (isset($_GET['lender_id'])) {
    $lender_id = $_GET['lender_id'];

    // Count loans per status
    $stmt = $conn->prepare("
        SELECT l.status, COUNT(*) as count 
        FROM loans l
        INNER JOIN loan_applications la ON l.id = la.loan_id
        WHERE la.lender_id = ?
        GROUP BY l.status
    ");
    $stmt->bind_param("i", $lender_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $status_counts = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'active' => 0,
        'closed' => 0
    ];

    while ($row = $result->fetch_assoc()) {
        $status = $row['status'];
        if (array_key_exists($status, $status_counts)) {
            $status_counts[$status] = (int)$row['count'];
        }
    }

    // Count unique borrowers for this lender
    $user_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT l.borrower_id) as user_count
        FROM loans l
        INNER JOIN loan_applications la ON l.id = la.loan_id
        WHERE la.lender_id = ? AND l.status = 'approved'
    ");
    $user_stmt->bind_param("i", $lender_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_count = $user_result->fetch_assoc()['user_count'] ?? 0;

    $response['success'] = true;
    $response['user_count'] = $user_count;

    // Map to Java's LoanStatus model structure
    $response['loan_status'] = [
        'approved_count' => $status_counts['approved'],
        'denied_count' => $status_counts['rejected'],
        'pending_count' => $status_counts['pending']
    ];
} else {
    $response['success'] = false;
    $response['message'] = 'Missing lender_id';
}

echo json_encode($response);
?>
