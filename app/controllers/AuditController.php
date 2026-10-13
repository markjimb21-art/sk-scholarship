<?php
declare(strict_types=1);

/** The audit log: every recorded action, filterable and paged. */
final class AuditController
{
    public function auditLogs(): void
    {
        $filters = [
            'q'           => trim(get_str('q')),
            'action'      => get_str('action'),
            'entity_type' => get_str('entity_type'),
            'user_id'     => get_str('user_id'),
            'date_from'   => get_str('date_from'),
            'date_to'     => get_str('date_to'),
        ];
        $limit = 50;
        $total = AuditLog::count($filters);
        ['page' => $page, 'offset' => $offset, 'pages' => $pages] = paginate($total, $limit);
        $logs = AuditLog::search($filters, $limit, $offset);

        [$actions, $entityTypes] = AuditLog::filterOptions();
        $users = User::options();
        render('admin/audit_Logs', compact('filters', 'logs', 'total', 'page', 'pages', 'actions', 'entityTypes', 'users') + ['pageScripts' => [asset('js/pages/audit-logs.js')]], 'Audit Logs');
    }
}
