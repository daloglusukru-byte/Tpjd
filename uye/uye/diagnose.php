<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>TPJD Server Diagnostics</h2>";
echo "<pre>";

// 1. PHP Version
echo "PHP Version: " . phpversion() . "\n";
echo "PHP SAPI: " . php_sapi_name() . "\n\n";

// 2. Required Extensions
$extensions = ['mysqli', 'json', 'openssl', 'mbstring', 'session'];
echo "=== Extensions ===\n";
foreach ($extensions as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? "OK" : "MISSING!") . "\n";
}
echo "\n";

// 3. Test config.php include
echo "=== Config Test ===\n";
try {
    require_once __DIR__ . '/config.php';
    echo "config.php: OK\n";
    echo "DB Connection: " . ($conn->connect_error ? "FAIL: " . $conn->connect_error : "OK") . "\n";

    // Test query
    $result = $conn->query("SELECT COUNT(*) as cnt FROM members");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Members count: " . $row['cnt'] . "\n";
    } else {
        echo "Members query failed: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "config.php ERROR: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "config.php FATAL: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Test auth.php include
echo "=== Auth Test ===\n";
try {
    // Don't actually require auth (it would block), just check file exists
    $authPath = __DIR__ . '/api/auth.php';
    echo "auth.php exists: " . (file_exists($authPath) ? "YES" : "NO - THIS IS THE PROBLEM!") . "\n";
    if (file_exists($authPath)) {
        echo "auth.php readable: " . (is_readable($authPath) ? "YES" : "NO") . "\n";
        echo "auth.php size: " . filesize($authPath) . " bytes\n";
        // Check for BOM
        $content = file_get_contents($authPath);
        $firstBytes = bin2hex(substr($content, 0, 10));
        echo "auth.php first bytes: $firstBytes\n";
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            echo "WARNING: auth.php has UTF-8 BOM!\n";
        }
    }
} catch (Exception $e) {
    echo "auth.php ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Test kvkk.php include
echo "=== KVKK Test ===\n";
try {
    $kvkkPath = __DIR__ . '/api/kvkk.php';
    echo "kvkk.php exists: " . (file_exists($kvkkPath) ? "YES" : "NO") . "\n";

    $kvkkKeyPath = __DIR__ . '/kvkk_key.php';
    echo "kvkk_key.php exists: " . (file_exists($kvkkKeyPath) ? "YES" : "NO") . "\n";
    if (file_exists($kvkkKeyPath)) {
        echo "kvkk_key.php size: " . filesize($kvkkKeyPath) . " bytes\n";
        $content = file_get_contents($kvkkKeyPath);
        $firstBytes = bin2hex(substr($content, 0, 10));
        echo "kvkk_key.php first bytes: $firstBytes\n";
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            echo "WARNING: kvkk_key.php has UTF-8 BOM!\n";
        }
    }
} catch (Exception $e) {
    echo "kvkk.php ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Check config.php for BOM
echo "=== BOM Check ===\n";
$configPath = __DIR__ . '/config.php';
$content = file_get_contents($configPath);
$firstBytes = bin2hex(substr($content, 0, 10));
echo "config.php first bytes: $firstBytes\n";
if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
    echo "WARNING: config.php has UTF-8 BOM!\n";
}

// 7. Check all PHP files in api/ for BOM
echo "\n=== API Files BOM Check ===\n";
$apiDir = __DIR__ . '/api/';
if (is_dir($apiDir)) {
    foreach (glob($apiDir . '*.php') as $file) {
        $content = file_get_contents($file);
        $hasBom = (substr($content, 0, 3) === "\xEF\xBB\xBF");
        $basename = basename($file);
        if ($hasBom) {
            echo "$basename: HAS BOM! (This causes 500 errors)\n";
        }
    }
    echo "BOM check complete.\n";
}

// 8. Check debug.log
echo "\n=== Debug Log (last 20 lines) ===\n";
$logPath = __DIR__ . '/../debug.log';
if (file_exists($logPath)) {
    $lines = file($logPath);
    $lastLines = array_slice($lines, -20);
    foreach ($lastLines as $line) {
        echo htmlspecialchars($line);
    }
} else {
    echo "debug.log not found at: $logPath\n";
    // Try alternative paths
    $altPath = __DIR__ . '/debug.log';
    if (file_exists($altPath)) {
        echo "Found at: $altPath\n";
        $lines = file($altPath);
        $lastLines = array_slice($lines, -20);
        foreach ($lastLines as $line) {
            echo htmlspecialchars($line);
        }
    }
}

// 9. Check error_log
echo "\n=== PHP Error Log ===\n";
$phpErrorLog = ini_get('error_log');
echo "PHP error_log path: $phpErrorLog\n";

echo "\n=== File Permissions ===\n";
$files = ['config.php', 'api/auth.php', 'api/members.php', 'api/kvkk.php', 'kvkk_key.php', 'api/settings.php'];
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        echo "$f: " . decoct(fileperms($path) & 0777) . "\n";
    } else {
        echo "$f: FILE NOT FOUND!\n";
    }
}

echo "</pre>";
