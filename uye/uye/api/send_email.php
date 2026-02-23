<?php
require_once 'auth.php';
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    sendResponse(false, 'Invalid request method');
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['recipients']) || !isset($data['subject']) || !isset($data['message'])) {
    sendResponse(false, 'Missing required fields');
    exit;
}

$recipients = $data['recipients'];
$subject = $data['subject'];
$message = $data['message'];

global $conn;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once 'phpmailer/Exception.php';
require_once 'phpmailer/PHPMailer.php';
require_once 'phpmailer/SMTP.php';

try {
    // SMTP settings
    $smtpHost = 'mail.tpjd.org.tr';
    $smtpPort = 465;
    $smtpUsername = 'uye@tpjd.org.tr';
    $smtpPassword = '$TKJ[0sPtEHhoDEA';
    $smtpSecure = PHPMailer::ENCRYPTION_SMTPS;

    // HTML message template
    $htmlMessage = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($subject) . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { background: #34495e; color: white; padding: 15px; text-align: center; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Türkiye Petrol Jeologları Derneği</h2>
            </div>
            <div class="content">
                <h3>' . htmlspecialchars($subject) . '</h3>
                <div>' . nl2br(htmlspecialchars($message)) . '</div>
            </div>
            <div class="footer">
                <p>Bu e-posta TPJD Üyelik Sistemi üzerinden gönderilmiştir.</p>
                <p>Türkiye Petrol Jeologları Derneği | www.tpjd.org.tr</p>
            </div>
        </div>
    </body>
    </html>';

    $successCount = 0;
    $failureCount = 0;
    $errors = [];

    // Initialize PHPMailer outside loop if we want to reuse connection, but sending one by one is safer to avoid BCC exposure if not coded properly
    // Let's create one mailer instance and reuse it with keepalive
    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->SMTPSecure = $smtpSecure;
    $mail->Port = $smtpPort;
    $mail->CharSet = 'UTF-8';
        $mail->SMTPKeepAlive = true; // Keep connection open for multiple emails

    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    // From
    $mail->setFrom($smtpUsername, 'TPJD Derneği');
    $mail->addReplyTo($smtpUsername, 'TPJD Derneği');

    $mail->isHTML(true);

    // Send to each recipient
    foreach ($recipients as $recipient) {
        $to = $recipient['email'];
        $personalizedSubject = $subject;

        try {
            $mail->clearAddress();
            $mail->clearAllRecipients();
            $mail->addAddress($to, $recipient['name'] ?? '');

            $mail->Subject = $personalizedSubject;
            $mail->Body = $htmlMessage;

            if ($mail->send()) {
                $successCount++;
            } else {
                $failureCount++;
                $errors[] = "Failed to send to {$to}: " . $mail->ErrorInfo;
            }
        } catch (Exception $e) {
            $failureCount++;
            $errors[] = "Error sending to {$to}: " . $e->getMessage();
        }
    }

    // Close connection
    $mail->smtpClose();

    // Log email sending
    $logData = [
        'recipients' => $recipients,
        'subject' => $subject,
        'message' => $message,
        'success_count' => $successCount,
        'failure_count' => $failureCount,
        'errors' => $errors,
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Save to notifications table
    $notification = [
        'id' => uniqid('notif_'),
        'title' => "Toplu E-posta Gönderimi",
        'message' => "{$successCount} kişiye e-posta gönderildi" . ($failureCount > 0 ? ", {$failureCount} başarısız" : ""),
        'priority' => 'normal',
        'recipients' => json_encode(array_column($recipients, 'email')),
        'sentAt' => date('Y-m-d H:i:s'),
        'createdAt' => date('Y-m-d H:i:s')
    ];

    $sql = "INSERT INTO notifications (id, title, message, priority, recipients, sentAt, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'sssssss',
        $notification['id'],
        $notification['title'],
        $notification['message'],
        $notification['priority'],
        $notification['recipients'],
        $notification['sentAt'],
        $notification['createdAt']
    );
    $stmt->execute();

    if ($successCount > 0) {
        sendResponse(true, "E-posta başarıyla gönderildi. Başarılı: {$successCount}, Başarısız: {$failureCount}", [
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'errors' => $errors
        ]);
    } else {
        $errorDetails = implode(" | ", $errors);
        sendResponse(false, 'E-posta gönderilemedi: ' . $errorDetails, ['errors' => $errors]);
    }

} catch (Exception $e) {
    logError('Email sending error: ' . $e->getMessage());
    sendResponse(false, 'E-posta gönderilirken bir hata oluştu: ' . $e->getMessage());
}
?>