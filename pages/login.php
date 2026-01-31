<?php
session_start();
if (isset($_SESSION['admin'])) {
    header('Location: dashboard.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    // Simple hardcoded login for demo
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body.login-bg {
            background: #23243a;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: #23243a;
            border-radius: 18px;
            box-shadow: 0 4px 32px #0005;
            width: 420px;
            padding: 48px 40px 40px 40px;
            position: relative;
            overflow: hidden;
        }
        .login-title {
            color: #fff;
            font-size: 2.2rem;
            font-family: 'Segoe UI', Arial, sans-serif;
            font-weight: 600;
            margin-bottom: 32px;
            letter-spacing: 2px;
            text-align: center;
        }
        .login-form input {
            width: 100%;
            background: transparent;
            border: none;
            border-bottom: 2px solid #444;
            color: #fff;
            font-size: 1.1rem;
            margin-bottom: 28px;
            padding: 10px 0;
            outline: none;
            transition: border-color 0.2s;
        }
        .login-form input:focus {
            border-bottom: 2px solid #3a7afe;
        }
        .login-form button {
            width: 100%;
            background: #3a7afe;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 12px 0;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s;
        }
        .login-form button:hover {
            background: #2656c7;
        }
        .login-error {
            color: #ff6b6b;
            text-align: center;
            margin-bottom: 18px;
        }
        /* Decorative shapes */
        .login-shape1 {
            position: absolute;
            left: -120px;
            top: -120px;
            width: 260px;
            height: 260px;
            background: #3a7afe;
            border-radius: 50%;
            opacity: 0.18;
        }
        .login-shape2 {
            position: absolute;
            right: -60px;
            top: 40px;
            width: 120px;
            height: 120px;
            background: #3a7afe;
            border-radius: 50%;
            opacity: 0.12;
        }
        .login-shape3 {
            position: absolute;
            right: -80px;
            bottom: -80px;
            width: 180px;
            height: 180px;
            background: #3a7afe;
            border-radius: 50%;
            opacity: 0.10;
        }
        .login-shape4 {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0;
            height: 0;
            border-left: 80px solid #3a7afe;
            border-top: 80px solid transparent;
            opacity: 0.18;
        }
    </style>
</head>
<body class="login-bg">
    <div class="login-container">
        <div class="login-shape1"></div>
        <div class="login-shape2"></div>
        <div class="login-shape3"></div>
        <div class="login-shape4"></div>
        <div class="login-title">login</div>
        <?php if ($error): ?><div class="login-error"><?= $error ?></div><?php endif; ?>
        <form class="login-form" method="post" autocomplete="off">
            <input type="text" name="username" placeholder="Username" required autofocus>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">login</button>
        </form>
    </div>
</body>
</html>