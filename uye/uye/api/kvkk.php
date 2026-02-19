<?php
/**
 * TPJD KVKK Uyum Modülü
 * AES-256-CBC şifreleme, maskeleme, erişim logları ve consent yönetimi
 */

require_once __DIR__ . '/../kvkk_key.php';

// ─── Şifrelenecek Hassas Alanlar ──────────────────────────
$KVKK_FIELDS = [
    'tcNo',
    'phone',
    'phoneHome',
    'address',
    'bloodType',
    'fatherName',
    'motherName',
    'volumeNo',
    'familyNo',
    'lineNo',
    'birthDate'
];

// ─── Tablo Oluşturma (IF NOT EXISTS) ──────────────────────
function kvkk_ensure_tables()
{
    global $conn;

    $conn->query("CREATE TABLE IF NOT EXISTS access_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_ip VARCHAR(45),
        session_id VARCHAR(128),
        action VARCHAR(50) NOT NULL,
        target_type VARCHAR(30),
        target_id VARCHAR(50),
        details TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action (action),
        INDEX idx_target (target_type, target_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS member_consents (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        member_id VARCHAR(50) NOT NULL,
        consent_type VARCHAR(50) NOT NULL,
        consent_given BOOLEAN DEFAULT FALSE,
        given_at DATETIME,
        withdrawn_at DATETIME,
        ip_address VARCHAR(45),
        notes TEXT,
        INDEX idx_member (member_id),
        INDEX idx_type (consent_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// İlk yüklemede tabloları oluştur (hata çıktısı JSON'ı bozmasın)
try {
    @kvkk_ensure_tables();
} catch (Exception $e) {
    error_log('KVKK table init error: ' . $e->getMessage());
}

// ─── AES-256-CBC Şifreleme ────────────────────────────────
function kvkk_encrypt($value)
{
    if ($value === null || $value === '')
        return $value;

    // Zaten şifreli ise tekrar şifreleme (çift şifreleme koruması)
    if (substr($value, 0, 4) === 'ENC:')
        return $value;

    // Maskelenmiş veri KESİNLİKLE şifrelenmemeli (veri kaybı koruması)
    if (strpos($value, '*') !== false)
        return $value;

    $key = substr(hash('sha256', KVKK_ENCRYPTION_KEY, true), 0, 32);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);

    if ($encrypted === false)
        return $value; // Şifreleme başarısızsa orijinal döndür

    // IV + encrypted (base64) formatında sakla, prefix ile şifreli olduğu belli olsun
    return 'ENC:' . base64_encode($iv) . ':' . $encrypted;
}

function kvkk_decrypt($value)
{
    if ($value === null || $value === '')
        return $value;

    // Şifreli değilse olduğu gibi döndür
    if (substr($value, 0, 4) !== 'ENC:')
        return $value;

    $key = substr(hash('sha256', KVKK_ENCRYPTION_KEY, true), 0, 32);
    $parts = explode(':', $value, 3);

    if (count($parts) !== 3)
        return $value;

    $iv = base64_decode($parts[1]);
    $encrypted = $parts[2];

    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);

    return $decrypted !== false ? $decrypted : $value;
}

// ─── Toplu Şifreleme/Çözme ───────────────────────────────
function kvkk_encrypt_fields(&$data)
{
    global $KVKK_FIELDS;
    foreach ($KVKK_FIELDS as $field) {
        if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
            $data[$field] = kvkk_encrypt($data[$field]);
        }
    }
}

function kvkk_decrypt_fields(&$data)
{
    global $KVKK_FIELDS;
    if (!is_array($data))
        return;
    foreach ($KVKK_FIELDS as $field) {
        if (isset($data[$field]) && $data[$field] !== null) {
            $data[$field] = kvkk_decrypt($data[$field]);
        }
    }
}

// ─── Maskeleme (Liste Görünümü İçin) ──────────────────────
function kvkk_mask($value, $type = 'generic')
{
    if ($value === null || $value === '')
        return $value;

    // Önce decrypt et (şifreli olabilir)
    $plain = kvkk_decrypt($value);

    switch ($type) {
        case 'tc':
            // *******1234 formatı (son 4 hane görünür, geri kalan yıldız)
            if (strlen($plain) >= 4) {
                return str_repeat('*', strlen($plain) - 4) . substr($plain, -4);
            }
            return '***';

        case 'phone':
            // ***89 50 formatı
            if (strlen($plain) >= 4) {
                return '***' . substr($plain, -4, 2) . ' ' . substr($plain, -2);
            }
            return '***';

        case 'email':
            // a***@domain.com
            $parts = explode('@', $plain);
            if (count($parts) === 2) {
                $name = $parts[0];
                if (strlen($name) > 2) {
                    return substr($name, 0, 1) . '***@' . $parts[1];
                }
                return '***@' . $parts[1];
            }
            return '***';

        case 'address':
            if (strlen($plain) > 10) {
                return substr($plain, 0, 10) . '***';
            }
            return '***';

        default:
            if (strlen($plain) > 3) {
                return '***' . substr($plain, -3);
            }
            return '***';
    }
}

// Dizi üzerinde toplu maskeleme
function kvkk_mask_fields(&$data)
{
    if (!is_array($data))
        return;

    // phone, phoneHome, address operasyonel ihtiyaçlar için maskelenmez
    $maskMap = [
        'tcNo' => 'tc',
        'bloodType' => 'generic',
        'fatherName' => 'generic',
        'motherName' => 'generic',
        'volumeNo' => 'generic',
        'familyNo' => 'generic',
        'lineNo' => 'generic',
        'birthDate' => 'generic'
    ];

    // phone, phoneHome, address → sadece decrypt et, maskeleme
    $decryptOnly = ['phone', 'phoneHome', 'address'];
    foreach ($decryptOnly as $field) {
        if (isset($data[$field]) && $data[$field] !== null) {
            $data[$field] = kvkk_decrypt($data[$field]);
        }
    }

    foreach ($maskMap as $field => $type) {
        if (isset($data[$field]) && $data[$field] !== null) {
            $data[$field] = kvkk_mask($data[$field], $type);
        }
    }
}

// ─── Erişim Logu ──────────────────────────────────────────
function kvkk_log_access($action, $targetId = '', $details = '', $targetType = 'member')
{
    global $conn;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $sessionId = session_id() ?: '';

    try {
        $stmt = $conn->prepare("INSERT INTO access_logs (user_ip, session_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssssss', $ip, $sessionId, $action, $targetType, $targetId, $details);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        // Log kaydı başarısız olsa bile ana işlemi etkilemesin
        error_log('KVKK log error: ' . $e->getMessage());
    }
}

// ─── Consent (Onay) Yönetimi ─────────────────────────────
function kvkk_save_consent($memberId, $consentType, $given, $notes = '')
{
    global $conn;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $now = date('Y-m-d H:i:s');

    // Varsa güncelle, yoksa ekle
    $checkStmt = $conn->prepare("SELECT id FROM member_consents WHERE member_id = ? AND consent_type = ?");
    $checkStmt->bind_param('ss', $memberId, $consentType);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($existing) {
        if ($given) {
            $stmt = $conn->prepare("UPDATE member_consents SET consent_given = 1, given_at = ?, ip_address = ?, notes = ?, withdrawn_at = NULL WHERE id = ?");
            $stmt->bind_param('sssi', $now, $ip, $notes, $existing['id']);
        } else {
            $stmt = $conn->prepare("UPDATE member_consents SET consent_given = 0, withdrawn_at = ?, ip_address = ?, notes = ? WHERE id = ?");
            $stmt->bind_param('sssi', $now, $ip, $notes, $existing['id']);
        }
    } else {
        $givenAt = $given ? $now : null;
        $givenBool = $given ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO member_consents (member_id, consent_type, consent_given, given_at, ip_address, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssisss', $memberId, $consentType, $givenBool, $givenAt, $ip, $notes);
    }

    $stmt->execute();
    $stmt->close();
}

function kvkk_get_consents($memberId)
{
    global $conn;

    $stmt = $conn->prepare("SELECT consent_type, consent_given, given_at, withdrawn_at FROM member_consents WHERE member_id = ?");
    $stmt->bind_param('s', $memberId);
    $stmt->execute();
    $result = $stmt->get_result();

    $consents = [];
    while ($row = $result->fetch_assoc()) {
        $consents[$row['consent_type']] = [
            'given' => (bool) $row['consent_given'],
            'given_at' => $row['given_at'],
            'withdrawn_at' => $row['withdrawn_at']
        ];
    }
    $stmt->close();

    return $consents;
}

// Toplu kayıt için varsayılan consent (YK kararı)
function kvkk_create_default_consent($memberId)
{
    kvkk_save_consent($memberId, 'kvkk_genel', true, 'Yönetim Kurulu kararıyla kayıt');
    kvkk_save_consent($memberId, 'email_izin', true, 'Üyelik kaydı sırasında');
}

// ─── Anonimleştirme ──────────────────────────────────────
function kvkk_anonymize_member($memberId)
{
    global $conn;

    $stmt = $conn->prepare("UPDATE members SET 
        firstName = 'Anonim', lastName = 'Üye',
        tcNo = ?, phone = '', phoneHome = '', 
        address = '', bloodType = '', email = ?,
        fatherName = '', motherName = '',
        volumeNo = '', familyNo = '', lineNo = '',
        birthDate = NULL, birthCity = '', birthDistrict = '',
        city = '', district = '', neighborhood = '',
        notes = 'KVKK kapsamında anonimleştirildi',
        membershipStatus = 'ayrıldı'
        WHERE id = ?");

    // Benzersiz anonimleştirilmiş değerler (UNIQUE constraint'ler için)
    $anonTc = 'ANON_' . substr(md5($memberId), 0, 6);
    $anonEmail = 'anon_' . substr(md5($memberId), 0, 8) . '@deleted.tpjd';

    $stmt->bind_param('sss', $anonTc, $anonEmail, $memberId);
    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        // Consent'ları da temizle
        $delStmt = $conn->prepare("DELETE FROM member_consents WHERE member_id = ?");
        $delStmt->bind_param('s', $memberId);
        $delStmt->execute();
        $delStmt->close();

        // Log kaydet
        kvkk_log_access('anonymize_member', $memberId, 'Üye KVKK kapsamında anonimleştirildi');
    }

    return $result;
}

// ─── Erişim Loglarını Listele ─────────────────────────────
function kvkk_get_access_logs($limit = 100, $targetId = '')
{
    global $conn;

    if ($targetId) {
        $stmt = $conn->prepare("SELECT * FROM access_logs WHERE target_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param('si', $targetId, $limit);
    } else {
        $stmt = $conn->prepare("SELECT * FROM access_logs ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param('i', $limit);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();

    return $logs;
}
?>