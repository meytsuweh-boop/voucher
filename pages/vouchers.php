<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
require '../includes/db.php';
// Filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$minutes = $_GET['minutes'] ?? '';
$sort = $_GET['sort'] ?? 'latest';
$where = [];
if ($search) $where[] = "code LIKE '%".$conn->real_escape_string($search)."%'";
if ($status) $where[] = "status='".$conn->real_escape_string($status)."'";
if ($minutes) $where[] = "minutes=".intval($minutes);
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$order_sql = $sort === 'oldest' ? 'ORDER BY date_created ASC' : 'ORDER BY date_created DESC';
$sql = "SELECT * FROM vouchers $where_sql $order_sql";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vouchers</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div style="max-width:1100px;margin:40px auto;">
        <h2>Vouchers</h2>
        <form method="get" style="margin-bottom:16px;">
            <input type="text" name="search" placeholder="Search by code" value="<?= htmlspecialchars($search) ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="UNUSED" <?= $status==='UNUSED'?'selected':'' ?>>UNUSED</option>
                <option value="USED" <?= $status==='USED'?'selected':'' ?>>USED</option>
                <option value="VOID" <?= $status==='VOID'?'selected':'' ?>>VOID</option>
                <option value="EXPIRED" <?= $status==='EXPIRED'?'selected':'' ?>>EXPIRED</option>
            </select>
            <select name="minutes">
                <option value="">All Minutes</option>
                <option value="5" <?= $minutes==='5'?'selected':'' ?>>5</option>
                <option value="10" <?= $minutes==='10'?'selected':'' ?>>10</option>
                <option value="30" <?= $minutes==='30'?'selected':'' ?>>30</option>
                <option value="60" <?= $minutes==='60'?'selected':'' ?>>60</option>
            </select>
            <select name="sort">
                <option value="latest" <?= $sort==='latest'?'selected':'' ?>>Latest</option>
                <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>Oldest</option>
            </select>
            <button class="btn" type="submit">Filter</button>
        </form>
        <table class="table">
            <tr><th>Code</th><th>Minutes</th><th>Status</th><th>Date Created</th><th>Date Used</th><th>Expiry</th><th>QR</th><th>Action</th></tr>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><a href="voucher_details.php?code=<?= urlencode($row['code']) ?>"><?= htmlspecialchars($row['code']) ?></a></td>
                <td><?= $row['minutes'] ?></td>
                <td><?= $row['status'] ?></td>
                <td><?= $row['date_created'] ?></td>
                <td><?= $row['date_used'] ?></td>
                <td><?= $row['expiry_date'] ?></td>
                <td><a href="../qrcodes/<?= $row['qr_image'] ?>" download>Download</a></td>
                <td>
                    <?php if ($row['status'] === 'UNUSED'): ?>
                        <form method="post" action="void_voucher.php" style="display:inline;">
                            <input type="hidden" name="code" value="<?= htmlspecialchars($row['code']) ?>">
                            <button class="btn void" type="submit">VOID</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
        <a class="btn" href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>