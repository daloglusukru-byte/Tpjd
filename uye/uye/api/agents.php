<?php
require_once 'auth.php';
require_once '../config.php';
require_once 'kvkk.php';


require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

function sendEmailInternalHelper($toEmail, $toName, $subject, $body)
{
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'mail.tpjd.org.tr';
        $mail->SMTPAuth = true;
        $mail->Username = 'uye@tpjd.org.tr';
        $mail->Password = '$TKJ[0sPtEHhoDEA';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('uye@tpjd.org.tr', 'TPJD Derneği');
        $mail->addReplyTo('uye@tpjd.org.tr', 'TPJD Derneği');
        $mail->addAddress(trim($toEmail), $toName);

        if (preg_match('/^\=\?UTF\-8\?B\?(.*?)\?\=$/i', trim($subject), $matches)) {
            $mail->Subject = base64_decode($matches[1]);
        } else {
            $mail->Subject = $subject;
        }

        $mail->isHTML(true);
        $mail->Body = $body;

        return $mail->send();
    } catch (Exception $e) {
        logError("PHPMailer Error to $toEmail: " . $e->getMessage());
        return false;
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($method) {
    case 'GET':
        if ($action === 'logs') {
            getAgentLogs();
        } elseif ($action === 'config') {
            getAgentConfig();
        } elseif ($action === 'stats') {
            getAgentStats();
        } elseif ($action === 'birthdays') {
            getTodayBirthdays();
        } elseif ($action === 'debtors') {
            getDebtorsForReminder();
        } elseif ($action === 'cron_check') {
            checkCronStatus();
        } elseif ($action === 'seniority_check') {
            checkSeniorityPromotions();
        } else {
            sendResponse(false, 'Geçersiz agent isteği');
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data) {
            sendResponse(false, 'Geçersiz JSON verisi');
            break;
        }
        if ($action === 'log') {
            saveAgentLog($data);
        } elseif ($action === 'config') {
            saveAgentConfig($data);
        } elseif ($action === 'run') {
            runAgent($data);
        } elseif ($action === 'seniority_promote') {
            runSeniorityPromotions($data);
        } elseif ($action === 'send-notification') {
            sendAgentNotification($data);
        } elseif ($action === 'event_announce') {
            sendEventAnnouncement($data);
        } elseif ($action === 'save_email_settings') {
            saveAgentEmailSettings($data);
        } elseif ($action === 'log_whatsapp') {
            // Tarayıcıdan gelen WhatsApp log kaydı
            $logId = uniqid('alog_');
            $sql = "INSERT INTO agent_logs (id, agent_name, action, channel, recipient_id, recipient_name, details, status) VALUES (?, ?, 'message', 'whatsapp', ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $agentName = $data['agent_name'] ?? 'unknown';
            $recipientId = $data['recipient_id'] ?? '';
            $recipientName = $data['recipient_name'] ?? '';
            $detail = $data['detail'] ?? 'WhatsApp mesajı gönderildi';
            $status = $data['status'] ?? 'success';
            $stmt->bind_param("ssssss", $logId, $agentName, $recipientId, $recipientName, $detail, $status);
            $stmt->execute();
            sendResponse(true, 'WhatsApp log kaydedildi');
        } else {
            sendResponse(false, 'Geçersiz agent isteği');
        }
        break;
    case 'DELETE':
        if ($action === 'delete_log') {
            $id = $_GET['id'] ?? '';
            deleteAgentLog($id);
        } elseif ($action === 'delete_logs') {
            $data = json_decode(file_get_contents("php://input"), true);
            deleteAgentLogs($data['ids'] ?? []);
        } else {
            sendResponse(false, 'Geçersiz silme isteği');
        }
        break;
    default:
        sendResponse(false, 'Geçersiz istek yöntemi');
}

// ─── Agent Log CRUD ────────────────────────────────────

function saveAgentLog($data)
{
    global $conn;

    $required = ['agent_name', 'action'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendResponse(false, "Gerekli alan eksik: $field");
            return;
        }
    }

    try {
        $sql = "INSERT INTO agent_logs (id, agent_name, action, channel, recipient_id, recipient_name, details, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception('Sorgu hazırlanamadı: ' . $conn->error);

        $id = $data['id'] ?? uniqid('alog_');
        $agent_name = $data['agent_name'];
        $action = $data['action'];
        $channel = $data['channel'] ?? '';
        $recipient_id = $data['recipient_id'] ?? '';
        $recipient_name = $data['recipient_name'] ?? '';
        $details = $data['details'] ?? '';
        $status = $data['status'] ?? 'success';

        $stmt->bind_param('ssssssss', $id, $agent_name, $action, $channel, $recipient_id, $recipient_name, $details, $status);

        if (!$stmt->execute())
            throw new Exception('Log kaydedilemedi: ' . $stmt->error);

        $stmt->close();
        sendResponse(true, 'Agent log kaydedildi', ['id' => $id]);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Agent log hatası: ' . $e->getMessage());
    }
}

function getAgentLogs()
{
    global $conn;

    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 500) : 100;
    $agent = isset($_GET['agent']) ? $_GET['agent'] : '';

    try {
        if ($agent) {
            $sql = "SELECT * FROM agent_logs WHERE agent_name = ? ORDER BY created_at DESC LIMIT ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt)
                throw new Exception($conn->error);
            $stmt->bind_param('si', $agent, $limit);
        } else {
            $sql = "SELECT * FROM agent_logs ORDER BY created_at DESC LIMIT ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt)
                throw new Exception($conn->error);
            $stmt->bind_param('i', $limit);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();

        sendResponse(true, 'Agent logları alındı', $logs);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Agent log hatası: ' . $e->getMessage());
    }
}

// ─── Agent Config ──────────────────────────────────────

function getAgentConfig()
{
    global $conn;
    try {
        $sql = "SELECT setting_value FROM settings WHERE setting_key = 'agent_config'";
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $config = json_decode($row['setting_value'], true);
            sendResponse(true, 'Agent config alındı', $config);
        } else {
            // Default config
            $defaultConfig = getDefaultAgentConfig();
            sendResponse(true, 'Varsayılan agent config', $defaultConfig);
        }
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Config hatası: ' . $e->getMessage());
    }
}

function saveAgentConfig($data)
{
    global $conn;
    try {
        $jsonValue = json_encode($data, JSON_UNESCAPED_UNICODE);
        $key = 'agent_config';
        $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception($conn->error);
        $stmt->bind_param('sss', $key, $jsonValue, $jsonValue);
        if (!$stmt->execute())
            throw new Exception($stmt->error);
        $stmt->close();
        sendResponse(true, 'Agent config kaydedildi');
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Config kayıt hatası: ' . $e->getMessage());
    }
}

function getDefaultAgentConfig()
{
    return [
        'payment_confirm' => ['enabled' => true, 'channels' => ['email', 'whatsapp']],
        'welcome' => ['enabled' => true, 'channels' => ['email', 'whatsapp']],
        'debt_reminder' => ['enabled' => true, 'channels' => ['email'], 'escalation_days' => [30, 60, 90]],
        'yearly_charge' => ['enabled' => true, 'auto_run' => false],
        'accounting' => ['enabled' => true],
        'birthday' => ['enabled' => true, 'channels' => ['email', 'whatsapp']],
        'monthly_report' => ['enabled' => true, 'recipients' => getEmailSettingRecipients('monthly_report')],
        'event_announce' => ['enabled' => true, 'channels' => ['email']]
    ];
}

// ─── Agent Stats ───────────────────────────────────────

