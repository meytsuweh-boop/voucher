<?php
// Database connection (Render env vars with local fallbacks)
$host = getenv('DB_HOST') ?: 'sql104.infinityfree.com';
$user = getenv('DB_USER') ?: 'if0_41035873';
$pass = getenv('DB_PASS') ?: 'ydFjMGskAucA';
$db = getenv('DB_NAME') ?: 'if0_41035873_wifi_vouchers';
$port = getenv('DB_PORT') ?: '3306';

$conn = new mysqli($host, $user, $pass, $db, (int)$port);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
?>
