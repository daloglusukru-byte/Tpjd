<?php
require_once 'auth.php';
require_once '../config.php';
require_once 'kvkk.php';


function generate_member_id()
{
    return uniqid('m_', true);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            getMembers();
        } elseif ($action === 'get') {
            getMember();
        } elseif ($action === 'consent_get') {
            getConsent();
        } elseif ($action === 'access_logs') {
            getAccessLogs();
        } else {
            getMembers();
        }
        break;
    case 'POST':
        $raw_input = file_get_contents("php://input");
        $data = json_decode($raw_input, true);

        if ($data === null && !empty($raw_input)) {
            sendResponse(false, 'JSON çözümleme hatası: ' . json_last_error_msg());
            break;
        }

        // Fallback for environments where php://input is empty
        if (empty($data)) {
            $data = $_POST;
        }

        if (!$data) {
            sendResponse(false, 'Veri alınamadı (JSON boş veya geçersiz)');
            break;
        }

        if (isset($data['action']) && $data['action'] === 'saveAll') {
            saveAllMembers($data['data']);
        } elseif (isset($data['action']) && $data['action'] === 'consent_save') {
            saveConsent($data);
        } elseif (isset($data['action']) && $data['action'] === 'anonymize') {
            anonymizeMember($data);
        } else {
            addMember($data);
        }
        break;
    case 'PUT':
        updateMember();
        break;
    case 'DELETE':
        deleteMember();
        break;
    default:
        sendResponse(false, 'Geçersiz istek yöntemi');
}

function getMembers()
{
    global $conn;

    try {
        $sql = "SELECT * FROM members ORDER BY createdAt DESC";
        $result = $conn->query($sql);

        if (!$result) {
            throw new Exception($conn->error);
        }

        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }

        sendResponse(true, 'Üyeler başarıyla alındı', $members);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Üyeler alınırken hata oluştu: ' . $e->getMessage());
    }
}

function getMember()
{
    global $conn;

    $id = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($id)) {
        sendResponse(false, 'Üye ID gerekli');
    }

    try {
        $sql = "SELECT * FROM members WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception($conn->error);
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        $member = $result->fetch_assoc();
        $stmt->close();

        if (!$member) {
            sendResponse(false, 'Üye bulunamadı');
        }

        kvkk_log_access('view_member', $id, 'Üye detayı görüntülendi');

        sendResponse(true, 'Üye başarıyla alındı', $member);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Üye alınırken hata oluştu: ' . $e->getMessage());
    }
}

function addMember($data)
{
    global $conn;

    if (!$data) {
        sendResponse(false, 'Geçersiz JSON verisi');
        return;
    }

    // Backend fallback: generate ID if missing/null
    if (empty($data['id'])) {
        $data['id'] = generate_member_id();
    }

    $required = ['memberNo', 'firstName', 'lastName', 'tcNo', 'email', 'phone', 'membershipType'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendResponse(false, "Gerekli alan eksik: $field");
            return;
        }
    }

    try {

        $sql = "INSERT INTO members (
            id, memberNo, firstName, lastName, tcNo, gender, birthDate, bloodType, 
            email, phone, phoneHome, maritalStatus, city, district, neighborhood, address,
            fatherName, motherName, graduation, graduationYear, employmentStatus, workplace,
            position, birthCity, birthDistrict, volumeNo, familyNo, lineNo, notes,
            membershipType, aidatAktif, membershipStatus, exitDate, exitReasonType, exitReason, 
            registrationDate, createdAt, updatedAt
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Sorgu hazırlanamadı: ' . $conn->error);
        }

        $id = $data['id'];
        $memberNo = $data['memberNo'];
        $firstName = $data['firstName'];
        $lastName = $data['lastName'];
        $tcNo = $data['tcNo'];
        $gender = $data['gender'] ?: null;
        $birthDate = $data['birthDate'] ?: null;
        $bloodType = $data['bloodType'] ?: null;
        $email = $data['email'];
        $phone = $data['phone'];
        $phoneHome = $data['phoneHome'] ?: null;
        $maritalStatus = $data['maritalStatus'] ?: null;
        $city = $data['city'] ?? '';
        $district = $data['district'] ?? '';
        $neighborhood = $data['neighborhood'] ?? '';
        $address = $data['address'] ?? '';
        $fatherName = $data['fatherName'] ?: null;
        $motherName = $data['motherName'] ?: null;
        $graduation = $data['graduation'] ?: null;
        $graduationYear = !empty($data['graduationYear']) ? (int) $data['graduationYear'] : null;
        $employmentStatus = $data['employmentStatus'] ?: null;
        $workplace = $data['workplace'] ?: null;
        $position = $data['position'] ?: null;
        $birthCity = $data['birthCity'] ?: null;
        $birthDistrict = $data['birthDistrict'] ?: null;
        $volumeNo = $data['volumeNo'] ?: null;
        $familyNo = $data['familyNo'] ?: null;
        $lineNo = $data['lineNo'] ?: null;
        $notes = $data['notes'] ?: null;
        $membershipType = $data['membershipType'];
        $aidatAktif = isset($data['aidatAktif']) ? (int) $data['aidatAktif'] : 1;
        $membershipStatus = $data['membershipStatus'] ?? 'aktif';
        $exitDate = $data['exitDate'] ?: null;
        $exitReasonType = $data['exitReasonType'] ?: null;
        $exitReason = $data['exitReason'] ?: null;
        $registrationDate = $data['registrationDate'] ?: null;

        // 36 placeholders before NOW(), NOW(): bind all as strings to allow NULLs safely
        $types = str_repeat('s', 36);
        $stmt->bind_param(
            $types,
            $id,
            $memberNo,
            $firstName,
            $lastName,
            $tcNo,
            $gender,
            $birthDate,
            $bloodType,
            $email,
            $phone,
            $phoneHome,
            $maritalStatus,
            $city,
            $district,
            $neighborhood,
            $address,
            $fatherName,
            $motherName,
            $graduation,
            $graduationYear,
            $employmentStatus,
            $workplace,
            $position,
            $birthCity,
            $birthDistrict,
            $volumeNo,
            $familyNo,
            $lineNo,
            $notes,
            $membershipType,
            $aidatAktif,
            $membershipStatus,
            $exitDate,
            $exitReasonType,
            $exitReason,
            $registrationDate
        );

        if (!$stmt->execute()) {
            throw new Exception('Sorgu çalıştırılamadı: ' . $stmt->error);
        }

        $stmt->close();

        // KVKK: Varsayılan consent kaydı oluştur
        kvkk_create_default_consent($id);
        kvkk_log_access('add_member', $id, 'Yeni üye eklendi');

        sendResponse(true, 'Üye başarıyla eklendi', ['id' => $id]);

    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Üye eklenirken hata oluştu: ' . $e->getMessage());
    }
}