function getAgentStats()
{
    global $conn;
    try {
        // Monthly stats
        $monthStart = date('Y-m-01');
        $sql = "SELECT 
                    agent_name,
                    COUNT(*) as total_actions,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error_count,
                    SUM(CASE WHEN channel = 'email' THEN 1 ELSE 0 END) as email_count,
                    SUM(CASE WHEN channel = 'sms' THEN 1 ELSE 0 END) as sms_count,
                    SUM(CASE WHEN channel = 'whatsapp' THEN 1 ELSE 0 END) as whatsapp_count,
                    MAX(created_at) as last_run
                FROM agent_logs 
                WHERE created_at >= '$monthStart'
                GROUP BY agent_name";

        $result = $conn->query($sql);
        if (!$result)
            throw new Exception($conn->error);

        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[$row['agent_name']] = $row;
        }

        // Collection rate
        $collectionSql = "SELECT 
                            SUM(CASE WHEN status = 'tamamlandı' THEN amount ELSE 0 END) as collected,
                            SUM(amount) as total
                          FROM payments WHERE type = 'aidat'";
        $cResult = $conn->query($collectionSql);
        $collection = $cResult ? $cResult->fetch_assoc() : ['collected' => 0, 'total' => 0];

        // This month collection
        $monthCollectionSql = "SELECT SUM(amount) as monthly_collected 
                               FROM payments 
                               WHERE type = 'aidat' AND status = 'tamamlandı' 
                               AND date >= '$monthStart'";
        $mResult = $conn->query($monthCollectionSql);
        $monthlyCollection = $mResult ? $mResult->fetch_assoc() : ['monthly_collected' => 0];

        // Pending debt count
        $pendingSql = "SELECT COUNT(DISTINCT memberId) as debtor_count, SUM(amount) as total_debt 
                       FROM payments WHERE type = 'aidat' AND status = 'bekliyor'";
        $pResult = $conn->query($pendingSql);
        $pending = $pResult ? $pResult->fetch_assoc() : ['debtor_count' => 0, 'total_debt' => 0];

        sendResponse(true, 'Agent istatistikleri', [
            'agents' => $stats,
            'collection' => [
                'total_collected' => (float) ($collection['collected'] ?? 0),
                'total_due' => (float) ($collection['total'] ?? 0),
                'rate' => $collection['total'] > 0 ? round(($collection['collected'] / $collection['total']) * 100, 1) : 0,
                'monthly_collected' => (float) ($monthlyCollection['monthly_collected'] ?? 0)
            ],
            'pending' => [
                'debtor_count' => (int) ($pending['debtor_count'] ?? 0),
                'total_debt' => (float) ($pending['total_debt'] ?? 0)
            ]
        ]);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'İstatistik hatası: ' . $e->getMessage());
    }
}

// ─── Birthday Check ────────────────────────────────────

function getTodayBirthdays()
{
    global $conn;
    try {
        $today = date('m-d');
        // birthDate is KVKK encrypted, so we must decrypt in PHP
        $sql = "SELECT id, memberNo, firstName, lastName, email, phone, birthDate, membershipType 
                FROM members 
                WHERE membershipStatus = 'aktif'
                AND birthDate IS NOT NULL AND birthDate != ''";
        $result = $conn->query($sql);
        if (!$result)
            throw new Exception($conn->error);

        $birthdays = [];
        while ($row = $result->fetch_assoc()) {
            $decryptedDate = kvkk_decrypt($row['birthDate']);
            if ($decryptedDate && strlen($decryptedDate) >= 10) {
                $memberDate = date('m-d', strtotime($decryptedDate));
                if ($memberDate === $today) {
                    $row['birthDate'] = $decryptedDate;
                    // Also decrypt phone for contact
                    if (isset($row['phone']))
                        $row['phone'] = kvkk_decrypt($row['phone']);
                    $birthdays[] = $row;
                }
            }
        }

        sendResponse(true, 'Bugünkü doğum günleri', $birthdays);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Doğum günü sorgusu hatası: ' . $e->getMessage());
    }
}

// ─── Debtors for Reminder ──────────────────────────────

function getDebtorsForReminder()
{
    global $conn;
    try {
        $year = isset($_GET['year']) ? $_GET['year'] : 'all';
        $tier = isset($_GET['tier']) ? intval($_GET['tier']) : 0;

        // Get debtors with their reminder history
        $sql = "SELECT m.id, m.memberNo, m.firstName, m.lastName, m.email, m.phone,
                       m.membershipType,
                       SUM(p.amount) as total_debt,
                       GROUP_CONCAT(DISTINCT p.year ORDER BY p.year DESC) as debt_years,
                       COUNT(DISTINCT p.year) as debt_year_count,
                       (SELECT COUNT(*) FROM agent_logs al 
                        WHERE al.recipient_id = m.id 
                        AND al.agent_name = 'debt_reminder'
                        AND al.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)) as reminder_count
                FROM members m
                JOIN payments p ON p.memberId = m.id
                WHERE p.type = 'aidat' AND p.status = 'bekliyor'
                AND m.membershipStatus = 'aktif'";

        if ($year !== 'all') {
            $sql .= " AND p.year = " . intval($year);
        }

        $sql .= " GROUP BY m.id";

        // Filter by reminder tier if specified
        if ($tier > 0) {
            $sql .= " HAVING reminder_count >= " . ($tier - 1) . " AND reminder_count < $tier";
        }

        $sql .= " ORDER BY total_debt DESC";

        $result = $conn->query($sql);
        if (!$result)
            throw new Exception($conn->error);

        $debtors = [];
        while ($row = $result->fetch_assoc()) {
            $debtors[] = [
                'memberId' => $row['id'],
                'memberNo' => $row['memberNo'],
                'fullName' => trim($row['firstName'] . ' ' . $row['lastName']),
                'email' => $row['email'],
                'phone' => kvkk_decrypt($row['phone']),
                'membershipType' => $row['membershipType'],
                'totalDebt' => (float) $row['total_debt'],
                'debtYears' => $row['debt_years'],
                'debtYearCount' => (int) $row['debt_year_count'],
                'reminderCount' => (int) $row['reminder_count'],
                'tier' => $row['reminder_count'] == 0 ? 1 : ($row['reminder_count'] < 3 ? 2 : 3)
            ];
        }

        sendResponse(true, 'Borçlu üyeler listelendi', [
            'data' => $debtors,
            'count' => count($debtors)
        ]);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Borçlu listesi hatası: ' . $e->getMessage());
    }
}

// ─── Run Agent ─────────────────────────────────────────

function runAgent($data)
{
    $agentName = $data['agent'] ?? '';

    switch ($agentName) {
        case 'birthday':
            runBirthdayAgent($data);
            break;
        case 'yearly_charge':
            runYearlyChargeAgent($data);
            break;
        case 'monthly_report':
            runMonthlyReportAgent($data);
            break;
        default:
            sendResponse(false, "Bilinmeyen agent: $agentName");
    }
}

