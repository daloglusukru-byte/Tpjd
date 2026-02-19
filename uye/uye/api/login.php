<?php
// Session başlat ÖNCE (config.php'den output gelmeden)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';


$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST' && $method !== 'GET') {
    sendResponse(false, 'Invalid request method');
}

$data = [];

if ($method === 'POST') {
    $rawInput = file_get_contents("php://input");

    if (isset($_POST['username'])) {
        $data = $_POST;
    } else {
        $data = json_decode($rawInput, true);
    }

    if (!$data || !is_array($data)) {
        $parsed = [];
        parse_str($rawInput, $parsed);
        if (!empty($parsed)) {
            $data = $parsed;
        } elseif (!empty($_REQUEST)) {
            $data = $_REQUEST;
        }
    }
} else {
    $data = $_GET;
}

// ─── Logout ────────────────────────────────────────────
$action = $data['action'] ?? ($_GET['action'] ?? '');
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    sendResponse(true, 'Çıkış yapıldı');
}

// ─── Login Status Kontrolü ─────────────────────────────
if ($action === 'status') {
    $isAuth = isset($_SESSION['tpjd_authenticated']) && $_SESSION['tpjd_authenticated'] === true;
    sendResponse($isAuth, $isAuth ? 'Oturum aktif' : 'Oturum yok');
}

if (!isset($data['username']) || !isset($data['password'])) {
    sendResponse(false, 'Username and password are required');
}

$username = $data['username'];
$password = $data['password'];

// ─── Rate Limiting ─────────────────────────────────────
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// login_attempts tablosunu oluştur (yoksa)
$conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Son 5 dakikadaki başarısız denemeleri say
$rateSql = "SELECT COUNT(*) as cnt FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
$rateStmt = $conn->prepare($rateSql);
$rateStmt->bind_param('s', $clientIp);
$rateStmt->execute();
$rateResult = $rateStmt->get_result()->fetch_assoc();
$rateStmt->close();

if ($rateResult['cnt'] >= 5) {
    http_response_code(429);
    sendResponse(false, 'Çok fazla başarısız deneme. Lütfen 5 dakika bekleyin.');
}

// ─── Login Doğrulama ───────────────────────────────────
global $conn;

try {
    // Get admin_username from settings
    $sql_user = "SELECT setting_value FROM settings WHERE setting_key = 'admin_username'";
    $result_user = $conn->query($sql_user);
    if (!$result_user || $result_user->num_rows === 0) {
        throw new Exception('Admin username not found in settings.');
    }
    $stored_username = json_decode($result_user->fetch_assoc()['setting_value'], true);
    if (is_array($stored_username)) {
        $stored_username = reset($stored_username);
    }
    $stored_username = is_string($stored_username) ? trim($stored_username) : '';

    // Get admin_password from settings
    $sql_pass = "SELECT setting_value FROM settings WHERE setting_key = 'admin_password'";
    $result_pass = $conn->query($sql_pass);
    if (!$result_pass || $result_pass->num_rows === 0) {
        throw new Exception('Admin password not found in settings.');
    }
    $stored_password_value = json_decode($result_pass->fetch_assoc()['setting_value'], true);
    if (is_array($stored_password_value)) {
        $stored_password_value = reset($stored_password_value);
    }
    if (is_string($stored_password_value)) {
        $stored_password_value = trim($stored_password_value);
    }

    logError('login attempt: user="' . $username . '" ip=' . $clientIp);

    // Verify username
    $trimmedUser = trim((string) $username);
    $trimmedStoredUser = trim((string) $stored_username);

    if ($trimmedUser !== $trimmedStoredUser) {
        logError('Username mismatch: "' . $trimmedUser . '"');
        // Başarısız denemeyi kaydet
        $failStmt = $conn->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
        $failStmt->bind_param('s', $clientIp);
        $failStmt->execute();
        $failStmt->close();
        sendResponse(false, 'Invalid username or password');
        // sendResponse içinde exit var, ama güvenlik için:
        exit;
    }

    // Check if it's already a valid bcrypt hash
    $isBcrypt = (substr((string) $stored_password_value, 0, 4) === '$2y$');

    // If password was stored in plain text, migrate to hash after validating
    if (!$isBcrypt) {
        $inputPass = trim((string) $password);
        $storedPass = trim((string) $stored_password_value);

        if ($inputPass === $storedPass) {
            logError('Password migrated to bcrypt hash');
            $newHash = password_hash($inputPass, PASSWORD_DEFAULT);
            $escaped = $conn->real_escape_string(json_encode($newHash, JSON_UNESCAPED_UNICODE));
            $updateSql = "UPDATE settings SET setting_value = '$escaped' WHERE setting_key = 'admin_password'";
            if (!$conn->query($updateSql)) {
                throw new Exception('Password migration failed: ' . $conn->error);
            }
            // Başarılı login — session oluştur
            $_SESSION['tpjd_authenticated'] = true;
            $_SESSION['tpjd_login_time'] = time();
            $_SESSION['tpjd_ip'] = $clientIp;
            // Eski denemeleri temizle
            $cleanStmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
            $cleanStmt->bind_param('s', $clientIp);
            $cleanStmt->execute();
            $cleanStmt->close();
            sendResponse(true, 'Login successful (migrated)');
            exit;
        } else {
            logError('Plain text password mismatch');
            $failStmt = $conn->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
            $failStmt->bind_param('s', $clientIp);
            $failStmt->execute();
            $failStmt->close();
            sendResponse(false, 'Invalid username or password');
            exit;
        }
    }

    // Hashed password verification
    if ($isBcrypt && password_verify(trim((string) $password), (string) $stored_password_value)) {
        // Başarılı login — session oluştur
        $_SESSION['tpjd_authenticated'] = true;
        $_SESSION['tpjd_login_time'] = time();
        $_SESSION['tpjd_ip'] = $clientIp;
        // Eski denemeleri temizle
        $cleanStmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $cleanStmt->bind_param('s', $clientIp);
        $cleanStmt->execute();
        $cleanStmt->close();
        sendResponse(true, 'Login successful (hash)');
        exit;
    }

    // Şifre yanlış
    $failStmt = $conn->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
    $failStmt->bind_param('s', $clientIp);
    $failStmt->execute();
    $failStmt->close();
    sendResponse(false, 'Invalid username or password');
    exit;

} catch (Exception $e) {
    logError($e->getMessage());
    sendResponse(false, 'An error occurred during login: ' . $e->getMessage());
}
?>