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
$sslCa = getenv('DB_SSL_CA') ?: '';

$conn = mysqli_init();
if ($conn === false) {
    die('Database connection failed: mysqli_init() failed');
}

if ($sslCa !== '') {
    $caPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'db_ca.pem';
    if (file_put_contents($caPath, $sslCa) === false) {
        die('Database connection failed: could not write CA certificate');
    }
    if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
        @mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    }
    mysqli_ssl_set($conn, null, null, $caPath, null, null);
    if (!$conn->real_connect($host, $user, $pass, $db, (int)$port, null, MYSQLI_CLIENT_SSL)) {
        die('Database connection failed: ' . $conn->connect_error);
    }
} else {
    if (!$conn->real_connect($host, $user, $pass, $db, (int)$port)) {
        die('Database connection failed: ' . $conn->connect_error);
    }
}
?>