function runBirthdayAgent($data)
{
    global $conn;
    try {
        $whatsappQueue = [];
        $today = date('m-d');
        // birthDate is KVKK encrypted, so we must decrypt in PHP
        $sql = "SELECT id, memberNo, firstName, lastName, email, phone, birthDate 
                FROM members 
                WHERE membershipStatus = 'aktif'
                AND birthDate IS NOT NULL AND birthDate != ''";
        $result = $conn->query($sql);
        if (!$result)
            throw new Exception($conn->error);

        $processed = 0;
        while ($row = $result->fetch_assoc()) {
            $decryptedDate = kvkk_decrypt($row['birthDate']);
            if (!$decryptedDate || strlen($decryptedDate) < 10)
                continue;
            $memberDate = date('m-d', strtotime($decryptedDate));
            if ($memberDate !== $today)
                continue;

            $member = $row;
            $member['birthDate'] = $decryptedDate;
            $age = date('Y') - date('Y', strtotime($decryptedDate));
            $recipientName = $member['firstName'] . ' ' . $member['lastName'];
            $detail = "🎂 Mutlu Yıllar! Sayın {$recipientName}, {$age}. yaş...";

            // Email gönder - kart tasarımı
            $mailStatus = 'success';
            if (!empty($member['email'])) {
                $subject = "=?UTF-8?B?" . base64_encode("🎂 Mutlu Yıllar! - TPJD") . "?=";
                $headers = "From: =?UTF-8?B?" . base64_encode("TPJD Derneği") . "?= <uye@tpjd.org.tr>\r\n";
                $headers .= "Reply-To: uye@tpjd.org.tr\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8";

                $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f0f0f0;font-family:Arial,Helvetica,sans-serif;">
                <div style="max-width:600px;margin:20px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
                    
                    <!-- Header -->
                    <div style="background:linear-gradient(135deg,#e74c3c,#e67e22,#f1c40f);padding:40px 30px;text-align:center;">
                        <div style="font-size:64px;margin-bottom:10px;">🎂</div>
                        <h1 style="color:white;margin:0;font-size:28px;text-shadow:0 2px 4px rgba(0,0,0,0.2);">Mutlu Yıllar!</h1>
                        <div style="background:rgba(255,255,255,0.25);display:inline-block;padding:8px 24px;border-radius:20px;margin-top:15px;">
                            <span style="color:white;font-size:20px;font-weight:bold;">' . $age . '. Yaş</span>
                        </div>
                    </div>

                    <!-- Body -->
                    <div style="padding:35px 30px;">
                        <p style="font-size:18px;color:#2c3e50;margin:0 0 15px;">Sayın <strong>' . htmlspecialchars($recipientName) . '</strong>,</p>
                        <p style="font-size:16px;color:#555;line-height:1.8;margin:0 0 20px;">
                            ' . $age . '. yaş gününüzü en içten dileklerimizle kutlar, sağlık ve mutluluk dolu nice yıllar dileriz.
                        </p>
                        <p style="font-size:16px;color:#555;line-height:1.8;margin:0 0 20px;">
                            TPJD ailesinin değerli bir üyesi olarak, derneğimize kattığınız değer için teşekkür ederiz. 🎉
                        </p>
                        <div style="background:#fef9e7;border-left:4px solid #f1c40f;padding:15px 20px;border-radius:0 8px 8px 0;margin:20px 0;">
                            <p style="margin:0;color:#7d6608;font-size:14px;font-style:italic;">
                                "Her yeni yaş, yeni bir başlangıçtır. Nice güzel yaşlara!" 🌟
                            </p>
                        </div>
                        <p style="font-size:15px;color:#888;margin:20px 0 0;">Sevgi ve saygılarımızla,</p>
                        <p style="font-size:16px;color:#2c3e50;font-weight:bold;margin:5px 0 0;">TPJD Yönetim Kurulu</p>
                    </div>

                    <!-- Footer -->
                    <div style="background:#2c3e50;padding:20px;text-align:center;">
                        <p style="color:#ecf0f1;margin:0;font-size:13px;">Türkiye Petrol Jeologları Derneği</p>
                        <p style="color:#95a5a6;margin:5px 0 0;font-size:12px;">TPJD Üyelik Sistemi | <a href="https://www.tpjd.org.tr" style="color:#3498db;text-decoration:none;">www.tpjd.org.tr</a></p>
                    </div>
                </div>
                </body></html>';

                $sent = sendEmailInternalHelper($member['email'], $recipientName, $subject, $body);
                $mailStatus = $sent ? 'success' : 'error';
            }

            // WhatsApp doğum günü tebriği — kuyruğa ekle (tarayıcı gönderecek)
            $decryptedPhone = isset($member['phone']) ? kvkk_decrypt($member['phone']) : '';
            if (!empty($decryptedPhone)) {
                $waPhone = preg_replace('/[^0-9]/', '', $decryptedPhone);
                if (substr($waPhone, 0, 1) === '0')
                    $waPhone = '90' . substr($waPhone, 1);
                elseif (substr($waPhone, 0, 2) !== '90')
                    $waPhone = '90' . $waPhone;

                $whatsappQueue[] = [
                    'number' => $waPhone,
                    'message' => "🎂 Mutlu Yıllar!\n\nSayın {$recipientName},\n\n{$age}. yaş gününüzü en içten dileklerimizle kutlar, sağlık ve mutluluk dolu nice yıllar dileriz.\n\nTPJD ailesinin değerli bir üyesi olarak, derneğimize kattığınız değer için teşekkür ederiz. 🎉\n\nSevgi ve saygılarımızla,\nTPJD Yönetim Kurulu",
                    'recipient_id' => $member['id'],
                    'recipient_name' => $recipientName,
                    'detail' => "🎂 WhatsApp doğum günü tebriği - {$age}. yaş"
                ];
            }

            // Email log yaz
            $logId = uniqid('alog_');
            $logSql = "INSERT INTO agent_logs (id, agent_name, action, channel, recipient_id, recipient_name, details, status) VALUES ('" . $conn->real_escape_string($logId) . "', 'birthday', 'greeting', 'email', '" . $conn->real_escape_string($member['id']) . "', '" . $conn->real_escape_string($recipientName) . "', '" . $conn->real_escape_string($detail) . "', '" . $conn->real_escape_string($mailStatus) . "')";
            $conn->query($logSql);

            $processed++;
        }

        sendResponse(true, "Doğum günü agent'ı çalıştı - v4 (KVKK uyumlu)", ['processed' => $processed, 'whatsapp_queue' => $whatsappQueue]);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Doğum günü agent hatası: ' . $e->getMessage());
    }
}

function runYearlyChargeAgent($data)
{
    global $conn;
    $year = isset($data['year']) ? intval($data['year']) : intval(date('Y'));

    $conn->begin_transaction();
    try {
        $sql_settings = "SELECT setting_value FROM settings WHERE setting_key = 'aidat_settings'";
        $result_settings = $conn->query($sql_settings);
        $aidatSettings = [];
        if ($result_settings && $result_settings->num_rows > 0) {
            $row = $result_settings->fetch_assoc();
            $decoded = json_decode($row['setting_value'], true);
            if (is_array($decoded))
                $aidatSettings = $decoded;
        }

        $sql_members = "SELECT id, membershipType FROM members WHERE aidatAktif = 1 AND membershipStatus = 'aktif'";
        $result_members = $conn->query($sql_members);

        $existingDebts = [];
        $sql_existing = "SELECT memberId FROM payments WHERE year = ? AND type = 'aidat'";
        $stmt_existing = $conn->prepare($sql_existing);
        $stmt_existing->bind_param('i', $year);
        $stmt_existing->execute();
        $stmt_existing->bind_result($existingMemberId);
        while ($stmt_existing->fetch()) {
            $existingDebts[$existingMemberId] = true;
        }
        $stmt_existing->close();

        $newDebts = 0;
        $skippedCount = 0;

        while ($member = $result_members->fetch_assoc()) {
            $memberId = $member['id'];
            $membershipType = $member['membershipType'];

            if (isset($existingDebts[$memberId])) {
                $skippedCount++;
                continue;
            }

            $duesAmount = $aidatSettings[$membershipType] ?? 0;
            if (empty($aidatSettings) && $membershipType === 'Asil Üye')
                $duesAmount = 1500;
            if ($duesAmount <= 0) {
                $skippedCount++;
                continue;
            }

            $debId = uniqid('p_');
            $type = 'aidat';
            $date = "$year-01-01";
            $desc = "$year yılı $membershipType aidatı";
            $status = 'bekliyor';

            $sql_insert = "INSERT INTO payments (id, memberId, amount, type, year, date, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param('ssdsssss', $debId, $memberId, $duesAmount, $type, $year, $date, $desc, $status);
            $stmt_insert->execute();
            $newDebts++;
        }

        $conn->commit();
        $success = true;
        $message = "Otomatik borçlandırma ($year) tamamlandı. Eklenen: $newDebts, Atlanan: $skippedCount";
    } catch (Exception $e) {
        $conn->rollback();
        $success = false;
        $message = "Borçlandırma hatası: " . $e->getMessage();
    }

    $logSql = "INSERT INTO agent_logs (id, agent_name, action, channel, details, status) VALUES (?, 'yearly_charge', 'batch_charge', 'system', ?, ?)";
    $logStmt = $conn->prepare($logSql);
    $logId = uniqid('alog_');
    $logStatus = $success ? 'success' : 'error';
    $logStmt->bind_param('sss', $logId, $message, $logStatus);
    $logStmt->execute();

    if (isset($data['internal']) && $data['internal'])
        return ['success' => $success, 'message' => $message];
    sendResponse($success, $message);
}

