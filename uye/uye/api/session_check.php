<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Aynı auth.php gibi session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'session_id' => session_id(),
    'tpjd_authenticated' => $_SESSION['tpjd_authenticated'] ?? 'NOT SET',
    'tpjd_login_time' => $_SESSION['tpjd_login_time'] ?? 'NOT SET',
    'tpjd_ip' => $_SESSION['tpjd_ip'] ?? 'NOT SET',
    'all_session_keys' => array_keys($_SESSION),
    'cookie_phpsessid' => $_COOKIE['PHPSESSID'] ?? 'NO COOKIE'
], JSON_UNESCAPED_UNICODE);
