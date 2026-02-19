<?php
require_once 'auth.php';

/**
 * WhatsApp Lokal Sunucu Kontrol Endpoint'i
 * ─────────────────────────────────────────
 * GET  ?action=start   → Node.js sunucuyu arka planda başlat
 * GET  ?action=status  → Sunucu çalışıyor mu kontrol et
 */

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'start':
        startWhatsAppServer();
        break;
    case 'status':
        checkServerRunning();
        break;
    default:
        sendResponse(false, 'Geçersiz action');
}

function startWhatsAppServer()
{
    // Önce sunucu zaten çalışıyor mu kontrol et
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $running = @file_get_contents('http://localhost:3456/status', false, $ctx);
    if ($running !== false) {
        sendResponse(true, 'Sunucu zaten çalışıyor', ['already_running' => true]);
        return;
    }

    // Paths
    $nodePath = 'C:\\Program Files\\nodejs\\node.exe';
    $serverDir = 'C:\\Users\\monster\\Desktop\\tpjd\\whatsapp-server';
    $serverScript = $serverDir . '\\server.js';

    if (!file_exists($nodePath)) {
        sendResponse(false, 'Node.js bulunamadı: ' . $nodePath);
        return;
    }

    if (!file_exists($serverScript)) {
        sendResponse(false, 'server.js bulunamadı: ' . $serverScript);
        return;
    }

    // Windows: start /B ile arka planda başlat — tam yol kullan
    // Not: Tırnak iç içe sorun yaratır — doğrudan start komutu kullan
    $cmd = 'start /B "" "' . $nodePath . '" "' . $serverScript . '"';

    // popen ile arka planda çalıştır
    $proc = popen($cmd, 'r');
    if ($proc) {
        pclose($proc);
    }

    // 4 saniye bekle ve kontrol et
    sleep(4);

    $ctx2 = stream_context_create(['http' => ['timeout' => 3]]);
    $check = @file_get_contents('http://localhost:3456/status', false, $ctx2);
    if ($check !== false) {
        sendResponse(true, 'WhatsApp sunucusu başlatıldı', ['started' => true]);
    } else {
        // Henüz hazır olmayabilir ama process başladı
        sendResponse(true, 'Sunucu başlatıldı, hazır olması birkaç saniye sürebilir', ['started' => true, 'warming_up' => true]);
    }
}

function checkServerRunning()
{
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $result = @file_get_contents('http://localhost:3456/status', false, $ctx);

    if ($result !== false) {
        $data = json_decode($result, true);
        sendResponse(true, 'Sunucu çalışıyor', [
            'running' => true,
            'status' => $data['status'] ?? 'unknown',
            'authenticated' => $data['authenticated'] ?? false,
            'info' => $data['info'] ?? null
        ]);
    } else {
        sendResponse(true, 'Sunucu kapalı', ['running' => false]);
    }
}
?>