// ─── WhatsApp Settings Helper ──────────────────────────

function getWhatsAppSettings()
{
    global $conn;
    try {
        $sql = "SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_settings'";
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            return json_decode($row['setting_value'], true);
        }
    } catch (Exception $e) {
        logError('WhatsApp settings error: ' . $e->getMessage());
    }
    return null;
}

// ─── Send Notification via Agent ───────────────────────

function sendAgentNotification($data)
{
    global $conn;

    $channel = $data['channel'] ?? 'email';
    $recipientId = $data['recipient_id'] ?? '';
    $subject = $data['subject'] ?? 'TPJD Bildirim';
    $message = $data['message'] ?? '';
    $agentName = $data['agent_name'] ?? 'manual';
    $isInternal = $data['internal'] ?? false;

    if (empty($recipientId) || empty($message)) {
        if ($isInternal)
            return ['channel' => $channel, 'status' => 'error', 'detail' => 'Alıcı ve mesaj gerekli'];
        sendResponse(false, 'Alıcı ve mesaj gerekli');
        return;
    }

    // Get member info
    $stmt = $conn->prepare("SELECT firstName, lastName, email, phone FROM members WHERE id = ?");
    $stmt->bind_param('s', $recipientId);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$member) {
        if ($isInternal)
            return ['channel' => $channel, 'status' => 'error', 'detail' => 'Üye bulunamadı'];
        sendResponse(false, 'Üye bulunamadı');
        return;
    }

    $recipientName = $member['firstName'] . ' ' . $member['lastName'];
    $result = ['channel' => $channel, 'status' => 'pending'];

    if ($channel === 'email' && !empty($member['email'])) {
        // Use existing send_email logic
        $emailData = [
            'recipients' => [['email' => $member['email'], 'name' => $recipientName]],
            'subject' => $subject,
            'message' => $message
        ];

        // Internal email send
        $headers = [
            'From: TPJD Derneği <uye@tpjd.org.tr>',
            'Reply-To: uye@tpjd.org.tr',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8'
        ];

        $htmlMessage = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>
            <div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;">
            <div style="background:#2c3e50;color:white;padding:20px;text-align:center;">
                <h2>Türkiye Petrol Jeologları Derneği</h2>
            </div>
            <div style="padding:20px;background:#f9f9f9;">
                <h3>' . htmlspecialchars($subject) . '</h3>
                <div>' . nl2br(htmlspecialchars($message)) . '</div>
            </div>
            <div style="background:#34495e;color:white;padding:15px;text-align:center;font-size:12px;">
                <p>TPJD Üyelik Sistemi | www.tpjd.org.tr</p>
            </div></div></body></html>';

        $mailSent = sendEmailInternalHelper($member['email'], $recipientName, $subject, $htmlMessage);
        $result['status'] = $mailSent ? 'success' : 'error';
        $result['detail'] = $mailSent ? 'Email gönderildi' : 'Email gönderilemedi';

    } elseif ($channel === 'sms' && !empty($member['phone'])) {
        // Will use NetGSM - for now just log
        $result['status'] = 'pending';
        $result['detail'] = 'SMS kuyruğa alındı';

    } elseif ($channel === 'whatsapp' && !empty($member['phone'])) {
        $phone = preg_replace('/[^0-9]/', '', $member['phone']);
        if (substr($phone, 0, 1) === '0')
            $phone = '90' . substr($phone, 1);
        elseif (substr($phone, 0, 2) !== '90')
            $phone = '90' . $phone;

        // WhatsApp mesajı tarayıcı tarafından gönderilecek (localhost:3456)
        $result['status'] = 'pending';
        $result['detail'] = 'WhatsApp kuyruğa alındı';
        $result['whatsapp_data'] = [
            'number' => $phone,
            'message' => $message
        ];
    }

    // If called internally (from another agent), return result directly — don't log or send HTTP response
    $isInternal = $data['internal'] ?? false;
    if ($isInternal) {
        return $result;
    }

    // Log the notification
    $logSql = "INSERT INTO agent_logs (id, agent_name, action, channel, recipient_id, recipient_name, details, status) 
               VALUES (?, ?, 'notification', ?, ?, ?, ?, ?)";
    $logStmt = $conn->prepare($logSql);
    $logId = uniqid('alog_');
    $details = $subject . ': ' . substr($message, 0, 200);
    $logStmt->bind_param('sssssss', $logId, $agentName, $channel, $recipientId, $recipientName, $details, $result['status']);
    $logStmt->execute();
    $logStmt->close();

    sendResponse(true, 'Bildirim işlendi', $result);
}

