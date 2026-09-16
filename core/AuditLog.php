<?php
declare(strict_types=1);

final class AuditLog
{
    public static function write(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $stmt = Database::conn()->prepare(
                "INSERT INTO audit_logs
                 (user_id, action, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $action,
                $entityType,
                $entityId,
                $description,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (Throwable $e) {
            // Fail silently to not break app
            if (APP_DEBUG) error_log('AuditLog error: ' . $e->getMessage());
        }
    }
}