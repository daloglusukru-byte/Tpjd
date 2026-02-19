<?php
/**
 * TPJD API Auth Guard
 * Tüm API endpoint'lerine dahil edilir (login.php hariç).
 * Session-based doğrulama yapar.
 */

// Session başlat (henüz başlamamışsa)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAuth()
{
    if (!isset($_SESSION['tpjd_authenticated']) || $_SESSION['tpjd_authenticated'] !== true) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Oturum geçersiz. Lütfen giriş yapın.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Guard'ı otomatik çalıştır
requireAuth();
