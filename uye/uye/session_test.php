<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session test
session_start();

echo "<h2>Session Test</h2><pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session save handler: " . ini_get('session.save_handler') . "\n";
echo "Session save path: " . ini_get('session.save_path') . "\n";
echo "Session cookie path: " . ini_get('session.cookie_path') . "\n";
echo "Session cookie domain: " . ini_get('session.cookie_domain') . "\n";
echo "Session cookie secure: " . ini_get('session.cookie_secure') . "\n";
echo "Session cookie samesite: " . ini_get('session.cookie_samesite') . "\n";
echo "Session cookie httponly: " . ini_get('session.cookie_httponly') . "\n\n";

// Set test variable
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 0;
}
$_SESSION['test_counter']++;
echo "Visit count: " . $_SESSION['test_counter'] . "\n";
echo "(Refresh page - counter should increase)\n\n";

// Check tpjd auth session
echo "tpjd_authenticated: " . (isset($_SESSION['tpjd_authenticated']) ? ($_SESSION['tpjd_authenticated'] ? 'TRUE' : 'FALSE') : 'NOT SET') . "\n";
echo "tpjd_login_time: " . ($_SESSION['tpjd_login_time'] ?? 'NOT SET') . "\n";
echo "tpjd_ip: " . ($_SESSION['tpjd_ip'] ?? 'NOT SET') . "\n\n";

// Cookie info
echo "=== Cookies ===\n";
echo "PHPSESSID cookie: " . (isset($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'] : 'NOT SET') . "\n";
echo "All cookies: " . print_r($_COOKIE, true) . "\n";

echo "</pre>";
echo "<p><a href='?'>Refresh to test session persistence</a></p>";
echo "<p><a href='api/login.php?action=status'>Check login status (API)</a></p>";
