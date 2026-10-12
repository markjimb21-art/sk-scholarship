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

    /** Audit-log page: one page of entries matching $filters (q, action, entity_type, user_id, date_from, date_to), newest first. */
    public static function search(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::where($filters);
        $stmt = Database::conn()->prepare("SELECT al.*, u.full_name AS actor_name, u.email AS actor_email
                               FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
                               WHERE 1=1 $where ORDER BY al.created_at DESC, al.id DESC LIMIT $limit OFFSET " . $offset);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(array $filters): int
    {
        [$where, $params] = self::where($filters);
        $cnt = Database::conn()->prepare("SELECT COUNT(*) FROM audit_logs al WHERE 1=1 $where");
        $cnt->execute($params);
        return (int)$cnt->fetchColumn();
    }

    /** Distinct actions and entity types, for the filter dropdowns. */
    public static function filterOptions(): array
    {
        $pdo = Database::conn();
        return [
            $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(),
            $pdo->query("SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type")->fetchAll(),
        ];
    }

    /** The trail of one application and its documents (the application page), newest first, at most 50. */
    public static function forApplication(int $applicationId): array
    {
        $audit = Database::conn()->prepare(
            "SELECT al.*, u.full_name AS actor FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
             WHERE (al.entity_type = 'applications' AND al.entity_id = ?)
                OR (al.entity_type = 'documents' AND al.entity_id IN (SELECT id FROM documents WHERE application_id = ?))
             ORDER BY al.created_at DESC, al.id DESC LIMIT 50"
        );
        $audit->execute([$applicationId, $applicationId]);
        return $audit->fetchAll();
    }

    /** WHERE fragment + parameters for the audit-log filters (every value is a bound parameter). */
    private static function where(array $filters): array
    {
        $where = '';
        $params = [];
        if ($filters['q'] !== '')           { $where .= " AND (al.description LIKE :q1 OR al.action LIKE :q2)"; $params['q1'] = $params['q2'] = '%' . $filters['q'] . '%'; }
        if ($filters['action'] !== '')      { $where .= " AND al.action = :a"; $params['a'] = $filters['action']; }
        if ($filters['entity_type'] !== '') { $where .= " AND al.entity_type = :et"; $params['et'] = $filters['entity_type']; }
        if ($filters['user_id'] !== '')     { $where .= " AND al.user_id = :uid"; $params['uid'] = (int)$filters['user_id']; }
        if ($filters['date_from'] !== '')   { $where .= " AND DATE(al.created_at) >= :df"; $params['df'] = $filters['date_from']; }
        if ($filters['date_to'] !== '')     { $where .= " AND DATE(al.created_at) <= :dt"; $params['dt'] = $filters['date_to']; }
        return [$where, $params];
    }
}