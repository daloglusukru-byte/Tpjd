<?php
/**
 * TPJD KVKK Reverse Migration Script
 * Şifreli ENC: verileri decrypt edip plaintext olarak geri yazar.
 * 
 * ⚠️ Çalıştırmadan önce mutlaka DB yedeği alın!
 */

require_once 'auth.php';
require_once '../config.php';
require_once 'kvkk.php';

// Sadece POST ile çalışsın
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Bu script sadece POST ile çalıştırılabilir');
    exit;
}

global $KVKK_FIELDS;

try {
    $result = $conn->query("SELECT id, " . implode(', ', $KVKK_FIELDS) . " FROM members");

    if (!$result) {
        throw new Exception('Üyeler okunamadı: ' . $conn->error);
    }

    $total = $result->num_rows;
    $decrypted = 0;
    $skipped = 0;
    $errors = [];

    while ($row = $result->fetch_assoc()) {
        $needsUpdate = false;
        $updates = [];
        $params = [];
        $types = '';

        foreach ($KVKK_FIELDS as $field) {
            if (isset($row[$field]) && $row[$field] !== null && $row[$field] !== '') {
                // Sadece ENC: ile başlayanları çöz
                if (substr($row[$field], 0, 4) !== 'ENC:') {
                    continue;
                }

                $decrypted_value = kvkk_decrypt($row[$field]);

                // Decrypt başarısız olduysa (hâlâ ENC: ile başlıyor) atla
                if (substr($decrypted_value, 0, 4) === 'ENC:') {
                    $errors[] = "ID {$row['id']}, alan $field: Decrypt başarısız";
                    continue;
                }

                $updates[] = "`$field` = ?";
                $params[] = $decrypted_value;
                $types .= 's';
                $needsUpdate = true;
            }
        }

        if ($needsUpdate && !empty($updates)) {
            $types .= 's';
            $params[] = $row['id'];

            $sql = "UPDATE members SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                $errors[] = "ID {$row['id']}: Sorgu hazırlanamadı";
                continue;
            }

            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $decrypted++;
            } else {
                $errors[] = "ID {$row['id']}: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $skipped++;
        }
    }

    kvkk_log_access('kvkk_reverse_migration', '', "Toplam: $total, Çözülen: $decrypted, Atlanan: $skipped, Hata: " . count($errors));

    sendResponse(true, 'Reverse migration tamamlandı — şifreleme kaldırıldı', [
        'total' => $total,
        'decrypted' => $decrypted,
        'skipped' => $skipped,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    logError('KVKK reverse migration error: ' . $e->getMessage());
    sendResponse(false, 'Reverse migration hatası: ' . $e->getMessage());
}
?>