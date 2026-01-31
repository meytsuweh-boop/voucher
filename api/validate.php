<?php
// api/validate.php
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
    $stmt = $conn->prepare(
        "SELECT code, minutes, expiry_date, status
         FROM vouchers
         WHERE code=? LIMIT 1"
    );
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        // Reject used/expired/voided vouchers
        if ($row['status'] !== 'UNUSED') {
            echo json_encode(['ok'=>false, 'msg'=>'Voucher not available', 'voucher'=>$row]);
            exit;
        }
        if (!empty($row['expiry_date']) && strtotime($row['expiry_date']) < time()) {
            echo json_encode(['ok'=>false, 'msg'=>'Voucher expired', 'voucher'=>$row]);
            exit;
        }
        echo json_encode(['ok'=>true, 'voucher'=>$row]);
    } else {
        echo json_encode(['ok'=>false, 'msg'=>'Invalid code']);
    }
} else {
    echo json_encode(['ok'=>false, 'msg'=>'No code provided']);
}
?>
