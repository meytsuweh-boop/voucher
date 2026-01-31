<?php
// Run this script periodically (e.g., via cron) to auto-expire vouchers
require 'db.php';
$now = date('Y-m-d H:i:s');
$conn->query("UPDATE vouchers SET status='EXPIRED' WHERE status='UNUSED' AND expiry_date IS NOT NULL AND expiry_date < '$now'");
?>