// ─── Event Announcement ────────────────────────────────────
function sendEventAnnouncement($data)
{
    global $conn;

    $title = $data['title'] ?? '';
    $date = $data['date'] ?? '';
    $time = $data['time'] ?? '10:00';
    $location = $data['location'] ?? '';
    $description = $data['description'] ?? '';
    $target = $data['target'] ?? 'all';
    $channel = $data['channel'] ?? 'email';
    $doEmail = ($channel === 'email' || $channel === 'both');

    if (empty($title) || empty($date) || empty($description)) {
        sendResponse(false, 'Etkinlik adı, tarih ve açıklama zorunludur');
        return;
    }

    // Format date for display
    $dateObj = new DateTime($date);
    $months = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    $displayDate = $dateObj->format('d') . ' ' . $months[(int) $dateObj->format('m') - 1] . ' ' . $dateObj->format('Y');
    $displayTime = $time;

    // Get target members
    if ($target === 'paid') {
        $year = date('Y');
        $sql = "SELECT DISTINCT m.id, m.firstName, m.lastName, m.email, m.phone 
                FROM members m 
                INNER JOIN payments p ON m.id = p.memberId AND p.year = ?
                WHERE m.membershipStatus = 'aktif' AND m.email IS NOT NULL AND m.email != ''";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $year);
    } else {
        $sql = "SELECT id, firstName, lastName, email, phone FROM members 
                WHERE membershipStatus = 'aktif' AND email IS NOT NULL AND email != ''";
        $stmt = $conn->prepare($sql);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $sent = 0;
    $errors = 0;
    $whatsappQueue = [];

    // Build WhatsApp message text
    $waMessage = "\xF0\x9F\x93\xA2 *" . $title . "*\n\n\xF0\x9F\x93\x85 Tarih: " . $displayDate . "\n\xF0\x9F\x95\x90 Saat: " . $displayTime;
    if (!empty($location))
        $waMessage .= "\n\xF0\x9F\x93\x8D Konum: " . $location;
    $waMessage .= "\n\n" . $description . "\n\n_TPJD_";

    // Email subject & headers
    $subject = "=?UTF-8?B?" . base64_encode("📢 " . $title . " - TPJD") . "?=";
    $headers = "From: =?UTF-8?B?" . base64_encode("TPJD Derneği") . "?= <uye@tpjd.org.tr>\r\n";
    $headers .= "Reply-To: uye@tpjd.org.tr\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8";

    // Location block (only if provided)
    $locationBlock = '';
    if (!empty($location)) {
        $locationBlock = '
        <tr>
            <td style="padding:8px 0;vertical-align:top;">
                <span style="font-size:20px;">📍</span>
            </td>
            <td style="padding:8px 0 8px 12px;">
                <div style="font-size:12px;color:#95a5a6;text-transform:uppercase;letter-spacing:1px;">Konum</div>
                <div style="font-size:16px;color:#2c3e50;font-weight:600;">' . htmlspecialchars($location) . '</div>
            </td>
        </tr>';
    }

    while ($member = $result->fetch_assoc()) {
        if (empty($member['email']))
            continue;

        $recipientName = $member['firstName'] . ' ' . $member['lastName'];

        $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f0f0f0;font-family:Arial,Helvetica,sans-serif;">
        <div style="max-width:600px;margin:20px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
            
            <!-- Header -->
            <div style="background:linear-gradient(135deg,#ff5722,#ff9800);padding:35px 30px;text-align:center;">
                <div style="font-size:48px;margin-bottom:8px;">📢</div>
                <h1 style="color:white;margin:0;font-size:24px;text-shadow:0 2px 4px rgba(0,0,0,0.2);">Etkinlik Duyurusu</h1>
                <div style="background:rgba(255,255,255,0.25);display:inline-block;padding:8px 24px;border-radius:20px;margin-top:12px;">
                    <span style="color:white;font-size:16px;font-weight:bold;">' . htmlspecialchars($title) . '</span>
                </div>
            </div>

            <!-- Body -->
            <div style="padding:30px;">
                <p style="font-size:17px;color:#2c3e50;margin:0 0 20px;">Sayın <strong>' . htmlspecialchars($recipientName) . '</strong>,</p>
                <p style="font-size:15px;color:#555;line-height:1.7;margin:0 0 25px;">
                    Sizi aşağıdaki etkinliğimize davet etmekten mutluluk duyarız:
                </p>

                <!-- Event Details Card -->
                <div style="background:#f8f9fa;border-radius:10px;padding:20px;margin-bottom:25px;border:1px solid #e9ecef;">
                    <h2 style="margin:0 0 15px;color:#ff5722;font-size:20px;">' . htmlspecialchars($title) . '</h2>
                    <table style="width:100%;border-collapse:collapse;">
                        <tr>
                            <td style="padding:8px 0;vertical-align:top;">
                                <span style="font-size:20px;">📅</span>
                            </td>
                            <td style="padding:8px 0 8px 12px;">
                                <div style="font-size:12px;color:#95a5a6;text-transform:uppercase;letter-spacing:1px;">Tarih</div>
                                <div style="font-size:16px;color:#2c3e50;font-weight:600;">' . $displayDate . '</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0;vertical-align:top;">
                                <span style="font-size:20px;">🕐</span>
                            </td>
                            <td style="padding:8px 0 8px 12px;">
                                <div style="font-size:12px;color:#95a5a6;text-transform:uppercase;letter-spacing:1px;">Saat</div>
                                <div style="font-size:16px;color:#2c3e50;font-weight:600;">' . $displayTime . '</div>
                            </td>
                        </tr>
                        ' . $locationBlock . '
                    </table>
                </div>

                <!-- Description -->
                <div style="background:#fff8f0;border-left:4px solid #ff9800;padding:15px 20px;border-radius:0 8px 8px 0;margin-bottom:20px;">
                    <p style="margin:0;color:#555;font-size:15px;line-height:1.8;">' . nl2br(htmlspecialchars($description)) . '</p>
                </div>

                <p style="font-size:14px;color:#888;margin:20px 0 0;">Katılımınızı bekliyoruz.</p>
                <p style="font-size:15px;color:#2c3e50;font-weight:bold;margin:5px 0 0;">TPJD Yönetim Kurulu</p>
            </div>

            <!-- Footer -->
            <div style="background:#2c3e50;padding:20px;text-align:center;">
                <p style="color:#ecf0f1;margin:0;font-size:13px;">Türkiye Petrol Jeologları Derneği</p>
                <p style="color:#95a5a6;margin:5px 0 0;font-size:12px;">TPJD Üyelik Sistemi | <a href="https://www.tpjd.org.tr" style="color:#3498db;text-decoration:none;">www.tpjd.org.tr</a></p>
            </div>
        </div>
        </body></html>';

        // Send email only if channel is email or both
        if ($doEmail) {
            $mailSent = sendEmailInternalHelper($member['email'], $recipientName, $subject, $body);
        } else {
            $mailSent = false; // skip email — whatsapp only
        }

        // Log each send
        $logId = uniqid('alog_');
        $status = $mailSent ? 'success' : 'error';
        $detail = "📢 Etkinlik: " . $title . " (" . $displayDate . ")";
        $logSql = "INSERT INTO agent_logs (id, agent_name, action, channel, recipient_id, recipient_name, details, status) 
                   VALUES ('" . $conn->real_escape_string($logId) . "', 'event_announce', 'announcement', 'email', 
                   '" . $conn->real_escape_string($member['id']) . "', '" . $conn->real_escape_string($recipientName) . "', 
                   '" . $conn->real_escape_string($detail) . "', '" . $conn->real_escape_string($status) . "')";
        $conn->query($logSql);

        if ($mailSent)
            $sent++;
        else
            $errors++;

        // WhatsApp kuyruğuna ekle
        $decryptedPhone = isset($member['phone']) ? kvkk_decrypt($member['phone']) : '';
        if (!empty($decryptedPhone)) {
            $waPhone = preg_replace('/[^0-9]/', '', $decryptedPhone);
            if (substr($waPhone, 0, 1) === '0')
                $waPhone = '90' . substr($waPhone, 1);
            elseif (substr($waPhone, 0, 2) !== '90')
                $waPhone = '90' . $waPhone;

            $whatsappQueue[] = [
                'number' => $waPhone,
                'message' => $waMessage,
                'recipient_id' => $member['id'],
                'recipient_name' => $recipientName,
                'detail' => "\xF0\x9F\x93\xA2 Etkinlik duyurusu: " . $title
            ];
        }
    }
    $stmt->close();

    sendResponse(true, "Etkinlik duyurusu gönderildi", ['sent' => $sent, 'errors' => $errors, 'title' => $title, 'whatsapp_queue' => $whatsappQueue]);
}

// ─── Delete Agent Log(s) ────────────────────────────────────
function deleteAgentLog($id)
{
    global $conn;
    if (empty($id)) {
        sendResponse(false, 'Log ID gerekli');
        return;
    }
    try {
        $stmt = $conn->prepare("DELETE FROM agent_logs WHERE id = ?");
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        sendResponse(true, 'Log silindi', ['deleted' => $affected]);
    } catch (Exception $e) {
        sendResponse(false, 'Silme hatası: ' . $e->getMessage());
    }
}

function deleteAgentLogs($ids)
{
    global $conn;
    if (empty($ids) || !is_array($ids)) {
        sendResponse(false, 'Silinecek ID listesi gerekli');
        return;
    }
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('s', count($ids));
        $stmt = $conn->prepare("DELETE FROM agent_logs WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        sendResponse(true, "$affected log silindi", ['deleted' => $affected]);
    } catch (Exception $e) {
        sendResponse(false, 'Toplu silme hatası: ' . $e->getMessage());
    }
}

// ─── Agent Email Settings ──────────────────────────────

function saveAgentEmailSettings($data)
{
    $settingsFile = __DIR__ . '/agent_email_settings.json';
    $settings = [
        'monthly_report_recipients' => $data['monthly_report_recipients'] ?? [],
        'notify_recipients' => $data['notify_recipients'] ?? [],
        'president_email' => $data['president_email'] ?? '',
        'treasurer_email' => $data['treasurer_email'] ?? '',
        'secretary_email' => $data['secretary_email'] ?? ''
    ];

    if (file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        sendResponse(true, 'Agent mail ayarları kaydedildi', $settings);
    } else {
        sendResponse(false, 'Ayarlar kaydedilemedi');
    }
}

function getEmailSettingRecipients($agentType)
{
    $settingsFile = __DIR__ . '/agent_email_settings.json';
    if (!file_exists($settingsFile)) {
        return ['uye@tpjd.org.tr']; // default fallback
    }

    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!$settings)
        return ['uye@tpjd.org.tr'];

    switch ($agentType) {
        case 'monthly_report':
            $recipients = $settings['monthly_report_recipients'] ?? [];
            return !empty($recipients) ? $recipients : ['uye@tpjd.org.tr'];
        case 'notify':
            return $settings['notify_recipients'] ?? [];
        default:
            return [];
    }
}