function updateMember()
{
    global $conn;

    $raw_input = file_get_contents("php://input");
    $data = json_decode($raw_input, true);

    if ($data === null && !empty($raw_input)) {
        sendResponse(false, 'JSON çözümleme hatası (PUT): ' . json_last_error_msg());
        return;
    }

    if (!$data || empty($data['id'])) {
        sendResponse(false, 'Üye ID gerekli');
        return;
    }

    try {
        $fields = [
            'memberNo',
            'firstName',
            'lastName',
            'tcNo',
            'gender',
            'birthDate',
            'bloodType',
            'email',
            'phone',
            'phoneHome',
            'maritalStatus',
            'city',
            'district',
            'neighborhood',
            'address',
            'fatherName',
            'motherName',
            'graduation',
            'graduationYear',
            'employmentStatus',
            'workplace',
            'position',
            'birthCity',
            'birthDistrict',
            'volumeNo',
            'familyNo',
            'lineNo',
            'notes',
            'membershipType',
            'aidatAktif',
            'membershipStatus',
            'exitDate',
            'exitReasonType',
            'exitReason',
            'registrationDate'
        ];

        $updates = [];
        $params = [];
        $types = '';

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                $updates[] = "`$field` = ?";
                $params[] = $value;
                if ($field === 'graduationYear' || $field === 'aidatAktif') {
                    $types .= 'i';
                } else {
                    $types .= 's';
                }
            }
        }

        if (empty($updates)) {
            sendResponse(false, 'Güncellenecek alan yok');
            return;
        }

        // Add the ID to the end of the params array for the WHERE clause
        $types .= 's';
        $params[] = $data['id'];

        $sql = "UPDATE `members` SET " . implode(', ', $updates) . " WHERE `id` = ?";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Sorgu hazırlanamadı: ' . $conn->error);
        }

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception('Sorgu çalıştırılamadı: ' . $stmt->error);
        }

        $stmt->close();

        // KVKK: Erişim logu
        kvkk_log_access('update_member', $data['id'], 'Üye bilgileri güncellendi');

        sendResponse(true, 'Üye başarıyla güncellendi');

    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Üye güncellenirken hata oluştu: ' . $e->getMessage());
    }
}

