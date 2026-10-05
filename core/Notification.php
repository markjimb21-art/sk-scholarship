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

    /**
     * In-app notification (and e-mail when $sendEmail and e-mail is on in Settings) to every ACTIVE user with the
     * admin role. Returns how many admins were notified.
     */
    public static function sendToAdmins(string $title, string $message, string $type = 'info', ?string $link = null, bool $sendEmail = false): int
    {
        $ids = Database::conn()->query(
            "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.role_name = 'admin' AND u.is_active = 1"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) self::send((int)$id, $title, $message, $type, $link, $sendEmail);
        return count($ids);
    }

    /**
     * Something new is waiting in an admin queue (submission, resubmission, no-show, reschedule request): in-app to every
     * admin; also e-mailed when Settings → "E-mail admins about new submissions" is on. Never breaks the caller's action.
     */
    public static function adminEvent(string $title, string $message, string $route): void
    {
        try {
            self::sendToAdmins($title, $message, 'info', url($route), setting('admin_event_emails', '0') === '1');
        } catch (Throwable $e) {
            error_log('Admin notification failed: ' . $e->getMessage());
        }
    }

    private static function emailEnabled(): bool {
        $stmt = Database::conn()->query("SELECT setting_value FROM system_settings WHERE setting_key='email_notifications'");
        return (bool)($stmt->fetchColumn() ?? 0);
    }

    /**
     * Safe, secret-free snapshot of the current mail configuration for the admin Settings screen
     * and for diagnosing delivery problems. Never includes MAIL_PASS or any other credential value —
     * only whether each setting is present.
     */
    public static function mailConfigStatus(): array {
        $host = trim((string)(getenv('MAIL_HOST') ?: ''));
        $port = trim((string)(getenv('MAIL_PORT') ?: ''));
        $user = trim((string)(getenv('MAIL_USER') ?: ''));
        $pass = (string)(getenv('MAIL_PASS') ?: '');
        $from = trim((string)(getenv('MAIL_FROM') ?: ''));
        $enc  = strtolower(trim((string)(getenv('MAIL_ENCRYPTION') ?: 'tls')));

        return [
            'host'                  => $host !== '' ? $host : null,
            'port'                  => $port !== '' ? (int)$port : ($enc === 'ssl' ? 465 : 587),
            'encryption'            => strtoupper($enc !== '' ? $enc : 'tls'),
            'user_configured'       => $user !== '',
            'pass_configured'       => $pass !== '',
            'from_is_placeholder'   => self::isPlaceholderFrom($from),
            'effective_from'        => self::resolveFrom($from, $user),
            'email_notifications_enabled' => self::emailEnabled(),
            'phpmailer_loaded'      => class_exists(\PHPMailer\PHPMailer\PHPMailer::class),
            'openssl_enabled'       => extension_loaded('openssl'),
            'mbstring_enabled'      => extension_loaded('mbstring'),
        ];
    }

    /** A MAIL_FROM value is unusable if it's empty or still the install placeholder domain. */
    private static function isPlaceholderFrom(string $from): bool {
        if ($from === '') return true;
        $domain = strtolower(substr($from, strpos($from, '@') + 1));
        return $domain === '' || $domain === 'yourdomain.test' || $domain === 'example.com';
    }

    /**
     * Resolve the sender address actually used for outgoing mail. Most SMTP providers (Gmail
     * included) require the From address to match the authenticated account or a verified alias —
     * a placeholder domain like "noreply@yourdomain.test" will be rejected or silently rewritten.
     * So: use MAIL_FROM if it looks real, otherwise fall back to the authenticated MAIL_USER.
     */
    private static function resolveFrom(string $from, string $authenticatedUser): string {
        if (!self::isPlaceholderFrom($from)) return $from;
        return $authenticatedUser !== '' ? $authenticatedUser : 'noreply@estefania.sk';
    }

    /** Admin-only "Send Test Email" (see AdminController::settingsTestEmail). */
    public static function sendTestEmail(string $toEmail): array {
        $subject = APP_NAME_SHORT . ' — Test Email';
        $plain = 'This is a test email from ' . APP_NAME . '.';
        $html = self::brand($subject, 'Administrator', '<p>' . $plain . '</p>');
        $ok = self::sendMail($toEmail, 'Administrator', $subject, $html, $plain);
        return $ok
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Unable to send test email. Check ' . 'storage/logs/app.log for the SMTP error.'];
    }

    private static function sendEmail(int $userId, string $subject, string $body): bool {
        $stmt = Database::conn()->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) return false;

        $html = self::brand($subject, $user['full_name'], nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')));
        return self::sendMail($user['email'], $user['full_name'], $subject, $html, $body);
    }

    /**
     * Shared SK EduKabataan / Barangay Estefania e-mail shell: subject as heading, greeting by name,
     * body HTML, an optional call-to-action button, and a footer. Used for every notification e-mail
     * so all outgoing mail looks consistent. The header is text and colors only (no remote image), so it
     * still looks right in mail clients that block images. Colors are the site's tokens (style.css).
     */
    public static function brand(string $subject, string $toName, string $bodyHtml, ?string $actionUrl = null, ?string $actionLabel = null): string {
        $name = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $button = '';
        if ($actionUrl !== null && $actionLabel !== null) {
            $button = '<p style="margin:28px 0;"><a href="' . htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') . '" '
                . 'style="background:#0B4DB3;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:8px;display:inline-block;font-weight:bold;font-size:17px;">'
                . htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') . '</a></p>';
        }
        $appName = htmlspecialchars(APP_NAME_SHORT, ENT_QUOTES, 'UTF-8');
        $appTagline = 'Educational Assistance for College Students';
        $subtitle = htmlspecialchars(APP_SUBTITLE, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!doctype html><html><body style="margin:0;padding:0;background:#F5F7FB;font-family:'Segoe UI',Arial,sans-serif;color:#1A1F2B;">
<div style="max-width:600px;margin:0 auto;padding:24px 12px;">
  <div style="background:#082B66;color:#ffffff;padding:22px 26px;border-radius:10px 10px 0 0;border-bottom:5px solid #F6C90E;">
    <div style="font-size:24px;font-weight:bold;line-height:1.2;color:#ffffff;">{$appName}</div>
    <div style="font-size:17px;font-weight:bold;margin-top:2px;color:#ffffff;">{$appTagline}</div>
    <div style="font-size:15px;margin-top:6px;color:#D6E2F5;">{$subtitle}</div>
  </div>
  <div style="background:#ffffff;padding:26px;border:1px solid #D3D9E3;border-top:none;">
    <h2 style="margin-top:0;color:#083A87;font-size:21px;line-height:1.3;">{$title}</h2>
    <p style="font-size:17px;line-height:1.6;">Hello {$name},</p>
    <div style="color:#1A1F2B;font-size:17px;line-height:1.6;">{$bodyHtml}</div>
    {$button}
  </div>
  <div style="background:#F5F7FB;color:#454E5F;font-size:15px;line-height:1.5;padding:16px 24px;text-align:center;">
    This is an automated message from {$appName}.<br>
    Please do not reply directly to this e-mail. For assistance, contact your Barangay Estefania SK office.
  </div>
</div>
</body></html>
HTML;
    }

    /**
     * Send one e-mail through PHPMailer/SMTP. Configuration (environment / .env):
     *   MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS, MAIL_FROM, MAIL_FROM_NAME,
     *   MAIL_ENCRYPTION = tls (STARTTLS, default) | ssl (SMTPS) | none (local test servers only)
     * Failures are logged (message only, never the body/credentials) and reported via the return value.
     */
    public static function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
        $host = (string)(getenv('MAIL_HOST') ?: 'localhost');
        $user = trim((string)(getenv('MAIL_USER') ?: ''));
        $rawFrom = trim((string)(getenv('MAIL_FROM') ?: ''));
        $from = self::resolveFrom($rawFrom, $user);
        $enc = strtolower((string)(getenv('MAIL_ENCRYPTION') ?: 'tls'));
        $port = (int)(getenv('MAIL_PORT') ?: ($enc === 'ssl' ? 465 : 587));

        // Dev-only SMTP transcript, buffered in memory and only ever written to the log (never shown
        // to a user) — and only when the send ultimately fails. AUTH exchange lines are redacted so
        // no credential material (even base64-encoded) can end up in storage/logs/app.log.
        $trace = [];

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = $user !== '';
            $mail->Username = $user;
            $mail->Password = (string)(getenv('MAIL_PASS') ?: '');

            if ($enc === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'none') {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port = $port;
            $mail->Timeout = 10;

            if (defined('APP_DEBUG') && APP_DEBUG) {
                $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function (string $str) use (&$trace): void {
                    $trace[] = self::scrubSmtpDebugLine($str);
                };
            }

            $mail->setFrom($from, getenv('MAIL_FROM_NAME') ?: APP_NAME_SHORT);
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);

            $sent = $mail->send();
            if (!$sent) {
                self::logMailFailure($host, $port, $enc, $user, $from, $toEmail, $mail->ErrorInfo, $trace);
            }
            return $sent;
        } catch (Throwable $e) {
            self::logMailFailure($host, $port, $enc, $user, $from, $toEmail, $e->getMessage(), $trace);
            return false;
        }
    }

    /**
     * Redacts PHPMailer SMTPDebug lines that carry the AUTH exchange (base64-encoded username/
     * password) or the raw server greeting/banner, keeping only lines useful for diagnosing a
     * failure (connection, STARTTLS, command replies).
     */
    private static function scrubSmtpDebugLine(string $line): string {
        $line = trim($line);
        if (preg_match('/AUTH|^334|^235|^535/i', $line)) {
            return '[SMTP AUTH EXCHANGE — REDACTED]';
        }
        return mb_substr($line, 0, 200);
    }

    /** Mask a recipient address for logs: "j***@example.com" — enough to identify, not to expose. */
    private static function maskEmail(string $email): string {
        $at = strpos($email, '@');
        if ($at === false || $at === 0) return '***';
        return $email[0] . str_repeat('*', max(1, $at - 1)) . substr($email, $at);
    }

    private static function logMailFailure(
        string $host, int $port, string $enc, string $user, string $from,
        string $toEmail, string $error, array $trace
    ): void {
        error_log(sprintf(
            '[MAIL ERROR] Host: %s | Port: %d | Encryption: %s | User: %s | From: %s | Recipient: %s | Error: %s',
            $host, $port, strtoupper($enc),
            $user !== '' ? 'configured' : 'MISSING',
            $from !== '' ? 'configured' : 'MISSING',
            self::maskEmail($toEmail),
            $error !== '' ? $error : 'unknown'
        ));
        if ((defined('APP_DEBUG') && APP_DEBUG) && $trace) {
            error_log('[MAIL SMTP TRACE] ' . implode(' \\n ', array_slice($trace, 0, 25)));
        }
    }

    public static function unreadCount(int $userId): int {
        $stmt = Database::conn()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}
