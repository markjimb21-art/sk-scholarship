<?php
declare(strict_types=1);

/** The system_settings table (Settings page and the reminder script). The setting() helper reads single values. */
final class Setting
{
    /** Every setting as key => value. */
    public static function all(): array
    {
        return Database::conn()->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** Insert or update each key => value. */
    public static function saveMany(array $values): void
    {
        $stmt = Database::conn()->prepare(
            "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        foreach ($values as $k => $v) $stmt->execute([$k, $v]);
    }

    /** One stored value as it is in the table (null when the row is missing or empty-NULL). */
    public static function raw(string $key): ?string
    {
        $stmt = Database::conn()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $v = $stmt->fetchColumn();
        return $v === false || $v === null ? null : (string)$v;
    }

    /* ---- Daily digest day (bin/send_reminders.php): claimed before sending so a day is never sent twice ---- */

    public static function ensureDigestDayRow(): void
    {
        Database::conn()->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description)
                       VALUES ('daily_digest_sent_on', '', 'string', 'Last day the daily interview digest was sent')")->execute();
    }

    /** Move daily_digest_sent_on from $previous to $today; false when another run got there first. */
    public static function claimDigestDay(string $today, string $previous): bool
    {
        $stmt = Database::conn()->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'daily_digest_sent_on' AND setting_value = ?");
        $stmt->execute([$today, $previous]);
        return $stmt->rowCount() === 1;
    }

    /** Give the day back after a failed digest, so the next run retries. */
    public static function releaseDigestDay(string $previous): void
    {
        Database::conn()->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'daily_digest_sent_on'")->execute([$previous]);
    }
}