function deleteMember()
{
    global $conn;

    $id = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($id)) {
        sendResponse(false, 'Üye ID gerekli');
        return;
    }

    $conn->begin_transaction();

    try {
        // First, delete associated payments
        $stmt1 = $conn->prepare("DELETE FROM payments WHERE memberId = ?");
        if (!$stmt1)
            throw new Exception($conn->error);
        $stmt1->bind_param('s', $id);
        if (!$stmt1->execute()) {
            throw new Exception('Ödemeler silinirken hata oluştu: ' . $stmt1->error);
        }
        $stmt1->close();

        // Then, delete the member
        $stmt2 = $conn->prepare("DELETE FROM members WHERE id = ?");
        if (!$stmt2)
            throw new Exception($conn->error);
        $stmt2->bind_param('s', $id);
        if (!$stmt2->execute()) {
            throw new Exception('Üye silinirken hata oluştu: ' . $stmt2->error);
        }
        $stmt2->close();

        $conn->commit();

        // KVKK: Erişim logu
        kvkk_log_access('delete_member', $id, 'Üye ve ödemeleri silindi');

        sendResponse(true, 'Üye ve ilişkili ödemeleri başarıyla silindi');

    } catch (Exception $e) {
        $conn->rollback();
        logError($e->getMessage());
        sendResponse(false, 'Üye silinirken bir hata oluştu: ' . $e->getMessage());
    }
}

function saveAllMembers($membersData)
{
    global $conn;

    if (!is_array($membersData) || empty($membersData)) {
        sendResponse(false, 'Geçersiz veya boş veri formatı');
        return;
    }

    try {
        $conn->begin_transaction();

        // First, truncate the table to ensure a clean slate, as per user request for sample data.
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        $conn->query("TRUNCATE TABLE members");
        $conn->query("SET FOREIGN_KEY_CHECKS=1");

        $sql = "INSERT INTO members (id, memberNo, firstName, lastName, tcNo, gender, birthDate, bloodType, email, phone, phoneHome, maritalStatus, city, district, neighborhood, address, fatherName, motherName, graduation, graduationYear, employmentStatus, workplace, position, birthCity, birthDistrict, volumeNo, familyNo, lineNo, notes, membershipType, aidatAktif, membershipStatus, exitDate, exitReasonType, exitReason, registrationDate, createdAt, updatedAt) VALUES ";

        $values = [];
        foreach ($membersData as $member) {
            // Basic validation
            if (empty($member['id']) || empty($member['memberNo']))
                continue;


            $id = $conn->real_escape_string($member['id']);
            $memberNo = $conn->real_escape_string($member['memberNo']);
            $firstName = $conn->real_escape_string($member['firstName'] ?? '');
            $lastName = $conn->real_escape_string($member['lastName'] ?? '');
            $tcNo = $conn->real_escape_string($member['tcNo'] ?? '');
            $gender = isset($member['gender']) ? "'" . $conn->real_escape_string($member['gender']) . "'" : 'NULL';
            $birthDate = isset($member['birthDate']) ? "'" . $conn->real_escape_string($member['birthDate']) . "'" : 'NULL';
            $bloodType = isset($member['bloodType']) ? "'" . $conn->real_escape_string($member['bloodType']) . "'" : 'NULL';
            $email = $conn->real_escape_string($member['email'] ?? '');
            $phone = $conn->real_escape_string($member['phone'] ?? '');
            $phoneHome = isset($member['phoneHome']) ? "'" . $conn->real_escape_string($member['phoneHome']) . "'" : 'NULL';
            $maritalStatus = isset($member['maritalStatus']) ? "'" . $conn->real_escape_string($member['maritalStatus']) . "'" : 'NULL';
            $city = $conn->real_escape_string($member['city'] ?? '');
            $district = $conn->real_escape_string($member['district'] ?? '');
            $neighborhood = $conn->real_escape_string($member['neighborhood'] ?? '');
            $address = $conn->real_escape_string($member['address'] ?? '');
            $fatherName = isset($member['fatherName']) ? "'" . $conn->real_escape_string($member['fatherName']) . "'" : 'NULL';
            $motherName = isset($member['motherName']) ? "'" . $conn->real_escape_string($member['motherName']) . "'" : 'NULL';
            $graduation = isset($member['graduation']) ? "'" . $conn->real_escape_string($member['graduation']) . "'" : 'NULL';
            $graduationYear = isset($member['graduationYear']) ? intval($member['graduationYear']) : 'NULL';
            $employmentStatus = isset($member['employmentStatus']) ? "'" . $conn->real_escape_string($member['employmentStatus']) . "'" : 'NULL';
            $workplace = isset($member['workplace']) ? "'" . $conn->real_escape_string($member['workplace']) . "'" : 'NULL';
            $position = isset($member['position']) ? "'" . $conn->real_escape_string($member['position']) . "'" : 'NULL';
            $birthCity = isset($member['birthCity']) ? "'" . $conn->real_escape_string($member['birthCity']) . "'" : 'NULL';
            $birthDistrict = isset($member['birthDistrict']) ? "'" . $conn->real_escape_string($member['birthDistrict']) . "'" : 'NULL';
            $volumeNo = isset($member['volumeNo']) ? "'" . $conn->real_escape_string($member['volumeNo']) . "'" : 'NULL';
            $familyNo = isset($member['familyNo']) ? "'" . $conn->real_escape_string($member['familyNo']) . "'" : 'NULL';
            $lineNo = isset($member['lineNo']) ? "'" . $conn->real_escape_string($member['lineNo']) . "'" : 'NULL';
            $notes = isset($member['notes']) ? "'" . $conn->real_escape_string($member['notes']) . "'" : 'NULL';
            $membershipType = $conn->real_escape_string($member['membershipType'] ?? 'Asil Üye');
            $aidatAktif = isset($member['aidatAktif']) ? (int) $member['aidatAktif'] : 1;
            $membershipStatus = $conn->real_escape_string($member['membershipStatus'] ?? 'aktif');
            $exitDate = isset($member['exitDate']) ? "'" . $conn->real_escape_string($member['exitDate']) . "'" : 'NULL';
            $exitReasonType = isset($member['exitReasonType']) ? "'" . $conn->real_escape_string($member['exitReasonType']) . "'" : 'NULL';
            $exitReason = isset($member['exitReason']) ? "'" . $conn->real_escape_string($member['exitReason']) . "'" : 'NULL';
            $registrationDate = isset($member['registrationDate']) ? "'" . $conn->real_escape_string($member['registrationDate']) . "'" : 'NULL';
            $createdAt = isset($member['createdAt']) ? "'" . $conn->real_escape_string($member['createdAt']) . "'" : 'NOW()';
            $updatedAt = isset($member['updatedAt']) ? "'" . $conn->real_escape_string($member['updatedAt']) . "'" : 'NOW()';

            $values[] = "('$id', '$memberNo', '$firstName', '$lastName', '$tcNo', $gender, $birthDate, $bloodType, '$email', '$phone', $phoneHome, $maritalStatus, '$city', '$district', '$neighborhood', '$address', $fatherName, $motherName, $graduation, $graduationYear, $employmentStatus, '$workplace', '$position', $birthCity, $birthDistrict, $volumeNo, $familyNo, $lineNo, $notes, '$membershipType', $aidatAktif, '$membershipStatus', $exitDate, $exitReasonType, $exitReason, $registrationDate, $createdAt, $updatedAt)";
        }

        if (empty($values)) {
            $conn->rollback();
            sendResponse(false, 'Kaydedilecek geçerli üye bulunamadı');
            return;
        }

        $sql .= implode(',', $values);

        if (!$conn->query($sql)) {
            throw new Exception($conn->error);
        }

        $conn->commit();
        sendResponse(true, count($values) . ' üye başarıyla kaydedildi.', ['count' => count($values)]);

    } catch (Exception $e) {
        $conn->rollback();
        logError($e->getMessage());
        sendResponse(false, 'Üyeler kaydedilirken bir hata oluştu: ' . $e->getMessage());
    }
}

