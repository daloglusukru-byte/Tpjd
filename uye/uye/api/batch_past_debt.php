<?php
// Database connection — config.php'den bilgileri al
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    sendResponse(false, 'Database connection failed: ' . $e->getMessage());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['memberId']) || !isset($data['years']) || !is_array($data['years'])) {
    sendResponse(false, 'Member ID and a list of years are required');
    exit;
}

$memberId = $data['memberId'];
$years = $data['years'];

try {
    $pdo->beginTransaction();

    // Get member details
    $sql_member = "SELECT membershipType FROM members WHERE id = ?";
    $stmt_member = $pdo->prepare($sql_member);
    $stmt_member->execute([$memberId]);
    $member = $stmt_member->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        throw new Exception('Member not found.');
    }

    $membershipType = $member['membershipType'];

    // Get aidat settings
    $sql_settings = "SELECT setting_value FROM settings WHERE setting_key = 'aidat_settings'";
    $result_settings = $pdo->query($sql_settings);
    if (!$result_settings || $result_settings->rowCount() === 0) {
        throw new Exception('Dues settings not found.');
    }
    $aidatSettings = json_decode($result_settings->fetch(PDO::FETCH_ASSOC)['setting_value'], true);
    $duesAmount = $aidatSettings[$membershipType] ?? 0;

    if ($duesAmount <= 0) {
        throw new Exception('Dues amount for this membership type is zero. No debts created.');
    }

    // Insert new debts with duplicate check
    $sql_check = "SELECT COUNT(*) as count FROM payments WHERE memberId = ? AND year = ? AND type = 'aidat'";
    $stmt_check = $pdo->prepare($sql_check);

    $sql_insert = "INSERT INTO payments (id, memberId, amount, type, year, date, description, status) VALUES (?, ?, ?, 'aidat', ?, ?, ?, 'bekliyor')";
    $stmt_insert = $pdo->prepare($sql_insert);

    $createdCount = 0;
    $skippedCount = 0;
    $skippedYears = [];

    foreach ($years as $year) {
        $year = intval($year);

        // Check if debt already exists for this year
        $stmt_check->execute([$memberId, $year]);
        $existingCount = $stmt_check->fetch(PDO::FETCH_ASSOC)['count'];

        if ($existingCount > 0) {
            $skippedCount++;
            $skippedYears[] = $year;
            continue; // Skip this year - debt already exists
        }

        $debtId = uniqid('p_');
        $date = "$year-01-01";
        $description = "$year yılı $membershipType aidatı (geçmiş dönem)";

        $stmt_insert->execute([$debtId, $memberId, $duesAmount, $year, $date, $description]);
        $createdCount++;
    }

    $pdo->commit();

    $message = "$createdCount adet geçmiş yıl borcu başarıyla oluşturuldu.";
    if ($skippedCount > 0) {
        $message .= " " . $skippedCount . " yıl (" . implode(', ', $skippedYears) . ") zaten borçlandırılmış olduğu için atlandı.";
    }

    sendResponse(true, $message, [
        'created' => $createdCount,
        'skipped' => $skippedCount,
        'skippedYears' => $skippedYears
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    sendResponse(false, 'Geçmiş borçlar oluşturulurken bir hata oluştu: ' . $e->getMessage());
}
?>