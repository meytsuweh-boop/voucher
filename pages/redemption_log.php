<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
require '../includes/db.php';
$result = $conn->query("SELECT * FROM redemption_log ORDER BY date_time DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Redemption Log</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div style="max-width:1000px;margin:40px auto;">
        <h2>Redemption Log</h2>
        <table class="table">
            <tr><th>Voucher Code</th><th>Minutes</th><th>Date & Time</th><th>Source</th><th>Status</th></tr>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['voucher_code']) ?></td>
                <td><?= $row['minutes'] ?></td>
                <td><?= $row['date_time'] ?></td>
                <td><?= $row['source'] ?></td>
                <td><?= $row['status'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
        <a class="btn" href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>