// ─── Cron Check (Auto-Run with Escalation) ─────────────

function checkCronStatus()
{
    global $conn;
    try {
        $today = date('Y-m-d');

        // ── Birthday check ──
        $bdSql = "SELECT COUNT(*) as cnt FROM agent_logs 
                  WHERE agent_name = 'birthday' AND DATE(created_at) = ?";
        $bdStmt = $conn->prepare($bdSql);
        $bdStmt->bind_param('s', $today);
        $bdStmt->execute();
        $bdResult = $bdStmt->get_result()->fetch_assoc();
        $bdStmt->close();

        // ── Monthly report check ──
        $mrSql = "SELECT COUNT(*) as cnt FROM agent_logs 
                  WHERE agent_name = 'monthly_report' AND DATE(created_at) = ?";
        $mrStmt = $conn->prepare($mrSql);
        $mrStmt->bind_param('s', $today);
        $mrStmt->execute();
        $mrResult = $mrStmt->get_result()->fetch_assoc();
        $mrStmt->close();
        $isFirstOfMonth = date('j') === '1';

        // ── Debt Reminder — 3 Kademeli Eskalasyon ──
        // Her borçlu üye için: kaç hatırlatma almış + son hatırlatma ne zaman
        $debtSql = "SELECT 
            m.id,
            (SELECT COUNT(*) FROM agent_logs al 
             WHERE al.recipient_id = m.id 
             AND al.agent_name = 'debt_reminder' 
             AND al.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)) as reminder_count,
            (SELECT MAX(al.created_at) FROM agent_logs al 
             WHERE al.recipient_id = m.id 
             AND al.agent_name = 'debt_reminder') as last_reminder
            FROM members m
            JOIN payments p ON p.memberId = m.id
            WHERE p.type = 'aidat' AND p.status = 'bekliyor' 
            AND m.membershipStatus = 'aktif'
            GROUP BY m.id";

        $debtResult = $conn->query($debtSql);
        $tierCounts = [1 => 0, 2 => 0, 3 => 0];

        if ($debtResult) {
            while ($row = $debtResult->fetch_assoc()) {
                $count = (int) $row['reminder_count'];
                $lastReminder = $row['last_reminder'];
                $daysSinceLast = $lastReminder ?
                    (int) ((time() - strtotime($lastReminder)) / 86400) : 999;

                // Kademe 1: Hiç hatırlatma almamış
                if ($count === 0) {
                    $tierCounts[1]++;
                }
                // Kademe 2: 1 hatırlatma almış + en az 30 gün geçmiş
                elseif ($count === 1 && $daysSinceLast >= 30) {
                    $tierCounts[2]++;
                }
                // Kademe 3: 2 hatırlatma almış + en az 30 gün geçmiş
                elseif ($count === 2 && $daysSinceLast >= 30) {
                    $tierCounts[3]++;
                }
            }
        }

        // Bugün herhangi bir debt_reminder çalışmış mı?
        $drTodaySql = "SELECT COUNT(*) as cnt FROM agent_logs 
                       WHERE agent_name = 'debt_reminder' AND DATE(created_at) = ?";
        $drStmt = $conn->prepare($drTodaySql);
        $drStmt->bind_param('s', $today);
        $drStmt->execute();
        $drTodayResult = $drStmt->get_result()->fetch_assoc();
        $drStmt->close();
        $drRanToday = (int) $drTodayResult['cnt'] > 0;

        // ── Seniority Promotion check ──
        $spSql = "SELECT COUNT(*) as cnt FROM agent_logs 
                  WHERE agent_name = 'seniority_promotion' AND DATE(created_at) = ?";
        $spStmt = $conn->prepare($spSql);
        $spStmt->bind_param('s', $today);
        $spStmt->execute();
        $spResult = $spStmt->get_result()->fetch_assoc();
        $spStmt->close();

        // 20 yılını dolduran terfi adayı var mı?
        $eligibleSql = "SELECT COUNT(*) as cnt FROM members 
                        WHERE membershipStatus = 'aktif' 
                        AND registrationDate IS NOT NULL 
                        AND registrationDate <= DATE_SUB(CURDATE(), INTERVAL 20 YEAR)
                        AND (membershipType = 'Asil Üye' OR membershipType = 'Fahri Üye')";
        $eligibleResult = $conn->query($eligibleSql);
        $eligibleCount = $eligibleResult ? (int) $eligibleResult->fetch_assoc()['cnt'] : 0;

        // Yearly Charge Check 
        $currentYearForCharge = date('Y');
        $ycSql = "SELECT COUNT(*) as cnt FROM agent_logs WHERE agent_name = 'yearly_charge' AND status = 'success' AND details LIKE CONCAT('%(', ?, ')%')";
        $ycStmt = $conn->prepare($ycSql);
        $ycStmt->bind_param('i', $currentYearForCharge);
        $ycStmt->execute();
        $ycResult = $ycStmt->get_result()->fetch_assoc();
        $ycStmt->close();
        $hasRunYearlyCharge = (int) $ycResult['cnt'] > 0;

        $status = [
            'yearly_charge' => [
                'ran_today' => $hasRunYearlyCharge,
                'should_run' => !$hasRunYearlyCharge
            ],
            'birthday' => [
                'ran_today' => (int) $bdResult['cnt'] > 0,
                'should_run' => (int) $bdResult['cnt'] === 0
            ],
            'monthly_report' => [
                'ran_today' => (int) $mrResult['cnt'] > 0,
                'should_run' => $isFirstOfMonth && (int) $mrResult['cnt'] === 0
            ],
            'debt_reminder' => [
                'ran_today' => $drRanToday,
                'should_run' => !$drRanToday && ($tierCounts[1] > 0 || $tierCounts[2] > 0 || $tierCounts[3] > 0),
                'escalation' => [
                    'tier_1' => ['eligible' => $tierCounts[1], 'label' => 'Nazik Hatırlatma'],
                    'tier_2' => ['eligible' => $tierCounts[2], 'label' => 'Resmi Uyarı (30+ gün)'],
                    'tier_3' => ['eligible' => $tierCounts[3], 'label' => 'Son Uyarı (60+ gün)']
                ]
            ],
            'seniority_promotion' => [
                'ran_today' => (int) $spResult['cnt'] > 0,
                'should_run' => (int) $spResult['cnt'] === 0 && $eligibleCount > 0,
                'eligible_count' => $eligibleCount
            ]
        ];

        sendResponse(true, 'Cron durumu kontrol edildi', $status);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Cron kontrol hatası: ' . $e->getMessage());
    }
}

