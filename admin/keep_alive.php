<?php
// admin/keep_alive.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Check session is still valid
if (empty($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'expired']);
    exit;
}

// Refresh last activity timestamp
$_SESSION['last_activity'] = time();

echo json_encode([
    'status'    => 'ok',
    'message'   => 'Session refreshed',
    'expires_in'=> 300,
]);
