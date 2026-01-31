<?php
require '../includes/db.php';
header('Content-Type: application/json');
if (!isset($_GET['code'])) {
    echo json_encode(['success'=>false,'message'=>'No code provided.']);
    exit;
}
$code = $_GET['code'];
$stmt = $conn->prepare("DELETE FROM vouchers WHERE code = ?");
$stmt->bind_param('s', $code);
if ($stmt->execute()) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'message'=>'Delete failed!']);
}
exit;
