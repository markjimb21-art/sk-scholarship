<?php
declare(strict_types=1);

/**
 * Email OTP codes (registration + password_reset). Codes are never stored in plaintext —
 * only a password_hash() of the 6-digit code is kept, alongside an expiry, an attempt counter,
 * and a resend cooldown. See database/migration_2026_09_otp_and_budget.sql for the schema.
 *
 * All expiry/cooldown comparisons are computed by MySQL's own NOW(), never PHP's time()/date()/
 * strtotime(), so nothing here can drift if PHP and MySQL run in different timezones.
 */
final class Otp
{
    private const TTL_MINUTES     = 10;
    private const MAX_ATTEMPTS    = 5;
    private const RESEND_COOLDOWN = 60; // seconds

    /**
     * Issue a fresh OTP for $userId/$purpose. Any previous unverified, unexpired code for the
     * same user+purpose is invalidated first (one active code at a time; new code always wins).
     * Returns ['ok'=>true,'code'=>'123456','ttl_minutes'=>10] or ['ok'=>false,'wait'=>seconds]
     * if the resend cooldown is still active.
     */
    public static function issue(int $userId, string $purpose): array
    {
        $pdo = Database::conn();

        $stmt = $pdo->prepare(
            "SELECT GREATEST(TIMESTAMPDIFF(SECOND, NOW(), resend_available_at), 0) AS wait_seconds
             FROM email_otp_codes
             WHERE user_id = ? AND purpose = ? AND verified_at IS NULL AND expires_at > NOW() AND resend_available_at > NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId, $purpose]);
        $wait = $stmt->fetchColumn();
        if ($wait !== false && (int)$wait > 0) {
            return ['ok' => false, 'wait' => (int)$wait];
        }

        // Invalidate any still-pending codes for this user+purpose (enforces "one active code").
        $pdo->prepare(
            "UPDATE email_otp_codes SET expires_at = NOW() WHERE user_id = ? AND purpose = ? AND verified_at IS NULL"
        )->execute([$userId, $purpose]);

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttlMin = (int)self::TTL_MINUTES;
        $cooldownSec = (int)self::RESEND_COOLDOWN;

        // $ttlMin/$cooldownSec are hardcoded class constants (not user input) — safe to inline.
        $pdo->prepare(
            "INSERT INTO email_otp_codes (user_id, purpose, otp_hash, expires_at, max_attempts, resend_available_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL $ttlMin MINUTE), ?, DATE_ADD(NOW(), INTERVAL $cooldownSec SECOND))"
        )->execute([$userId, $purpose, password_hash($code, PASSWORD_DEFAULT), self::MAX_ATTEMPTS]);

        return ['ok' => true, 'code' => $code, 'ttl_minutes' => self::TTL_MINUTES];
    }

    /**
     * Verify a submitted code. Returns one of:
     *   ['ok'=>true]
     *   ['ok'=>false,'error'=>'none']            no pending code (never issued / already used)
     *   ['ok'=>false,'error'=>'expired']
     *   ['ok'=>false,'error'=>'locked']           too many wrong attempts
     *   ['ok'=>false,'error'=>'invalid']          wrong code, attempts remain
     */
    public static function verify(int $userId, string $purpose, string $code): array
    {
        $pdo = Database::conn();
        $stmt = $pdo->prepare(
            "SELECT *, (expires_at <= NOW()) AS is_expired
             FROM email_otp_codes WHERE user_id = ? AND purpose = ? AND verified_at IS NULL
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId, $purpose]);
        $row = $stmt->fetch();
        if (!$row) return ['ok' => false, 'error' => 'none'];

        if ((int)$row['is_expired'] === 1) return ['ok' => false, 'error' => 'expired'];
        if ((int)$row['attempts'] >= (int)$row['max_attempts']) return ['ok' => false, 'error' => 'locked'];

        if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $row['otp_hash'])) {
            $pdo->prepare("UPDATE email_otp_codes SET attempts = attempts + 1 WHERE id = ?")->execute([$row['id']]);
            return ['ok' => false, 'error' => 'invalid'];
        }

        // One-time use: mark verified so it can never be replayed, and expire any siblings.
        $pdo->prepare("UPDATE email_otp_codes SET verified_at = NOW() WHERE id = ?")->execute([$row['id']]);
        $pdo->prepare(
            "UPDATE email_otp_codes SET expires_at = NOW() WHERE user_id = ? AND purpose = ? AND id != ? AND verified_at IS NULL"
        )->execute([$userId, $purpose, $row['id']]);

        return ['ok' => true];
    }
}
