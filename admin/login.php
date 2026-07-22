<?php
// admin/login.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error          = '';
$timeoutMessage = '';

// Show timeout message if redirected from session expiry
if (!empty($_GET['timeout'])) {
     $timeoutMessage =  $_SESSION['timeout_message']
        ?? 'You were logged out due to inactivity.';
    unset($_SESSION['timeout_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $username = trim($_POST['username'] ?? '');
     $password = trim($_POST['password'] ?? '');

    if ($username &&  $password) {
         $pdo  = getPDO();
         $stmt =  $pdo->prepare(
            'SELECT * FROM admin_users WHERE username = ? AND is_active = 1');
         $stmt->execute([$username]);
         $admin =  $stmt->fetch();

        if ($admin && password_verify($password,  $admin['password_hash'])) {
            // Regenerate session ID on login (security best practice)
            session_regenerate_id(true);

             $_SESSION['admin_id']       =  $admin['id'];
             $_SESSION['admin_username'] =  $admin['username'];
             $_SESSION['admin_role']     =  $admin['role'];
             $_SESSION['last_activity']  = time();

             $pdo->prepare('UPDATE admin_users SET last_login=NOW() WHERE id=?')
                ->execute([$admin['id']]);

            header('Location: index.php');
            exit;
        } else {
             $error = 'Invalid username or password.';
        }
    } else {
         $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Rotary Attendance</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #003f87 0%, #0062cc 100%);
            min-height: 100vh; display: flex;
            align-items: center; justify-content: center; padding: 20px;
        }
        .login-card {
            background: #fff; border-radius: 16px; padding: 48px 40px;
            max-width: 420px; width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .brand { text-align: center; margin-bottom: 32px; }
        .brand .logo { font-size: 2.2rem; font-weight: 800; color: #003f87; }
        .brand .sub  { color: #888; font-size: 0.9rem; margin-top: 4px; }
        label {
            display: block; font-size: 0.85rem;
            font-weight: 600; color: #555; margin-bottom: 5px;
        }
        input {
            width: 100%; padding: 12px 14px;
            border: 1.5px solid #dee2e6; border-radius: 8px;
            font-size: 0.95rem; margin-bottom: 16px;
            transition: border-color 0.2s;
        }
        input:focus { outline: none; border-color: #003f87; }
        button {
            width: 100%; padding: 13px; background: #003f87;
            color: #fff; border: none; border-radius: 8px;
            font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: opacity 0.2s;
        }
        button:hover { opacity: 0.88; }
        .alert {
            padding: 12px 14px; border-radius: 8px;
            margin-bottom: 18px; font-size: 0.88rem;
        }
        .alert-error   { background: #f8d7da; color: #721c24; }
        .alert-warning { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="brand">
        <?php
         $logoPath = '../assets/images/logo.png';
        if (file_exists(__DIR__ . '/../assets/images/logo.png')): ?>
            <img src="<?=  $logoPath ?>"
                alt="Club Logo"
                style="max-width:200px; max-height:120px;
                        object-fit:contain; margin-bottom:14px;
                        display:block; margin-left:auto; margin-right:auto;">
        <?php endif; ?>
        <!-- <div class="logo">&#9900; Rotary</div> -->
        <div class="sub">Attendance System — Admin</div>
    </div>


    <?php if ($timeoutMessage): ?>
        <div class="alert alert-warning">
            ⏱️ <?= htmlspecialchars($timeoutMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <label>Username</label>
        <input type="text" name="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               autofocus required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit">Sign In</button>
    </form>
</div>
</body>
</html>