// ─── KVKK Endpoint Fonksiyonları ──────────────────────────

function getConsent()
{
    $memberId = isset($_GET['id']) ? $_GET['id'] : '';
    if (empty($memberId)) {
        sendResponse(false, 'Üye ID gerekli');
        return;
    }
    $consents = kvkk_get_consents($memberId);
    sendResponse(true, 'Onay bilgileri alındı', $consents);
}

function saveConsent($data)
{
    if (empty($data['member_id']) || empty($data['consent_type'])) {
        sendResponse(false, 'Üye ID ve onay tipi gerekli');
        return;
    }
    $given = isset($data['consent_given']) ? (bool) $data['consent_given'] : false;
    $notes = $data['notes'] ?? '';
    kvkk_save_consent($data['member_id'], $data['consent_type'], $given, $notes);
    kvkk_log_access('consent_update', $data['member_id'], $data['consent_type'] . ' → ' . ($given ? 'verildi' : 'geri çekildi'));
    sendResponse(true, 'Onay kaydedildi');
}

function anonymizeMember($data)
{
    if (empty($data['member_id'])) {
        sendResponse(false, 'Üye ID gerekli');
        return;
    }
    $result = kvkk_anonymize_member($data['member_id']);
    if ($result) {
        sendResponse(true, 'Üye verileri KVKK kapsamında anonimleştirildi');
    } else {
        sendResponse(false, 'Anonimleştirme sırasında hata oluştu');
    }
}

function getAccessLogs()
{
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 500) : 100;
    $targetId = isset($_GET['target_id']) ? $_GET['target_id'] : '';
    $logs = kvkk_get_access_logs($limit, $targetId);
    sendResponse(true, 'Erişim logları alındı', $logs);
}
?>