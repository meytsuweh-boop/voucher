<?php
// api/redeem.php
require '../includes/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$code = $_POST['code'] ?? $_GET['code'] ?? '';
if ($code) {
    $stmt = $conn->prepare("UPDATE vouchers SET status='USED' WHERE code=? AND status='UNUSED'");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo json_encode(['ok'=>true]);
    } else {
        echo json_encode(['ok'=>false, 'msg'=>'Voucher already used or invalid']);
    }
} else {
    echo json_encode(['ok'=>false, 'msg'=>'No code provided']);
}
?>
