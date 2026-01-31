<?php
// Database connection
$host = 'sql104.infinityfree.com';
$user = 'if0_41035873';
$pass = 'ydFjMGskAucA';
$db = 'if0_41035873_wifi_vouchers';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
?>
