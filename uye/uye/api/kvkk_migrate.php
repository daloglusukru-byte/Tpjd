<?php
/**
 * TPJD KVKK Veri Migration Script
 * Mevcut plaintext üye verilerini AES-256-CBC ile şifreler.
 * 
 * ⚠️ BU SCRIPT SADECE BİR KEZ ÇALIŞTIRILMALIDIR!
 * ⚠️ Çalıştırmadan önce mutlaka DB yedeği alın!
 */

require_once 'auth.php';     // Sadece yetkili kullanıcı çalıştırabilsin
require_once '../config.php';
require_once 'kvkk.php';

// Sadece POST ile çalışsın (kazara GET ile tetiklenmesin)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Bu script sadece POST ile çalıştırılabilir');
    exit;
}

global $KVKK_FIELDS;

try {
    // Tüm üyeleri oku
    $result = $conn->query("SELECT id, " . implode(', ', $KVKK_FIELDS) . " FROM members");

    if (!$result) {
        throw new Exception('Üyeler okunamadı: ' . $conn->error);
    }

    $total = $result->num_rows;
    $encrypted = 0;
    $skipped = 0;
    $errors = [];

    while ($row = $result->fetch_assoc()) {
        $needsUpdate = false;
        $updates = [];
        $params = [];
        $types = '';

        foreach ($KVKK_FIELDS as $field) {
            if (isset($row[$field]) && $row[$field] !== null && $row[$field] !== '') {
                // Zaten şifreli mi kontrol et
                if (substr($row[$field], 0, 4) === 'ENC:') {
                    continue; // Zaten şifreli, atla
                }

                $encrypted_value = kvkk_encrypt($row[$field]);
                $updates[] = "`$field` = ?";
                $params[] = $encrypted_value;
                $types .= 's';
                $needsUpdate = true;
            }
        }

        if ($needsUpdate && !empty($updates)) {
            $types .= 's'; // id için
            $params[] = $row['id'];

            $sql = "UPDATE members SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                $errors[] = "ID {$row['id']}: Sorgu hazırlanamadı";
                continue;
            }

            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $encrypted++;
                // Her üye için consent kaydı oluştur
                kvkk_create_default_consent($row['id']);
            } else {
                $errors[] = "ID {$row['id']}: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $skipped++;
        }
    }

    kvkk_log_access('kvkk_migration', '', "Toplam: $total, Şifrelenen: $encrypted, Atlanan: $skipped, Hata: " . count($errors));

    sendResponse(true, 'KVKK migration tamamlandı', [
        'total' => $total,
        'encrypted' => $encrypted,
        'skipped' => $skipped,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    logError('KVKK migration error: ' . $e->getMessage());
    sendResponse(false, 'Migration hatası: ' . $e->getMessage());
}
?>