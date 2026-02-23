<?php
require_once 'auth.php';
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    sendResponse(false, 'Invalid request method');
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['year'])) {
    sendResponse(false, 'Year is required');
    exit;
}

$year = intval($data['year']);

if ($year < 2020 || $year > 2050) {
    sendResponse(false, 'Invalid year provided');
    exit;
}

global $conn;

$conn->begin_transaction();

try {
    // 1. Get dues settings
    $sql_settings = "SELECT setting_value FROM settings WHERE setting_key = 'aidat_settings'";
    $result_settings = $conn->query($sql_settings);
    $aidatSettings = [];

    if (!$result_settings || $result_settings->num_rows === 0) {
        // Try individual fallback if aidat_settings JSON doesn't exist
        $settingKeysMap = [
            'aidatAsilUye' => 'Asil Üye',
            'aidatAsilOnursal' => 'Asil Onursal',
            'aidatOgrenciUye' => 'Öğrenci Üye',
            'aidatFahriUye' => 'Fahri Üye',
            'aidatFahriOnursal' => 'Fahri Onursal'
        ];

        foreach ($settingKeysMap as $dbKey => $membershipType) {
            $sql = "SELECT setting_value FROM settings WHERE setting_key = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $dbKey);
            $stmt->execute();

            $stmt->bind_result($sval);
            if ($stmt->fetch()) {
                $aidatSettings[$membershipType] = floatval($sval);
            }
            $stmt->close();
        }

        if (empty($aidatSettings)) {
            // Provide default fallback if db is completely empty for dues
            $aidatSettings = [
                'Asil Üye' => 1500,
                'Öğrenci Üye' => 0
            ];
        }
    } else {
        $row = $result_settings->fetch_assoc();
        $decoded = json_decode($row['setting_value'], true);
        if (is_array($decoded)) {
            $aidatSettings = $decoded;
        } else {
            $aidatSettings = [
                'Asil Üye' => 1500,
                'Öğrenci Üye' => 0
            ];
        }
    }

    // 2. Get active members
    $sql_members = "SELECT id, membershipType FROM members WHERE aidatAktif = 1 AND membershipStatus = 'aktif'";
    $result_members = $conn->query($sql_members);
    if (!$result_members) {
        throw new Exception('Could not fetch members.');
    }
    $activeMembers = $result_members->fetch_all(MYSQLI_ASSOC);

    // 3. Get existing debts for the year to avoid duplicates
    $sql_existing = "SELECT memberId FROM payments WHERE year = ? AND type = 'aidat'";
    $stmt_existing = $conn->prepare($sql_existing);
    $stmt_existing->bind_param('i', $year);
    $stmt_existing->execute();

    $stmt_existing->bind_result($existingMemberId);
    $existingDebts = [];
    while ($stmt_existing->fetch()) {
        $existingDebts[$existingMemberId] = true;
    }
    $stmt_existing->close();

    $newDebts = [];
    $skippedCount = 0;

    foreach ($activeMembers as $member) {
        $memberId = $member['id'];
        $membershipType = $member['membershipType'];

        // Skip if already has a debt for the year
        if (isset($existingDebts[$memberId])) {
            $skippedCount++;
            continue;
        }

        $duesAmount = $aidatSettings[$membershipType] ?? 0;

        // Skip if dues amount is zero
        if ($duesAmount <= 0) {
            $skippedCount++;
            continue;
        }

        $newDebts[] = [
            'id' => uniqid('p_'), // Generate a unique ID
            'memberId' => $memberId,
            'amount' => $duesAmount,
            'type' => 'aidat',
            'year' => $year,
            'date' => "$year-01-01",
            'description' => "$year yılı $membershipType aidatı",
            'status' => 'bekliyor'
        ];
    }

    if (!empty($newDebts)) {
        $sql_insert = "INSERT INTO payments (id, memberId, amount, type, year, date, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);

        foreach ($newDebts as $debt) {
            $stmt_insert->bind_param(
                'ssdsssss',
                $debt['id'],
                $debt['memberId'],
                $debt['amount'],
                $debt['type'],
                $debt['year'],
                $debt['date'],
                $debt['description'],
                $debt['status']
            );
            if (!$stmt_insert->execute()) {
                throw new Exception('Failed to insert new debt: ' . $stmt_insert->error);
            }
        }
    }

    $conn->commit();
    sendResponse(true, 'Otomatik borçlandırma tamamlandı.', ['created' => count($newDebts), 'skipped' => $skippedCount]);

} catch (Exception $e) {
    $conn->rollback();
    logError($e->getMessage());
    sendResponse(false, 'Otomatik borçlandırma sırasında bir hata oluştu: ' . $e->getMessage());
}

?>