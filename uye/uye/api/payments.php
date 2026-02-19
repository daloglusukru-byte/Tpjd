<?php
require_once 'auth.php';
require_once '../config.php';

function generate_payment_id()
{
    return uniqid('p_', true);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            getPayments();
        } elseif ($action === 'get') {
            getPayment();
        } else {
            getPayments();
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data)) {
            $data = $_POST;
        }

        if (!$data) {
            sendResponse(false, 'Geçersiz JSON verisi');
            break;
        }

        if (isset($data['action']) && $data['action'] === 'saveAll') {
            saveAllPayments($data['data']);
        } else {
            addPayment($data);
        }
        break;
    case 'PUT':
        updatePayment();
        break;
    case 'DELETE':
        deletePayment();
        break;
    default:
        sendResponse(false, 'Geçersiz istek yöntemi');
}

function getPayments()
{
    global $conn;

    try {
        $sql = "SELECT p.*, m.firstName, m.lastName, m.memberNo FROM payments p 
                LEFT JOIN members m ON p.memberId = m.id 
                ORDER BY p.date DESC";
        $result = $conn->query($sql);

        if (!$result) {
            throw new Exception($conn->error);
        }

        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }

        sendResponse(true, 'Ödemeler başarıyla alındı', $payments);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Ödemeler alınırken hata oluştu: ' . $e->getMessage());
    }
}

function getPayment()
{
    global $conn;

    $id = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($id)) {
        sendResponse(false, 'Ödeme ID gerekli');
    }

    try {
        $sql = "SELECT p.*, m.firstName, m.lastName, m.memberNo FROM payments p 
                LEFT JOIN members m ON p.memberId = m.id 
                WHERE p.id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception($conn->error);
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        $payment = $result->fetch_assoc();
        $stmt->close();

        if (!$payment) {
            sendResponse(false, 'Ödeme bulunamadı');
        }

        sendResponse(true, 'Ödeme başarıyla alındı', $payment);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Ödeme alınırken hata oluştu: ' . $e->getMessage());
    }
}

function addPayment($data)
{
    global $conn;

    if (!$data) {
        sendResponse(false, 'Geçersiz JSON verisi');
    }

    // Backend fallback for ID
    if (empty($data['id'])) {
        $data['id'] = generate_payment_id();
    }

    $required = ['memberId', 'amount', 'type', 'year', 'status'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            sendResponse(false, "Gerekli alan eksik: $field");
        }
    }

    try {
        $sql = "INSERT INTO payments (
            id, memberId, amount, type, year, date, receiptNo, description, status, createdAt, updatedAt
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Sorgu hazırlanamadı: ' . $conn->error);
        }

        $id = $data['id'];
        $memberId = $data['memberId'];
        $amount = isset($data['amount']) ? (float) $data['amount'] : 0;
        $type = $data['type'];
        $year = isset($data['year']) ? (int) $data['year'] : null;
        $date = !empty($data['date']) ? $data['date'] : null;
        $receiptNo = $data['receiptNo'] ?? null;
        $description = $data['description'] ?? '';
        $status = $data['status'];
        $createdAt = $data['createdAt'] ?? date('Y-m-d H:i:s');
        $updatedAt = $data['updatedAt'] ?? date('Y-m-d H:i:s');

        $stmt->bind_param(
            'ssdsissssss',
            $id,
            $memberId,
            $amount,
            $type,
            $year,
            $date,
            $receiptNo,
            $description,
            $status,
            $createdAt,
            $updatedAt
        );

        if (!$stmt->execute()) {
            throw new Exception('Sorgu çalıştırılamadı: ' . $stmt->error);
        }

        $stmt->close();
        sendResponse(true, 'Ödeme başarıyla eklendi', ['id' => $id]);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Ödeme eklenirken hata oluştu: ' . $e->getMessage());
    }
}

