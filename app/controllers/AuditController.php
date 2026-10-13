<?php
declare(strict_types=1);

/** The audit log: every recorded action, filterable and paged. */
final class AuditController
{
    private const PER_PAGE = 50;

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
        $total = AuditLog::count($filters);
        ['page' => $page, 'offset' => $offset, 'pages' => $pages] = paginate($total, self::PER_PAGE);
        $logs = AuditLog::search($filters, self::PER_PAGE, $offset);

        [$actions, $entityTypes] = AuditLog::filterOptions();
        $users = User::options();
        render('admin/audit_Logs', compact('filters', 'logs', 'total', 'page', 'pages', 'actions', 'entityTypes', 'users') + ['pageScripts' => [asset('js/pages/audit-logs.js')]], 'Audit Logs');
    }
}
