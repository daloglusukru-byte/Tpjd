import sys
import re

file_path = r"C:\Users\monster\Desktop\tpjd\uye\uye\api\agents.php"
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

helper_func = """
function sendEmailInternalHelper($toEmail, $toName, $subject, $body) {
    try {
        require_once __DIR__ . '/phpmailer/Exception.php';
        require_once __DIR__ . '/phpmailer/PHPMailer.php';
        require_once __DIR__ . '/phpmailer/SMTP.php';

        $mail = new \\PHPMailer\\PHPMailer\\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'mail.tpjd.org.tr';
        $mail->SMTPAuth = true;
        $mail->Username = 'uye@tpjd.org.tr';
        $mail->Password = '$TKJ[0sPtEHhoDEA';
        $mail->SMTPSecure = \\PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_SMTPS;
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

        if (preg_match('/^\\=\\?UTF\\-8\\?B\\?(.*?)\\?\\=$/i', trim($subject), $matches)) {
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
"""

if "sendEmailInternalHelper" not in content:
    content = content.replace("$method = $_SERVER['REQUEST_METHOD'];", helper_func + "\n$method = $_SERVER['REQUEST_METHOD'];")

content = content.replace(
    "$sent = @mail($member['email'], $subject, $body, $headers);",
    "$sent = sendEmailInternalHelper($member['email'], $recipientName, $subject, $body);"
)

content = content.replace(
    "$mailSent = @mail($member['email'], $subject, $htmlMessage, implode(\"\\r\\n\", $headers));",
    "$mailSent = sendEmailInternalHelper($member['email'], $recipientName, $subject, $htmlMessage);"
)

content = content.replace(
    "$mailSent = @mail($member['email'], $subject, $body, $headers);",
    "$mailSent = sendEmailInternalHelper($member['email'], $recipientName, $subject, $body);"
)

content = content.replace(
    "$mailSent = @mail(trim($email), $subject, $body, $headers);",
    "$mailSent = sendEmailInternalHelper(trim($email), '', $subject, $body);"
)

# New yearly charge logic replacement
old_yearly_charge = re.search(r'function runYearlyChargeAgent\(\$data\)\s*\{.*?\n\}', content, re.DOTALL)
new_yearly_charge = """function runYearlyChargeAgent($data)
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
            if (is_array($decoded)) $aidatSettings = $decoded;
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
            if (empty($aidatSettings) && $membershipType === 'Asil Üye') $duesAmount = 1500;
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

    if (isset($data['internal']) && $data['internal']) return ['success' => $success, 'message' => $message];
    sendResponse($success, $message);
}"""

if old_yearly_charge:
    content = content.replace(old_yearly_charge.group(0), new_yearly_charge)

cron_status_replacement = """        // Yearly Charge Check 
        $currentYearForCharge = date('Y');
        $ycSql = "SELECT COUNT(*) as cnt FROM agent_logs WHERE agent_name = 'yearly_charge' AND status = 'success' AND details LIKE CONCAT('%(', ?, ')%')";
        $ycStmt = $conn->prepare($ycSql);
        $ycStmt->bind_param('i', $currentYearForCharge);
        $ycStmt->execute();
        $ycResult = $ycStmt->get_result()->fetch_assoc();
        $ycStmt->close();
        $hasRunYearlyCharge = (int)$ycResult['cnt'] > 0;

        $status = [
            'yearly_charge' => [
                'ran_today' => $hasRunYearlyCharge,
                'should_run' => !$hasRunYearlyCharge
            ],
            'birthday' => ["""

content = content.replace("        $status = [\n            'birthday' => [", cron_status_replacement)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("api/agents.php safely updated successfully without regex destruction!")
