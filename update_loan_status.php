<?php
header('Content-Type: application/json');
include 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

$loan_id = $data['loan_id'] ?? null;
$lender_id = $data['lender_id'] ?? null;
$approval_status = $data['approval_status'] ?? null; // 'approved' or 'rejected'

if (!$loan_id || !$lender_id || !in_array($approval_status, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Check if loan exists
$check = $conn->prepare("SELECT * FROM loan_applications WHERE id = ?");
$check->bind_param("i", $loan_id);
$check->execute();
$result = $check->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Loan not found']);
    exit;
}

// Record approval or rejection
$sql = "INSERT INTO loan_approvals (loan_id, lender_id, approval_status) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE approval_status = VALUES(approval_status)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $loan_id, $lender_id, $approval_status);
$stmt->execute();

// Update loan status in main table
$status_sql = "UPDATE loan_applications SET application_status = ? WHERE id = ?";
$status_stmt = $conn->prepare($status_sql);
$status_stmt->bind_param("si", $approval_status, $loan_id);
$status_stmt->execute();

// ✅ Add notification if rejected
if ($approval_status === 'rejected') {
    $notify_sql = "SELECT borrower_id FROM loan_applications WHERE id = ?";
    $notify_stmt = $conn->prepare($notify_sql);
    $notify_stmt->bind_param("i", $loan_id);
    $notify_stmt->execute();
    $result = $notify_stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $user_id = $row['borrower_id'];
        $title = "Loan Rejected";
        $message = "Your loan application (ID: $loan_id) has been rejected.";

        $insert_notify = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        $insert_notify->bind_param("iss", $user_id, $title, $message);
        $insert_notify->execute();
        $insert_notify->close();
    }

    $notify_stmt->close();
}

echo json_encode(['success' => true, 'message' => 'Loan status updated']);
$stmt->close();
$status_stmt->close();
$conn->close();
?>
