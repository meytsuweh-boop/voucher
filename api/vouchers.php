<?php
// api/vouchers.php
require '../includes/db.php';
header('Content-Type: application/json');
$vouchers = [];
$res = $conn->query("SELECT code, minutes, expiry_date, status FROM vouchers WHERE status='UNUSED'");
while ($row = $res->fetch_assoc()) {
    $vouchers[] = $row;
}
echo json_encode($vouchers);
?>
