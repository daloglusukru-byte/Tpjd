<?php
require_once 'auth.php';
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    sendResponse(false, 'Geçersiz istek yöntemi');
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'send':
        sendSMS();
        break;
    case 'test':
        testConnection();
        break;
    case 'history':
        getSMSHistory();
        break;
    default:
        sendResponse(false, 'Geçersiz SMS isteği');
}

function getNetgsmSettings()
{
    global $conn;
    try {
        $sql = "SELECT setting_value FROM settings WHERE setting_key = 'netgsm_settings'";
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $settings = json_decode($row['setting_value'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($settings)) {
                return $settings;
            }
        }
    } catch (Exception $e) {
        logError('NETGSM settings okunamadı: ' . $e->getMessage());
    }
    return null;
}

function saveSMSHistory($recipients, $message, $jobId, $status, $errorMessage = '')
{
    global $conn;
    try {
        $sql = "INSERT INTO sms_history (recipients, message, job_id, status, error_message, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $recipientsJson = json_encode($recipients);
        $stmt->bind_param('sssss', $recipientsJson, $message, $jobId, $status, $errorMessage);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        logError('SMS history kaydedilemedi: ' . $e->getMessage());
    }
}

function sendSMS()
{
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendResponse(false, 'Geçersiz JSON verisi');
    }

    $recipients = isset($input['recipients']) ? $input['recipients'] : [];
    $message = isset($input['message']) ? $input['message'] : '';
    $iysFilter = isset($input['iysFilter']) ? $input['iysFilter'] : '0';

    if (empty($recipients) || empty($message)) {
        sendResponse(false, 'Alıcı listesi ve mesaj içeriği zorunludur');
    }

    if (strlen($message) > 917) {
        sendResponse(false, 'Mesaj 917 karakterden uzun olamaz');
    }

    $settings = getNetgsmSettings();
    if (!$settings || empty($settings['username']) || empty($settings['password']) || empty($settings['header'])) {
        sendResponse(false, 'NETGSM ayarları yapılandırılmamış');
    }

    $username = $settings['username'];
    $password = $settings['password'];
    $msgHeader = $settings['header'];

    // Format phone numbers
    $formattedNumbers = [];
    foreach ($recipients as $phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10 && $phone[0] === '5') {
            $formattedNumbers[] = $phone;
        } elseif (strlen($phone) === 11 && $phone[0] === '0') {
            $formattedNumbers[] = substr($phone, 1);
        } elseif (strlen($phone) === 13 && substr($phone, 0, 3) === '905') {
            $formattedNumbers[] = substr($phone, 2);
        }
    }

    if (empty($formattedNumbers)) {
        sendResponse(false, 'Geçerli telefon numarası bulunamadı');
    }

    // Prepare NETGSM API request
    $messages = [];
    foreach ($formattedNumbers as $number) {
        $messages[] = [
            'msg' => $message,
            'no' => $number
        ];
    }

    $data = [
        'msgheader' => $msgHeader,
        'messages' => $messages,
        'encoding' => 'TR',
        'iysfilter' => $iysFilter,
        'partnercode' => ''
    ];

    $url = 'https://api.netgsm.com.tr/sms/rest/v2/send';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($username . ':' . $password)
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        saveSMSHistory($recipients, $message, '', 'failed', $curlError);
        sendResponse(false, 'SMS gönderim hatası: ' . $curlError);
    }

    if ($httpCode !== 200) {
        saveSMSHistory($recipients, $message, '', 'failed', 'HTTP ' . $httpCode);
        sendResponse(false, 'SMS servisi HTTP ' . $httpCode . ' hatası döndü');
    }

    $result = json_decode($response, true);

    if (!$result) {
        saveSMSHistory($recipients, $message, '', 'failed', 'JSON parse error');
        sendResponse(false, 'Servis yanıtı parse edilemedi: ' . $response);
    }

    if (isset($result['code']) && $result['code'] === '00') {
        $jobId = isset($result['jobid']) ? $result['jobid'] : '';
        saveSMSHistory($recipients, $message, $jobId, 'queued');
        sendResponse(true, 'SMS kuyruğa alındı', [
            'jobId' => $jobId,
            'recipientCount' => count($formattedNumbers),
            'description' => isset($result['description']) ? $result['description'] : 'queued'
        ]);
    } else {
        $errorCode = isset($result['code']) ? $result['code'] : 'unknown';
        $errorDesc = isset($result['description']) ? $result['description'] : 'Bilinmeyen hata';
        saveSMSHistory($recipients, $message, '', 'failed', $errorCode . ': ' . $errorDesc);
        sendResponse(false, 'NETGSM Hatası: ' . $errorCode . ' - ' . $errorDesc);
    }
}

function testConnection()
{
    $settings = getNetgsmSettings();
    if (!$settings || empty($settings['username']) || empty($settings['password'])) {
        sendResponse(false, 'NETGSM ayarları yapılandırılmamış');
    }

    $username = $settings['username'];
    $password = $settings['password'];

    // Test by checking balance (stip=2 for credit info)
    $url = 'https://api.netgsm.com.tr/balance';

    $data = [
        'usercode' => $username,
        'password' => $password,
        'stip' => 2
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($username . ':' . $password)
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        sendResponse(false, 'Bağlantı hatası: ' . $curlError);
    }

    if ($httpCode !== 200) {
        sendResponse(false, 'Servis HTTP ' . $httpCode . ' hatası döndü');
    }

    $result = json_decode($response, true);

    if (isset($result['code']) && $result['code'] === '00') {
        sendResponse(true, 'Bağlantı başarılı', [
            'balance' => isset($result['balance']) ? $result['balance'] : 'Bilinmiyor'
        ]);
    } elseif (isset($result['code']) && $result['code'] === '30') {
        sendResponse(false, 'Geçersiz kullanıcı adı veya şifre');
    } else {
        $error = isset($result['description']) ? $result['description'] : 'Bilinmeyen hata';
        sendResponse(false, 'Bağlantı testi başarısız: ' . $error);
    }
}

function getSMSHistory()
{
    global $conn;

    try {
        $sql = "SELECT * FROM sms_history ORDER BY created_at DESC LIMIT 100";
        $result = $conn->query($sql);

        $history = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['recipients'] = json_decode($row['recipients'], true);
                $history[] = $row;
            }
        }

        sendResponse(true, 'SMS geçmişi listelendi', $history);
    } catch (Exception $e) {
        sendResponse(false, 'SMS geçmişi alınamadı: ' . $e->getMessage());
    }
}
?>