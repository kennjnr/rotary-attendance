<?php
// admin/includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load config to get APP_URL
require_once __DIR__ . '/../../config/db.php';

define('SESSION_TIMEOUT', 300); // 5 minutes

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

if (!empty($_SESSION['last_activity'])) {
     $inactive = time() -  $_SESSION['last_activity'];

    if ($inactive >= SESSION_TIMEOUT) {
        session_unset();
        session_destroy();

        session_start();
         $_SESSION['timeout_message'] =
            'You were logged out due to 5 minutes of inactivity.';

        header('Location: ' . APP_URL . '/admin/login.php?timeout=1');
        exit;
    }
}

$_SESSION['last_activity'] = time();
