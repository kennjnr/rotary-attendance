<?php
// admin/logout.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

session_unset();
session_destroy();

header('Location: ' . APP_URL . '/admin/login.php');
exit;
