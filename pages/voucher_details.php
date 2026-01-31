<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
require '../includes/db.php';
$code = $_GET['code'] ?? '';
if (!$code) die('No voucher code specified.');
$stmt = $conn->prepare("SELECT * FROM vouchers WHERE code = ?");
$stmt->bind_param('s', $code);
$stmt->execute();
$result = $stmt->get_result();
if (!$row = $result->fetch_assoc()) die('Voucher not found.');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Voucher Details</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div style="max-width:500px;margin:40px auto;">
        <div class="card">
            <h2>Voucher Details</h2>
            <b>Code:</b> <?= htmlspecialchars($row['code']) ?><br>
            <b>Minutes:</b> <?= $row['minutes'] ?><br>
            <b>Status:</b> <?= $row['status'] ?><br>
            <b>Date Created:</b> <?= $row['date_created'] ?><br>
            <b>Date Used:</b> <?= $row['date_used'] ?><br>
            <b>Expiry Date:</b> <?= $row['expiry_date'] ?><br>
            <b>QR Image:</b> <a href="../qrcodes/<?= $row['qr_image'] ?>" download>Download</a><br><br>
            <?php if ($row['status'] === 'UNUSED'): ?>
                <form method="post" action="void_voucher.php">
                    <input type="hidden" name="code" value="<?= htmlspecialchars($row['code']) ?>">
                    <button class="btn void" type="submit">VOID</button>
                </form>
            <?php endif; ?>
            <a class="btn" href="vouchers.php">Back</a>
        </div>
    </div>
</body>
</html>