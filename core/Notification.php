<?php
declare(strict_types=1);

final class Notification
{
    public static function send(
        int $userId,
        string $title,
        string $message,
        string $type = 'info',
        ?string $link = null,
        bool $sendEmail = false
    ): void {
        $pdo = Database::conn();
        $stmt = $pdo->prepare(
            "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $title, $message, $type, $link]);
        $notificationId = (int)$pdo->lastInsertId();

        if ($sendEmail && self::emailEnabled()) {
            if (self::sendEmail($userId, $title, $message)) {
                $pdo->prepare("UPDATE notifications SET email_sent = 1 WHERE id = ?")->execute([$notificationId]);
            }
        }
    }

    private static function emailEnabled(): bool {
        $stmt = Database::conn()->query("SELECT setting_value FROM system_settings WHERE setting_key='email_notifications'");
        return (bool)($stmt->fetchColumn() ?? 0);
    }

    private static function sendEmail(int $userId, string $subject, string $body): bool {
        $stmt = Database::conn()->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) return false;

        return self::sendMail($user['email'], $user['full_name'], $subject, nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')), $body);
    }

    /**
     * Send one e-mail through PHPMailer/SMTP. Configuration (environment / .env):
     *   MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS, MAIL_FROM, MAIL_FROM_NAME,
     *   MAIL_ENCRYPTION = tls (STARTTLS, default) | ssl (SMTPS) | none (local test servers only)
     * Failures are logged (message only, never the body/credentials) and reported via the return value.
     */
    public static function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = getenv('MAIL_HOST') ?: 'localhost';
            $user = (string)(getenv('MAIL_USER') ?: '');
            $mail->SMTPAuth = $user !== '';
            $mail->Username = $user;
            $mail->Password = (string)(getenv('MAIL_PASS') ?: '');

            $enc = strtolower((string)(getenv('MAIL_ENCRYPTION') ?: 'tls'));
            if ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'none') {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            } else {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port = (int)(getenv('MAIL_PORT') ?: ($enc === 'ssl' ? 465 : 587));
            $mail->Timeout = 10;

            $mail->setFrom(getenv('MAIL_FROM') ?: 'noreply@estefania.sk', getenv('MAIL_FROM_NAME') ?: 'SK Scholarship');
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);
            return $mail->send();
        } catch (Throwable $e) {
            error_log('Mail error: ' . $e->getMessage());
            return false;
        }
    }

    public static function unreadCount(int $userId): int {
        $stmt = Database::conn()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}
