<?php
// Database Configuration
$servername = "localhost";
$username = "u550249498_tpjd";
$password = "Tpjd2026Test!";
$dbname = "u550249498_tpjd";

// Timezone — Türkiye (UTC+3)
date_default_timezone_set('Europe/Istanbul');

define('DB_HOST', $servername);
define('DB_USER', $username);
define('DB_PASS', $password);
define('DB_NAME', $dbname);

// Create connection with try-catch for PHP 8+ strict mode
try {
    mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
} catch (\mysqli_sql_exception $e) {
    error_log('[TPJD DB Error] ' . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => false,
        'message' => 'Veritabanı bağlantısı kurulamadı. config.php içindeki şifreleri sunucuya (Turhost) göre düzenlememiş olabilirsiniz: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE));
}

// Set charset to utf8mb4 (already done in try)
$conn->set_charset("utf8mb4");

// Enable error reporting but suppress HTML output; log to file instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../debug.log');

// Set response header
header('Content-Type: application/json; charset=utf-8');
// CORS — sadece izin verilen origin'ler
$allowedOrigins = ['https://uye.tpjd.org.tr', 'https://tpjd.org.tr', 'https://www.tpjd.org.tr', 'http://localhost', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://uye.tpjd.org.tr');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Helper function to send JSON response
function sendResponse($success, $message = '', $data = null)
{
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

// Helper function to log errors
function logError($error)
{
    error_log('[TPJD Error] ' . date('Y-m-d H:i:s') . ' - ' . $error);
}
