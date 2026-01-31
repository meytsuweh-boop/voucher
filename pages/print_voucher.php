<?php
// print_voucher.php: Print-friendly voucher page
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
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = $host !== '' ? ($scheme . '://' . $host) : '';
$qrUrl = $baseUrl !== '' ? ($baseUrl . '/qrcodes/' . rawurlencode($row['qr_image'])) : ('../qrcodes/' . rawurlencode($row['qr_image']));
?><!DOCTYPE html>
<html>
<head>
    <title>Print Voucher</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Manrope', 'Segoe UI', Arial, sans-serif;
            background:
                radial-gradient(900px 500px at 15% 10%, rgba(29,233,182,0.15), transparent 55%),
                radial-gradient(900px 600px at 90% 15%, rgba(58,122,254,0.12), transparent 60%),
                #f2f5fb;
            color: #1f2533;
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px 14px;
        }
        .voucher-box {
            max-width: 420px;
            width: 100%;
            border: 2px dashed #1de9b6;
            padding: 28px 28px 24px 28px;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 18px 40px rgba(20, 30, 60, 0.18);
            position: relative;
        }
        .voucher-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            letter-spacing: 0.6px;
            color: #111827;
        }
        .brand-dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: #1de9b6;
            box-shadow: 0 0 16px rgba(29,233,182,0.55);
        }
        .voucher-badge {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 999px;
            color: #3a7afe;
            border: 1px solid rgba(58,122,254,0.35);
            background: rgba(58,122,254,0.08);
            font-weight: 700;
        }
        .voucher-title {
            color: #1de9b6;
            font-size: 1.35rem;
            font-weight: 800;
            margin: 10px 0 12px 0;
            letter-spacing: 0.8px;
        }
        .voucher-info {
            text-align: left;
            margin: 0 auto 16px auto;
            display: grid;
            gap: 6px;
        }
        .voucher-info div {
            font-size: 0.98rem;
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 8px;
        }
        .voucher-label {
            color: #3a7afe;
            font-weight: 600;
        }
        .voucher-value {
            color: #1f2533;
            font-weight: 600;
        }
        .voucher-status {
            font-weight: 700;
            color: #1de9b6;
        }
        .voucher-qr {
            margin: 18px 0 14px 0;
            display: grid;
            place-items: center;
        }
        .voucher-qr img {
            border: 2px solid #e8f8f3;
            border-radius: 12px;
            background: #fff;
            padding: 8px;
            width: 132px;
            height: 132px;
            box-shadow: 0 8px 16px rgba(29,233,182,0.12);
        }
        .voucher-note {
            text-align: center;
            color: #6b7280;
            font-size: 0.86rem;
            margin: 6px 0 14px 0;
        }
        .print-btn {
            margin-top: 18px;
            background: #3a7afe;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px 32px;
            font-size: 0.98rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 8px #3a7afe33;
            transition: background 0.18s;
        }
        .print-btn:hover {
            background: #2656c7;
        }
        .footer-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 6px;
            font-size: 0.85rem;
            color: #94a3b8;
        }
        @media print {
            body { background: #fff !important; padding: 0; }
            .print-btn { display: none; }
            .voucher-box { box-shadow: none; border: 2px dashed #1de9b6; }
            .voucher-note { color: #444; }
        }
        @media (max-width: 420px) {
            .voucher-box { padding: 22px 20px; }
            .voucher-info div { grid-template-columns: 100px 1fr; }
        }
    </style>
</head>
<body>
    <div class="voucher-box">
        <div class="voucher-header">
            <div class="brand">
                <span class="brand-dot"></span>
                <span>WiFiPoint</span>
            </div>
            <span class="voucher-badge">VOUCHER</span>
        </div>
        <div class="voucher-title">Access Code</div>
        <div class="voucher-info">
            <div><span class="voucher-label">Code:</span> <span class="voucher-value"><?= htmlspecialchars($row['code']) ?></span></div>
            <div><span class="voucher-label">Minutes:</span> <span class="voucher-value"><?= $row['minutes'] ?></span></div>
            <div><span class="voucher-label">Status:</span> <span class="voucher-status"><?= $row['status'] ?></span></div>
            <div><span class="voucher-label">Date Created:</span> <span class="voucher-value"><?= $row['date_created'] ?></span></div>
            <div><span class="voucher-label">Expiry:</span> <span class="voucher-value"><?= $row['expiry_date'] ?></span></div>
        </div>
        <div class="voucher-qr">
            <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code">
        </div>
        <div class="voucher-note">Scan the QR or enter the code on the portal.</div>
        <button class="print-btn" onclick="window.print()">Print</button>
        <div class="footer-row">
            <span>Valid once</span>
            <span><?= htmlspecialchars($row['code']) ?></span>
        </div>
    </div>
</body>
</html>
