import sys

file_path = r"C:\Users\monster\Desktop\tpjd\uye\uye\api\agents.php"
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

bad_require = """    try {
        require_once __DIR__ . '/phpmailer/Exception.php';
        require_once __DIR__ . '/phpmailer/PHPMailer.php';
        require_once __DIR__ . '/phpmailer/SMTP.php';"""

good_require = """require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

function sendEmailInternalHelper($toEmail, $toName, $subject, $body) {
    try {"""

content = content.replace(bad_require, "    try {")
if "require_once __DIR__ . '/phpmailer/Exception.php';" not in content:
    content = content.replace("function sendEmailInternalHelper($toEmail, $toName, $subject, $body) {", good_require)

# Also let's fix the missing string replace for cron_check
old_cron = """        $status = [
            'birthday' => ["""
new_cron = """        $currentYearForCharge = date('Y');
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

# Fix line endings issue for replace by replacing disregarding whitespace
import re
content = re.sub(r'\$status\s*=\s*\[\s*\'birthday\'\s*=>\s*\[', new_cron, content)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Fixed require_once inside function and cron_check!")
