<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
require '../includes/db.php';
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(bin2hex(random_bytes(4)));
    $minutes = intval($_POST['minutes']);
    $expiry = $_POST['expiry'] ? date('Y-m-d', strtotime($_POST['expiry'])) : null;
    $qr_image = $code . '.png';
    // Insert voucher
    $stmt = $conn->prepare("INSERT INTO vouchers (code, minutes, expiry_date, qr_image) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('siss', $code, $minutes, $expiry, $qr_image);
    if ($stmt->execute()) {
        // Generate QR
        require_once '../vendor/phpqrcode/qrlib.php';
        QRcode::png($code, '../qrcodes/' . $qr_image, QR_ECLEVEL_L, 6);
        $success = "Voucher created! Code: $code";
    } else {
        $error = 'Error creating voucher.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Voucher</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body.create-bg { background: #23243a; min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; }
        .create-container { background: #23243a; border-radius: 18px; box-shadow: 0 4px 32px #0005; width: 420px; padding: 48px 40px 40px 40px; position: relative; overflow: hidden; }
        .create-title { color: #fff; font-size: 2.2rem; font-family: 'Segoe UI', Arial, sans-serif; font-weight: 600; margin-bottom: 32px; letter-spacing: 2px; text-align: center; }
        .create-form input, .create-form select { width: 100%; background: transparent; border: none; border-bottom: 2px solid #444; color: #fff; font-size: 1.1rem; margin-bottom: 28px; padding: 10px 0; outline: none; transition: border-color 0.2s; }
        .create-form input:focus, .create-form select:focus { border-bottom: 2px solid #3a7afe; }
        .create-form button { width: 100%; background: #3a7afe; color: #fff; border: none; border-radius: 6px; padding: 12px 0; font-size: 1.1rem; font-weight: 600; cursor: pointer; margin-top: 10px; transition: background 0.2s; }
        .create-form button:hover { background: #2656c7; }
        .create-success { color: #4caf50; text-align: center; margin-bottom: 18px; }
        .create-error { color: #ff6b6b; text-align: center; margin-bottom: 18px; }
        .create-shape1 { position: absolute; left: -120px; top: -120px; width: 260px; height: 260px; background: #3a7afe; border-radius: 50%; opacity: 0.18; }
        .create-shape2 { position: absolute; right: -60px; top: 40px; width: 120px; height: 120px; background: #3a7afe; border-radius: 50%; opacity: 0.12; }
        .create-shape3 { position: absolute; right: -80px; bottom: -80px; width: 180px; height: 180px; background: #3a7afe; border-radius: 50%; opacity: 0.10; }
        .create-shape4 { position: absolute; left: 0; bottom: 0; width: 0; height: 0; border-left: 80px solid #3a7afe; border-top: 80px solid transparent; opacity: 0.18; }
    </style>
</head>
<body class="create-bg">
    <div class="create-container">
        <div class="create-shape1"></div>
        <div class="create-shape2"></div>
        <div class="create-shape3"></div>
        <div class="create-shape4"></div>
        <div class="create-title">Create Voucher</div>
        <?php if ($success): ?><div class="create-success"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="create-error"><?= $error ?></div><?php endif; ?>
        <form class="create-form" method="post" autocomplete="off">
            <select name="minutes" required>
                <option value="">Select Minutes</option>
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="30">30</option>
                <option value="60">60</option>
            </select>
            <input type="date" name="expiry" placeholder="Expiry Date (optional)">
            <button type="submit">Create Voucher</button>
        </form>
    </div>
</body>
</html>