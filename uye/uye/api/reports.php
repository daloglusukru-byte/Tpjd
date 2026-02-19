<?php
require_once 'auth.php';
require_once '../config.php';
require_once 'kvkk.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($method !== 'GET') {
    sendResponse(false, 'Geçersiz istek yöntemi');
}

switch ($action) {
    case 'debtors':
        getDebtors();
        break;
    default:
        sendResponse(false, 'Geçersiz rapor isteği');
}

function getAidatSettingsMap()
{
    global $conn;
    $defaults = [
        'Asil Üye' => 100,
        'Asil Onursal' => 90,
        'Öğrenci Üye' => 0,
        'Fahri Üye' => 0,
        'Fahri Onursal' => 0
    ];

    try {
        $sql = "SELECT setting_value FROM settings WHERE setting_key = 'aidat_settings'";
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $val = json_decode($row['setting_value'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                logError('Aidat settings JSON decode hatası: ' . json_last_error_msg());
                return $defaults;
            }
            if (is_array($val)) {
                return array_merge($defaults, $val);
            }
        }
    } catch (Exception $e) {
        logError('Aidat settings okunamadı: ' . $e->getMessage());
    }

    return $defaults;
}

function getDebtors()
{
    global $conn;
    $yearParam = isset($_GET['year']) ? $_GET['year'] : date('Y');

    try {
        // If year is 'all', get debtors from all years
        if ($yearParam === 'all') {
            $sql = "SELECT m.id, m.memberNo, m.firstName, m.lastName, m.membershipType, m.email, m.phone, m.membershipStatus,
                           SUM(p.amount) as total_due,
                           GROUP_CONCAT(DISTINCT p.year ORDER BY p.year DESC) as debt_years
                    FROM members m
                    JOIN payments p ON p.memberId = m.id
                    WHERE p.type = 'aidat' 
                    AND p.status = 'bekliyor' 
                    AND m.membershipStatus != 'ayrıldı'
                    GROUP BY m.id";

            $result = $conn->query($sql);
            if ($result === false) {
                throw new Exception('Sorgu çalıştırılamadı: ' . $conn->error);
            }

            $debtors = [];
            while ($row = $result->fetch_assoc()) {
                $debtors[] = [
                    'memberId' => $row['id'],
                    'memberNo' => $row['memberNo'],
                    'fullName' => trim($row['firstName'] . ' ' . $row['lastName']),
                    'membershipType' => $row['membershipType'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'paid' => 0,
                    'due' => (float) $row['total_due'],
                    'status' => 'borçlu',
                    'year' => $row['debt_years']
                ];
            }

            sendResponse(true, 'Tüm borçlu üyeler listelendi', ['data' => $debtors, 'count' => count($debtors)]);
            return;
        }

        // Original logic for specific year
        $year = (int) $yearParam;

        $sql = "SELECT m.id, m.memberNo, m.firstName, m.lastName, m.membershipType, m.email, m.phone, m.membershipStatus,
                       p.amount as due_amount, p.year
                FROM members m
                JOIN payments p ON p.memberId = m.id
                WHERE p.type = 'aidat' 
                AND p.status = 'bekliyor' 
                AND p.year = ?
                AND m.membershipStatus != 'ayrıldı'";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Sorgu hazırlanamadı: ' . $conn->error);
        }

        $stmt->bind_param('i', $year);

        if (!$stmt->execute()) {
            throw new Exception('Sorgu çalıştırılamadı: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $debtors = [];

        while ($row = $result->fetch_assoc()) {
            $debtors[] = [
                'memberId' => $row['id'],
                'memberNo' => $row['memberNo'],
                'fullName' => trim($row['firstName'] . ' ' . $row['lastName']),
                'membershipType' => $row['membershipType'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'paid' => 0, // Since we are looking at 'bekliyor' status
                'due' => (float) $row['due_amount'],
                'status' => 'borçlu',
                'year' => (int) $row['year']
            ];
        }

        $stmt->close();

        sendResponse(true, 'Borçlu üyeler listelendi', ['data' => $debtors, 'count' => count($debtors)]);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Borçlu listesi alınırken hata oluştu: ' . $e->getMessage());
    }
}
