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
        $stmt = Database::conn()->prepare(
            "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $title, $message, $type, $link]);

        if ($sendEmail && self::emailEnabled()) {
            self::sendEmail($userId, $title, $message);
        }
    }

    private static function emailEnabled(): bool {
        $stmt = Database::conn()->query("SELECT setting_value FROM system_settings WHERE setting_key='email_notifications'");
        return (bool)($stmt->fetchColumn() ?? 0);
    }

    private static function sendEmail(int $userId, string $subject, string $body): void {
        try {
            $stmt = Database::conn()->prepare("SELECT email, full_name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!$user) return;

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = getenv('MAIL_HOST') ?: 'localhost';
            $mail->SMTPAuth = true;
            $mail->Username = getenv('MAIL_USER') ?: '';
            $mail->Password = getenv('MAIL_PASS') ?: '';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)(getenv('MAIL_PORT') ?: 587);
            $mail->setFrom(getenv('MAIL_FROM') ?: 'noreply@estefania.sk', 'SK Scholarship');
            $mail->addAddress($user['email'], $user['full_name']);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = nl2br(htmlspecialchars($body));
            $mail->send();
        } catch (Throwable $e) {
            if (APP_DEBUG) error_log('Mail error: ' . $e->getMessage());
        }
    }

    public static function unreadCount(int $userId): int {
        $stmt = Database::conn()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}