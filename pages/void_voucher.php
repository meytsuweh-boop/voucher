<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
require '../includes/db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $code = $_POST['code'];
    $stmt = $conn->prepare("UPDATE vouchers SET status='VOID' WHERE code=? AND status='UNUSED'");
    $stmt->bind_param('s', $code);
    $stmt->execute();
}
header('Location: vouchers.php');
exit;
