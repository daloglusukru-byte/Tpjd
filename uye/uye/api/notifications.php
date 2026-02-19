<?php
require_once 'auth.php';
require_once '../config.php';

function generate_notification_id()
{
    return uniqid('n_', true);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            getNotifications();
        } elseif ($action === 'get') {
            getNotification();
        } else {
            getNotifications();
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data)) {
            $data = $_POST;
        }

        // Route by action parameter
        if ($action === 'whatsapp_send') {
            sendWhatsAppMessage($data);
            break;
        }

        if (!$data) {
            sendResponse(false, 'Geçersiz JSON verisi');
            break;
        }

        addNotification($data);
        break;
    case 'PUT':
        updateNotification();
        break;
    case 'DELETE':
        deleteNotification();
        break;
    default:
        sendResponse(false, 'Geçersiz istek yöntemi');
}

function getNotifications()
{
    global $conn;

    try {
        $sql = "SELECT * FROM notifications ORDER BY sentAt DESC";
        $result = $conn->query($sql);

        if (!$result) {
            throw new Exception($conn->error);
        }

        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['recipients']) {
                $row['recipients'] = json_decode($row['recipients'], true);
            }
            $notifications[] = $row;
        }

        sendResponse(true, 'Bildirimler başarıyla alındı', $notifications);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Bildirimler alınırken hata oluştu: ' . $e->getMessage());
    }
}

function getNotification()
{
    global $conn;

    $id = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($id)) {
        sendResponse(false, 'Bildirim ID gerekli');
    }

    try {
        $sql = "SELECT * FROM notifications WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception($conn->error);
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        $notification = $result->fetch_assoc();
        $stmt->close();

        if (!$notification) {
            sendResponse(false, 'Bildirim bulunamadı');
        }

        if ($notification['recipients']) {
            $notification['recipients'] = json_decode($notification['recipients'], true);
        }

        sendResponse(true, 'Bildirim başarıyla alındı', $notification);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Bildirim alınırken hata oluştu: ' . $e->getMessage());
    }
}

function addNotification($data)
{
    global $conn;

    if (!$data) {
        sendResponse(false, 'Geçersiz JSON verisi');
    }

    // Backend fallback
    if (empty($data['id'])) {
        $data['id'] = generate_notification_id();
    }

    // Validate required fields
    $required = ['title', 'message'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendResponse(false, "Gerekli alan eksik: $field");
        }
    }

    try {
        $sql = "INSERT INTO notifications (
            id, title, message, recipients, priority, sentAt, createdAt
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Sorgu hazırlanamadı: ' . $conn->error);
        }

        $id = $data['id'];
        $title = $data['title'];
        $message = $data['message'];
        $recipients = isset($data['recipients']) ? json_encode($data['recipients']) : json_encode([]);
        $priority = $data['priority'] ?? 'normal';
        $sentAt = $data['sentAt'] ?? null;
        $createdAt = $data['createdAt'] ?? date('Y-m-d H:i:s');

        $stmt->bind_param(
            'sssssss',
            $id,
            $title,
            $message,
            $recipients,
            $priority,
            $sentAt,
            $createdAt
        );

        if (!$stmt->execute()) {
            throw new Exception('Sorgu çalıştırılamadı: ' . $stmt->error);
        }

        $stmt->close();
        sendResponse(true, 'Bildirim başarıyla eklendi', ['id' => $id]);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Bildirim eklenirken hata oluştu: ' . $e->getMessage());
    }
}

function updateNotification()
{
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || empty($data['id'])) {
        sendResponse(false, 'Bildirim ID gerekli');
    }

    try {
        $fields = ['title', 'message', 'recipients', 'priority', 'sentAt'];
        $updates = [];
        $params = [];
        $types = '';

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                if ($field === 'recipients') {
                    $types .= 's';
                    $params[] = json_encode($data[$field]);
                } else {
                    $types .= 's';
                    $params[] = $data[$field];
                }
            }
        }

        // Always update createdAt if provided? keep untouched, but ensure updatedAt? table has createdAt only. Skip.

        if (empty($updates)) {
            sendResponse(false, 'Güncellenecek alan yok');
        }

        $sql = "UPDATE notifications SET " . implode(', ', $updates) . " WHERE id = ?";
        $types .= 's';
        $params[] = $data['id'];

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Sorgu hazırlanamadı: ' . $conn->error);
        }

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception('Sorgu çalıştırılamadı: ' . $stmt->error);
        }

        $stmt->close();
        sendResponse(true, 'Bildirim başarıyla güncellendi');
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Bildirim güncellenirken hata oluştu: ' . $e->getMessage());
    }
}

