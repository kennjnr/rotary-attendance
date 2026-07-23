<?php
// config/db.php

define('DB_HOST', 'localhost');
define('DB_NAME', 'rotary_attendance');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_CHARSET', 'utf8mb4');

// define('APP_URL',        'http://localhost/rotary-attendance');
define('APP_URL',        'http://157.230.121.27/rotary-attendance');
define('CERT_DIR',       __DIR__ . '/../certificates/');
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'rcnbiupperhill@gmail.com');
define('SMTP_PASS',      'jpam swbt sgxa wsub');
define('SMTP_FROM_NAME', 'Rotary Club Attendance');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
