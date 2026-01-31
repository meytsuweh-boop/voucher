<?php
// Database connection (env vars only)
function env_or_fail($key) {
    $val = getenv($key);
    if ($val === false || $val === '') {
        die('Missing environment variable: ' . $key);
    }
    return $val;
}

$host = env_or_fail('DB_HOST');
$user = env_or_fail('DB_USER');
$pass = env_or_fail('DB_PASS');
$db = env_or_fail('DB_NAME');
$port = getenv('DB_PORT') ?: '3306';

$conn = new mysqli($host, $user, $pass, $db, (int)$port);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
?>
