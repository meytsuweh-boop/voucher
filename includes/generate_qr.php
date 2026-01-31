<?php
// Usage: generate_qr.php?code=VOUCHER_CODE
require 'db.php';
$code = $_GET['code'] ?? '';
if (!$code) die('No code provided.');
$qr_file = '../qrcodes/' . $code . '.png';
if (!file_exists($qr_file)) {
    require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';
    QRcode::png($code, $qr_file, QR_ECLEVEL_L, 6);
}
header('Content-Type: image/png');
readfile($qr_file);
?>