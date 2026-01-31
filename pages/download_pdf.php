<?php
// download_pdf.php: Generate a styled PDF for the voucher code
require_once '../vendor/autoload.php'; // mPDF
require '../includes/db.php';

if (!isset($_GET['code'])) {
    die('No code provided.');
}
$code = $_GET['code'];
$stmt = $conn->prepare("SELECT * FROM vouchers WHERE code = ? LIMIT 1");
$stmt->bind_param('s', $code);
$stmt->execute();
$result = $stmt->get_result();
if (!$row = $result->fetch_assoc()) {
    die('Voucher not found.');
}

$voucherHtml = '<div style="max-width:400px;margin:40px auto;padding:32px 28px;border:2px dashed #1de9b6;border-radius:14px;text-align:center;font-family:Arial,sans-serif;">';
$voucherHtml .= '<h2 style="color:#1de9b6;margin-bottom:18px;">WiFi Voucher</h2>';
$voucherHtml .= '<div style="font-size:1.1em;margin-bottom:10px;"><b style="color:#3a7afe;">Code:</b> ' . htmlspecialchars($row['code']) . '</div>';
$voucherHtml .= '<div style="margin-bottom:6px;"><b style="color:#3a7afe;">Minutes:</b> ' . $row['minutes'] . '</div>';
$voucherHtml .= '<div style="margin-bottom:6px;"><b style="color:#3a7afe;">Status:</b> ' . $row['status'] . '</div>';
$voucherHtml .= '<div style="margin-bottom:6px;"><b style="color:#3a7afe;">Date Created:</b> ' . $row['date_created'] . '</div>';
$voucherHtml .= '<div style="margin-bottom:16px;"><b style="color:#3a7afe;">Expiry:</b> ' . $row['expiry_date'] . '</div>';
$voucherHtml .= '<img src="../qrcodes/' . $row['qr_image'] . '" width="120" style="margin:18px 0;">';
$voucherHtml .= '</div>';

$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => [80,120]]); // Small voucher size
$mpdf->WriteHTML($voucherHtml);
$mpdf->Output('voucher_' . $row['code'] . '.pdf', 'D');
exit;