function deleteNotification()
{
    global $conn;

    $id = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : '';

    if (empty($id)) {
        sendResponse(false, 'Bildirim ID gerekli');
    }

    try {
        $sql = "DELETE FROM notifications WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Sorgu hazırlanamadı: ' . $conn->error);
        }

        $stmt->bind_param('s', $id);

        if (!$stmt->execute()) {
            throw new Exception('Sorgu çalıştırılamadı: ' . $stmt->error);
        }

        $stmt->close();
        sendResponse(true, 'Bildirim başarıyla silindi');
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Bildirim silinirken hata oluştu: ' . $e->getMessage());
    }
}

function saveAllNotifications($notificationsData)
{
    global $conn;

    if (!is_array($notificationsData)) {
        sendResponse(false, 'Geçersiz veri formatı');
        return;
    }

    try {
        $conn->begin_transaction();

        // Truncate the table for a clean slate
        $conn->query("TRUNCATE TABLE notifications");

        if (empty($notificationsData)) {
            $conn->commit();
            sendResponse(true, 'Bildirimler tablosu temizlendi.', ['count' => 0]);
            return;
        }

        $sql = "INSERT INTO notifications (id, title, message, recipients, priority, sentAt, createdAt) VALUES ";

        $values = [];
        foreach ($notificationsData as $notification) {
            if (empty($notification['id']))
                continue;

            $id = $conn->real_escape_string($notification['id']);
            $title = $conn->real_escape_string($notification['title'] ?? 'Başlıksız');
            $message = $conn->real_escape_string($notification['message'] ?? '');
            $recipients = isset($notification['recipients']) ? "'" . $conn->real_escape_string(json_encode($notification['recipients'])) . "'" : 'NULL';
            $priority = $conn->real_escape_string($notification['priority'] ?? 'normal');
            $sentAt = isset($notification['sentAt']) ? "'" . $conn->real_escape_string($notification['sentAt']) . "'" : 'NOW()';
            $createdAt = isset($notification['createdAt']) ? "'" . $conn->real_escape_string($notification['createdAt']) . "'" : 'NOW()';

            $values[] = "('$id', '$title', '$message', $recipients, '$priority', $sentAt, $createdAt)";
        }

        if (empty($values)) {
            $conn->rollback();
            sendResponse(false, 'Kaydedilecek geçerli bildirim bulunamadı');
            return;
        }

        $sql .= implode(',', $values);

        if (!$conn->query($sql)) {
            throw new Exception($conn->error);
        }

        $conn->commit();
        sendResponse(true, count($values) . ' bildirim başarıyla kaydedildi.', ['count' => count($values)]);

    } catch (Exception $e) {
        $conn->rollback();
        logError($e->getMessage());
        sendResponse(false, 'Bildirimler kaydedilirken bir hata oluştu: ' . $e->getMessage());
    }
}

// ─── WhatsApp Send via Evolution API ───────────────────────
function sendWhatsAppMessage($data)
{
    global $conn;

    if (empty($data['phone']) || empty($data['message'])) {
        sendResponse(false, 'Telefon ve mesaj alanları zorunludur');
        return;
    }

    try {
        $stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'whatsapp_settings'");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row || empty($row['value'])) {
            sendResponse(false, 'WhatsApp (Evolution API) ayarları yapılandırılmamış');
            return;
        }

        $waSettings = json_decode($row['value'], true);
        $apiUrl = $waSettings['apiUrl'] ?? '';
        $apiKey = $waSettings['apiKey'] ?? '';
        $instanceName = $waSettings['instanceName'] ?? '';

        if (empty($apiUrl) || empty($apiKey) || empty($instanceName)) {
            sendResponse(false, 'Evolution API URL, Key veya Instance eksik');
            return;
        }

        // Format phone number
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);
        if (strlen($phone) === 10 && $phone[0] === '5') {
            $phone = '90' . $phone;
        } elseif (strlen($phone) === 11 && $phone[0] === '0') {
            $phone = '9' . $phone;
        }

        // Evolution API v2 - Send Text
        $url = rtrim($apiUrl, '/') . '/message/sendText/' . urlencode($instanceName);

        $payload = json_encode([
            'number' => $phone,
            'text' => $data['message']
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            logError('WhatsApp curl error: ' . $curlError);
            sendResponse(false, 'WhatsApp bağlantı hatası: ' . $curlError);
            return;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            sendResponse(true, 'WhatsApp mesajı gönderildi', [
                'messageId' => $result['key']['id'] ?? '',
                'status' => $result['status'] ?? 'sent'
            ]);
        } else {
            $errorMsg = $result['message'] ?? $result['error'] ?? 'HTTP ' . $httpCode;
            logError('WhatsApp API error: ' . $errorMsg);
            sendResponse(false, 'WhatsApp hatası: ' . $errorMsg);
        }

    } catch (Exception $e) {
        logError('WhatsApp exception: ' . $e->getMessage());
        sendResponse(false, 'WhatsApp gönderim hatası: ' . $e->getMessage());
    }
}