// ─── Monthly Management Report Agent ───────────────────

function runMonthlyReportAgent($data)
{
    global $conn;
    try {
        $now = new DateTime();
        $monthName = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        $currentMonth = $monthName[(int) $now->format('m') - 1];
        $currentYear = $now->format('Y');
        $monthStart = $now->format('Y-m-01');

        // Previous month for the report
        $prevMonth = (clone $now)->modify('first day of last month');
        $prevMonthName = $monthName[(int) $prevMonth->format('m') - 1];
        $prevMonthStart = $prevMonth->format('Y-m-01');
        $prevMonthEnd = $prevMonth->format('Y-m-t');
        $reportPeriod = $prevMonthName . ' ' . $prevMonth->format('Y');

        // ── 1. Üye İstatistikleri ──
        $memberSql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN membershipStatus = 'aktif' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN membershipStatus != 'aktif' THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN membershipType = 'Asil Üye' THEN 1 ELSE 0 END) as asil,
            SUM(CASE WHEN membershipType = 'Öğrenci Üye' THEN 1 ELSE 0 END) as ogrenci,
            SUM(CASE WHEN membershipType LIKE '%Fahri%' THEN 1 ELSE 0 END) as fahri,
            SUM(CASE WHEN membershipType LIKE '%Onursal%' THEN 1 ELSE 0 END) as onursal
            FROM members";
        $memberStats = $conn->query($memberSql)->fetch_assoc();

        // ── 2. Geçen Ay Tahsilat ──
        $collectionSql = "SELECT 
            COUNT(*) as payment_count,
            COALESCE(SUM(amount), 0) as total_collected
            FROM payments 
            WHERE status = 'tamamlandı' 
            AND date BETWEEN '$prevMonthStart' AND '$prevMonthEnd'";
        $collectionStats = $conn->query($collectionSql)->fetch_assoc();

        // ── 3. Toplam Borç Durumu ──
        $debtSql = "SELECT 
            COUNT(DISTINCT memberId) as debtor_count,
            COALESCE(SUM(amount), 0) as total_debt
            FROM payments 
            WHERE type = 'aidat' AND status = 'bekliyor'";
        $debtStats = $conn->query($debtSql)->fetch_assoc();

        // ── 4. Geçen Ay Agent Aktivitesi ──
        $agentSql = "SELECT agent_name, COUNT(*) as cnt, 
            SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as success_cnt
            FROM agent_logs 
            WHERE created_at BETWEEN '$prevMonthStart' AND '$prevMonthEnd 23:59:59'
            GROUP BY agent_name";
        $agentResult = $conn->query($agentSql);
        $agentActivity = [];
        $totalActions = 0;
        if ($agentResult) {
            while ($row = $agentResult->fetch_assoc()) {
                $agentActivity[] = $row;
                $totalActions += (int) $row['cnt'];
            }
        }

        // ── 5. Yeni Üyeler (Geçen Ay) ──
        $newMemberSql = "SELECT COUNT(*) as cnt FROM members 
            WHERE createdAt BETWEEN '$prevMonthStart' AND '$prevMonthEnd 23:59:59'";
        $newMemberResult = $conn->query($newMemberSql);
        $newMembers = $newMemberResult ? $newMemberResult->fetch_assoc()['cnt'] : 0;

        // ── Agent Aktivite Tablosu HTML ──
        $agentNames = [
            'payment_confirm' => '💳 Ödeme Onay',
            'welcome' => '👋 Hoş Geldin',
            'debt_reminder' => '🔔 Borç Hatırlatma',
            'yearly_charge' => '📅 Yıllık Borç',
            'birthday' => '🎂 Doğum Günü',
            'accounting' => '🧮 Muhasebe',
            'monthly_report' => '📊 Aylık Rapor',
            'event_announce' => '📢 Etkinlik'
        ];

        $agentRows = '';
        foreach ($agentActivity as $a) {
            $label = $agentNames[$a['agent_name']] ?? $a['agent_name'];
            $agentRows .= '<tr>
                <td style="padding:8px 12px;border-bottom:1px solid #eee;">' . $label . '</td>
                <td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:center;">' . $a['cnt'] . '</td>
                <td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:center;color:#27ae60;">' . $a['success_cnt'] . '</td>
            </tr>';
        }
        if (empty($agentRows)) {
            $agentRows = '<tr><td colspan="3" style="padding:12px;text-align:center;color:#95a5a6;">Bu dönemde agent aktivitesi yok</td></tr>';
        }

        // ── HTML Email Template ──
        $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f0f0f0;font-family:Arial,Helvetica,sans-serif;">
        <div style="max-width:650px;margin:20px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
            
            <!-- Header -->
            <div style="background:linear-gradient(135deg,#2c3e50,#3498db);padding:35px 30px;text-align:center;">
                <div style="font-size:48px;margin-bottom:8px;">📊</div>
                <h1 style="color:white;margin:0;font-size:24px;">Aylık Yönetim Raporu</h1>
                <div style="background:rgba(255,255,255,0.2);display:inline-block;padding:8px 24px;border-radius:20px;margin-top:12px;">
                    <span style="color:white;font-size:16px;font-weight:bold;">' . $reportPeriod . '</span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div style="padding:25px 30px;">
                <h2 style="color:#2c3e50;font-size:18px;margin:0 0 15px;border-bottom:2px solid #3498db;padding-bottom:8px;">👥 Üye Durumu</h2>
                <table style="width:100%;border-collapse:collapse;margin-bottom:25px;">
                    <tr>
                        <td style="padding:10px;background:#eaf2f8;border-radius:8px 0 0 0;">
                            <div style="font-size:24px;font-weight:bold;color:#2c3e50;">' . $memberStats['total'] . '</div>
                            <div style="font-size:12px;color:#7f8c8d;">Toplam Üye</div>
                        </td>
                        <td style="padding:10px;background:#eaf2f8;">
                            <div style="font-size:24px;font-weight:bold;color:#27ae60;">' . $memberStats['active'] . '</div>
                            <div style="font-size:12px;color:#7f8c8d;">Aktif</div>
                        </td>
                        <td style="padding:10px;background:#eaf2f8;">
                            <div style="font-size:24px;font-weight:bold;color:#3498db;">' . $newMembers . '</div>
                            <div style="font-size:12px;color:#7f8c8d;">Yeni Üye</div>
                        </td>
                        <td style="padding:10px;background:#eaf2f8;border-radius:0 8px 0 0;">
                            <div style="font-size:24px;font-weight:bold;color:#e74c3c;">' . $memberStats['inactive'] . '</div>
                            <div style="font-size:12px;color:#7f8c8d;">Pasif</div>
                        </td>
                    </tr>
                </table>

                <h2 style="color:#2c3e50;font-size:18px;margin:0 0 15px;border-bottom:2px solid #27ae60;padding-bottom:8px;">💰 Tahsilat Özeti</h2>
                <table style="width:100%;border-collapse:collapse;margin-bottom:25px;">
                    <tr>
                        <td style="padding:10px;background:#eafaf1;border-radius:8px 0 0 8px;">
                            <div style="font-size:24px;font-weight:bold;color:#27ae60;">₺' . number_format((float) $collectionStats['total_collected'], 0, ',', '.') . '</div>
                            <div style="font-size:12px;color:#7f8c8d;">Geçen Ay Tahsilat</div>
                        </td>
                        <td style="padding:10px;background:#eafaf1;">
                            <div style="font-size:24px;font-weight:bold;color:#2c3e50;">' . $collectionStats['payment_count'] . '</div>
                            <div style="font-size:12px;color:#7f8c8d;">Ödeme Sayısı</div>
                        </td>
                        <td style="padding:10px;background:#fdedec;border-radius:0 8px 8px 0;">
                            <div style="font-size:24px;font-weight:bold;color:#e74c3c;">₺' . number_format((float) $debtStats['total_debt'], 0, ',', '.') . '</div>
                            <div style="font-size:12px;color:#7f8c8d;">' . $debtStats['debtor_count'] . ' Borçlu Üye</div>
                        </td>
                    </tr>
                </table>

                <h2 style="color:#2c3e50;font-size:18px;margin:0 0 15px;border-bottom:2px solid #9b59b6;padding-bottom:8px;">🤖 Agent Aktivitesi (' . $totalActions . ' işlem)</h2>
                <table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:14px;">
                    <tr style="background:#f8f9fa;">
                        <th style="padding:10px 12px;text-align:left;color:#7f8c8d;font-weight:600;">Agent</th>
                        <th style="padding:10px 12px;text-align:center;color:#7f8c8d;font-weight:600;">Toplam</th>
                        <th style="padding:10px 12px;text-align:center;color:#7f8c8d;font-weight:600;">Başarılı</th>
                    </tr>
                    ' . $agentRows . '
                </table>
            </div>

            <!-- Footer -->
            <div style="background:#2c3e50;padding:20px;text-align:center;">
                <p style="color:#ecf0f1;margin:0;font-size:13px;">Türkiye Petrol Jeologları Derneği</p>
                <p style="color:#95a5a6;margin:5px 0 0;font-size:12px;">TPJD Üyelik Sistemi — Otomatik Aylık Rapor | <a href="https://www.tpjd.org.tr" style="color:#3498db;text-decoration:none;">www.tpjd.org.tr</a></p>
            </div>
        </div>
        </body></html>';

        // ── Alıcılara Gönder ──
        $recipients = getEmailSettingRecipients('monthly_report');
        $subject = "=?UTF-8?B?" . base64_encode("📊 TPJD Aylık Yönetim Raporu — $reportPeriod") . "?=";
        $headers = "From: =?UTF-8?B?" . base64_encode("TPJD Derneği") . "?= <uye@tpjd.org.tr>\r\n";
        $headers .= "Reply-To: uye@tpjd.org.tr\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8";

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $email) {
            $mailSent = sendEmailInternalHelper(trim($email), '', $subject, $body);
            if ($mailSent)
                $sent++;
            else
                $failed++;
        }

        // ── Log Yaz ──
        $logId = uniqid('alog_');
        $detail = "📊 $reportPeriod raporu: " . count($recipients) . " alıcıya gönderildi (Başarılı: $sent, Hata: $failed)";
        $status = $sent > 0 ? 'success' : 'error';
        $logSql = "INSERT INTO agent_logs (id, agent_name, action, channel, recipient_name, details, status) 
                   VALUES (?, 'monthly_report', 'report', 'email', 'Yönetim Kurulu', ?, ?)";
        $logStmt = $conn->prepare($logSql);
        $logStmt->bind_param('sss', $logId, $detail, $status);
        $logStmt->execute();
        $logStmt->close();

        sendResponse(true, "Aylık rapor gönderildi — $reportPeriod", [
            'period' => $reportPeriod,
            'recipients' => count($recipients),
            'sent' => $sent,
            'failed' => $failed,
            'member_total' => (int) $memberStats['total'],
            'collection' => (float) $collectionStats['total_collected'],
            'debt' => (float) $debtStats['total_debt']
        ]);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Aylık rapor hatası: ' . $e->getMessage());
    }
}

