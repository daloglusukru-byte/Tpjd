<?php
require_once 'auth.php';
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($method) {
    case 'GET':
        if ($action === 'get') {
            getSetting();
        } elseif ($action === 'all') {
            getAllSettings();
        } else {
            getAllSettings();
        }
        break;
    case 'POST':
        saveSetting();
        break;
    default:
        sendResponse(false, 'Geçersiz istek yöntemi');
}

function getSetting()
{
    global $conn;

    $key = isset($_GET['key']) ? $_GET['key'] : '';

    if (empty($key)) {
        sendResponse(false, 'Ayar anahtarı gerekli');
    }

    try {
        $sql = "SELECT * FROM settings WHERE setting_key = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception($conn->error);
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();

        $setting = $result->fetch_assoc();
        $stmt->close();

        if (!$setting) {
            sendResponse(false, 'Ayar bulunamadı');
        }

        if ($setting['setting_value']) {
            $setting['setting_value'] = json_decode($setting['setting_value'], true);
        }

        sendResponse(true, 'Ayar başarıyla alındı', $setting);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Ayar alınırken hata oluştu: ' . $e->getMessage());
    }
}

function getAllSettings()
{
    global $conn;

    try {
        $sql = "SELECT * FROM settings";
        $result = $conn->query($sql);

        if (!$result) {
            throw new Exception($conn->error);
        }

        $settings = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['setting_value']) {
                $row['setting_value'] = json_decode($row['setting_value'], true);
            }
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        sendResponse(true, 'Ayarlar başarıyla alındı', $settings);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Ayarlar alınırken hata oluştu: ' . $e->getMessage());
    }
}

function saveSetting()
{
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        sendResponse(false, 'Geçersiz veri');
        return;
    }

    try {
        $conn->begin_transaction();

        $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception($conn->error);

        foreach ($data as $key => $value) {
            if ($key === 'admin_password') {
                if (empty($value)) {
                    continue;
                }
                $passwordInfo = password_get_info($value);
                if ($passwordInfo['algo'] === 0) {
                    $value = password_hash($value, PASSWORD_DEFAULT);
                }
            }

            $jsonValue = json_encode($value, JSON_UNESCAPED_UNICODE);
            $stmt->bind_param('sss', $key, $jsonValue, $jsonValue);

            if (!$stmt->execute()) {
                throw new Exception("Error saving setting '$key': " . $stmt->error);
            }
        }

        $stmt->close();
        $conn->commit();
        sendResponse(true, 'Ayarlar başarıyla kaydedildi');

    } catch (Exception $e) {
        $conn->rollback();
        logError($e->getMessage());
        sendResponse(false, 'Ayarlar kaydedilirken bir hata oluştu: ' . $e->getMessage());
    }
}
?>