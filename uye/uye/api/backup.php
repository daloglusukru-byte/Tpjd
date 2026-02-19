<?php
/**
 * TPJD Otomatik Veritabanı Yedekleme
 * 
 * Kullanım:
 *   Manuel: POST /api/backup.php?action=create
 *   Cron:   php /path/to/api/backup.php cron
 *   Liste:  GET  /api/backup.php?action=list
 *   İndir:  GET  /api/backup.php?action=download&file=backup_2026-02-18.json
 *   Sil:    POST /api/backup.php?action=delete  {file: "backup_...json"}
 */

// Cron modunda auth bypass (komut satırından çalıştırılıyor)
$isCron = (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'cron');

if (!$isCron) {
    require_once 'auth.php';
}
require_once '../config.php';

// Yedek dizini
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    // .htaccess ile web erişimini engelle
    file_put_contents($backupDir . '/.htaccess', "Deny from all\n");
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $isCron ? 'create' : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'create':
        createBackup();
        break;
    case 'list':
        listBackups();
        break;
    case 'download':
        downloadBackup();
        break;
    case 'delete':
        deleteBackup();
        break;
    default:
        sendResponse(false, 'Geçersiz action. Kullanım: create, list, download, delete');
}

function createBackup()
{
    global $conn, $backupDir, $isCron;

    try {
        $backup = [
            'version' => '1.0',
            'created_at' => date('Y-m-d H:i:s'),
            'source' => $isCron ? 'cron' : 'manual',
            'tables' => []
        ];

        // Yedeklenecek tablolar
        $tables = ['members', 'payments', 'notifications', 'settings', 'agent_logs', 'access_logs', 'member_consents', 'login_attempts'];

        foreach ($tables as $table) {
            $result = $conn->query("SELECT * FROM `$table`");
            if ($result) {
                $rows = [];
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
                $backup['tables'][$table] = [
                    'count' => count($rows),
                    'data' => $rows
                ];
            }
            // Tablo yoksa sessizce atla
        }

        // Dosya adı: backup_YYYY-MM-DD_HHmmss.json
        $filename = 'backup_' . date('Y-m-d_His') . '.json';
        $filepath = $backupDir . '/' . $filename;

        $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($filepath, $json) === false) {
            throw new Exception('Yedek dosyası yazılamadı');
        }

        // Eski yedekleri temizle (30 günden eski)
        cleanOldBackups($backupDir, 30);

        $sizeKB = round(filesize($filepath) / 1024, 1);
        $totalRows = 0;
        foreach ($backup['tables'] as $t) {
            $totalRows += $t['count'];
        }

        $message = "Yedek oluşturuldu: $filename ({$sizeKB}KB, $totalRows kayıt)";

        if ($isCron) {
            echo date('[Y-m-d H:i:s] ') . $message . "\n";
        } else {
            sendResponse(true, $message, [
                'filename' => $filename,
                'size_kb' => $sizeKB,
                'total_rows' => $totalRows,
                'tables' => array_map(function ($t) {
                    return $t['count']; }, $backup['tables'])
            ]);
        }

    } catch (Exception $e) {
        if ($isCron) {
            echo date('[Y-m-d H:i:s] ') . 'HATA: ' . $e->getMessage() . "\n";
            exit(1);
        } else {
            logError('Backup error: ' . $e->getMessage());
            sendResponse(false, 'Yedekleme hatası: ' . $e->getMessage());
        }
    }
}

function listBackups()
{
    global $backupDir;

    $backups = [];
    $files = glob($backupDir . '/backup_*.json');

    if ($files) {
        // En yeniden en eskiye sırala
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a); });

        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size_kb' => round(filesize($file) / 1024, 1),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
                'age_days' => floor((time() - filemtime($file)) / 86400)
            ];
        }
    }

    sendResponse(true, count($backups) . ' yedek bulundu', $backups);
}

function downloadBackup()
{
    global $backupDir;

    $file = isset($_GET['file']) ? basename($_GET['file']) : '';

    if (empty($file) || !preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{6}\.json$/', $file)) {
        sendResponse(false, 'Geçersiz dosya adı');
        return;
    }

    $filepath = $backupDir . '/' . $file;

    if (!file_exists($filepath)) {
        sendResponse(false, 'Yedek dosyası bulunamadı');
        return;
    }

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

function deleteBackup()
{
    global $backupDir;

    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    $file = isset($data['file']) ? basename($data['file']) : '';

    if (empty($file) || !preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{6}\.json$/', $file)) {
        sendResponse(false, 'Geçersiz dosya adı');
        return;
    }

    $filepath = $backupDir . '/' . $file;

    if (!file_exists($filepath)) {
        sendResponse(false, 'Yedek dosyası bulunamadı');
        return;
    }

    if (unlink($filepath)) {
        sendResponse(true, 'Yedek silindi: ' . $file);
    } else {
        sendResponse(false, 'Yedek silinemedi');
    }
}

function cleanOldBackups($dir, $maxDays)
{
    $cutoff = time() - ($maxDays * 86400);
    $files = glob($dir . '/backup_*.json');

    if ($files) {
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}
?>