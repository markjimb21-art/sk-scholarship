<?php
declare(strict_types=1);

/** The audit log: every recorded action, filterable and paged. */
final class AuditController
{
    public function auditLogs(): void
    {
        $filters = [
            'q'           => trim((string)($_GET['q'] ?? '')),
            'action'      => (string)($_GET['action'] ?? ''),
            'entity_type' => (string)($_GET['entity_type'] ?? ''),
            'user_id'     => (string)($_GET['user_id'] ?? ''),
            'date_from'   => (string)($_GET['date_from'] ?? ''),
            'date_to'     => (string)($_GET['date_to'] ?? ''),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $logs = AuditLog::search($filters, $limit, ($page - 1) * $limit);
        $total = AuditLog::count($filters);
        $pages = max(1, (int)ceil($total / $limit));

        [$actions, $entityTypes] = AuditLog::filterOptions();
        $users = User::options();
        render('admin/audit_Logs', compact('filters', 'logs', 'total', 'page', 'pages', 'actions', 'entityTypes', 'users'), 'Audit Logs');
    }
}