function updatePayment()
{
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || empty($data['id'])) {
        sendResponse(false, 'Ödeme ID gerekli');
    }

    try {
        $fields = ['memberId', 'amount', 'type', 'year', 'date', 'receiptNo', 'description', 'status'];
        $updates = [];
        $params = [];
        $types = '';

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                if ($field === 'amount') {
                    $types .= 'd';
                    $params[] = (float) $data[$field];
                } elseif ($field === 'year') {
                    $types .= 'i';
                    $params[] = (int) $data[$field];
                } else {
                    $types .= 's';
                    $params[] = $data[$field];
                }
            }
        }

        // Always update updatedAt
        $updates[] = 'updatedAt = ?';
        $types .= 's';
        $params[] = date('Y-m-d H:i:s');

        if (empty($updates)) {
            sendResponse(false, 'Güncellenecek alan yok');
        }

        $sql = "UPDATE payments SET " . implode(', ', $updates) . " WHERE id = ?";
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
        sendResponse(true, 'Ödeme başarıyla güncellendi');
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Ödeme güncellenirken hata oluştu: ' . $e->getMessage());
    }
}

function deletePayment()
{
    global $conn;

    $id = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($id)) {
        sendResponse(false, 'Ödeme ID gerekli');
    }

    try {
        $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
        if (!$stmt)
            throw new Exception($conn->error);
        $stmt->bind_param('s', $id);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();

        sendResponse(true, 'Ödeme başarıyla silindi');
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Ödeme silinirken hata oluştu: ' . $e->getMessage());
    }
}

function saveAllPayments($paymentsData)
{
    global $conn;

    if (!is_array($paymentsData)) {
        sendResponse(false, 'Geçersiz veri formatı');
        return;
    }

    try {
        $conn->begin_transaction();

        // Truncate the table for a clean slate
        $conn->query("TRUNCATE TABLE payments");

        if (empty($paymentsData)) {
            // If the data is empty, just commit the truncate and exit
            $conn->commit();
            sendResponse(true, 'Ödemeler tablosu temizlendi.', ['count' => 0]);
            return;
        }

        $sql = "INSERT INTO payments (id, memberId, amount, type, year, date, receiptNo, description, status, createdAt, updatedAt) VALUES ";

        $values = [];
        foreach ($paymentsData as $payment) {
            if (empty($payment['id']))
                continue;

            $id = $conn->real_escape_string($payment['id']);
            $memberId = $conn->real_escape_string($payment['memberId']);
            $amount = floatval($payment['amount']);
            $type = $conn->real_escape_string($payment['type']);
            $year = intval($payment['year']);
            $date = isset($payment['date']) ? "'" . $conn->real_escape_string($payment['date']) . "'" : 'NULL';
            $receiptNo = $conn->real_escape_string($payment['receiptNo'] ?? '');
            $description = $conn->real_escape_string($payment['description'] ?? '');
            $status = $conn->real_escape_string($payment['status']);
            $createdAt = isset($payment['createdAt']) ? "'" . $conn->real_escape_string($payment['createdAt']) . "'" : 'NOW()';
            $updatedAt = isset($payment['updatedAt']) ? "'" . $conn->real_escape_string($payment['updatedAt']) . "'" : 'NOW()';

            $values[] = "('$id', '$memberId', $amount, '$type', $year, $date, '$receiptNo', '$description', '$status', $createdAt, $updatedAt)";
        }

        if (empty($values)) {
            $conn->rollback();
            sendResponse(false, 'Kaydedilecek geçerli ödeme bulunamadı');
            return;
        }

        $sql .= implode(',', $values);

        if (!$conn->query($sql)) {
            throw new Exception($conn->error);
        }

        $conn->commit();
        sendResponse(true, count($values) . ' ödeme başarıyla kaydedildi.', ['count' => count($values)]);

    } catch (Exception $e) {
        $conn->rollback();
        logError($e->getMessage());
        sendResponse(false, 'Ödemeler kaydedilirken bir hata oluştu: ' . $e->getMessage());
    }
}
?>