// ─── Seniority Promotion Agent ──────────────────────────

function checkSeniorityPromotions()
{
    global $conn;
    try {
        // 20 yılını dolduran ve hâlâ Asil Üye veya Fahri Üye olan aktif üyeler
        $sql = "SELECT id, memberNo, firstName, lastName, email, phone, 
                       membershipType, registrationDate,
                       TIMESTAMPDIFF(YEAR, registrationDate, CURDATE()) as years_member
                FROM members 
                WHERE membershipStatus = 'aktif' 
                AND registrationDate IS NOT NULL 
                AND registrationDate <= DATE_SUB(CURDATE(), INTERVAL 20 YEAR)
                AND (membershipType = 'Asil Üye' OR membershipType = 'Fahri Üye')
                ORDER BY registrationDate ASC";

        $result = $conn->query($sql);
        $eligible = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $newType = $row['membershipType'] === 'Asil Üye' ? 'Asil Onursal' : 'Fahri Onursal';
                $eligible[] = [
                    'id' => $row['id'],
                    'memberNo' => $row['memberNo'],
                    'fullName' => $row['firstName'] . ' ' . $row['lastName'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'currentType' => $row['membershipType'],
                    'newType' => $newType,
                    'registrationDate' => $row['registrationDate'],
                    'yearsMember' => (int) $row['years_member']
                ];
            }
        }

        sendResponse(true, count($eligible) . ' üye terfi için uygun', ['eligible' => $eligible]);
    } catch (Exception $e) {
        logError($e->getMessage());
        sendResponse(false, 'Kıdem kontrol hatası: ' . $e->getMessage());
    }
}

function runSeniorityPromotions($data)
{
    global $conn;
    try {
        $promotions = $data['promotions'] ?? [];
        if (empty($promotions)) {
            sendResponse(false, 'Terfi edilecek üye listesi boş');
            return;
        }

        $conn->begin_transaction();
        $results = [];
        $updateStmt = $conn->prepare("UPDATE members SET membershipType = ?, aidatAktif = 0, updatedAt = NOW() WHERE id = ?");

        foreach ($promotions as $promo) {
            $memberId = $promo['id'];
            $newType = $promo['newType'];
            $oldType = $promo['currentType'];
            $fullName = $promo['fullName'];

            // Üyelik tipini güncelle + aidatAktif = 0 (muaf)
            $updateStmt->bind_param('ss', $newType, $memberId);
            $updateStmt->execute();

            // Agent log kaydet
            $logId = uniqid('alog_');
            $logStmt = $conn->prepare(
                "INSERT INTO agent_logs (id, agent_name, action_type, recipient_id, recipient_name, details, status, created_at)
                 VALUES (?, 'seniority_promotion', 'promotion', ?, ?, ?, 'success', NOW())"
            );
            $details = json_encode([
                'old_type' => $oldType,
                'new_type' => $newType,
                'years_member' => $promo['yearsMember'] ?? 20
            ], JSON_UNESCAPED_UNICODE);
            $logStmt->bind_param('ssss', $logId, $memberId, $fullName, $details);
            $logStmt->execute();
            $logStmt->close();

            $results[] = [
                'id' => $memberId,
                'fullName' => $fullName,
                'oldType' => $oldType,
                'newType' => $newType
            ];
        }

        $updateStmt->close();
        $conn->commit();

        sendResponse(true, count($results) . ' üye başarıyla terfi edildi', ['promoted' => $results]);
    } catch (Exception $e) {
        $conn->rollback();
        logError($e->getMessage());
        sendResponse(false, 'Terfi işlemi başarısız: ' . $e->getMessage());
    }
}
?>