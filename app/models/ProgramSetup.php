<?php
declare(strict_types=1);

/**
 * Programs → New program opens the new academic year in one form (docs/CHANGE_PLAN_5.md §7, decision D9: on create
 * only): the program, its renewal window for current scholars and its budget, in ONE transaction — all saved or none.
 * Each part is saved by its own model (Program::save, Budget::save, TermSchedule::save) with the same checks and audit
 * entries as on its own page. The renewal e-mails go out only after the commit, so a failed save never e-mails anyone.
 */
final class ProgramSetup
{
    /** Form section => the label its errors start with. */
    public const PARTS = ['program' => 'Program', 'budget' => 'Budget', 'renewal' => 'Renewal window'];

    /**
     * $data: the program form fields, plus with_budget + budget_total and with_renewal + renewal_opens_at +
     * renewal_deadline (datetime-local). Returns ['ok'=>true,'id','budget_id'|null,'renewal_id'|null,'notified'] or
     * ['ok'=>false,'part' => program|budget|renewal,'error' => "<part>: <message>"].
     */
    public static function create(array $data, int $adminId): array
    {
        $withBudget = !empty($data['with_budget']);
        $withRenewal = !empty($data['with_renewal']);
        $failed = static function (string $part, string $error): never {
            throw new ProgramSetupFailed($part, self::PARTS[$part] . ': ' . $error);
        };
        try {
            $r = Database::transaction(static function () use ($data, $adminId, $withBudget, $withRenewal, $failed): array {
                $p = Program::save($data, null, $adminId);
                if (!$p['ok']) $failed('program', $p['error']);
                $program = Program::find($p['id']);
                $out = ['ok' => true, 'id' => $p['id'], 'budget_id' => null, 'renewal_id' => null];
                if ($withBudget) {
                    $b = Budget::save(['program_id' => $p['id'], 'academic_year' => $program['academic_year'],
                                       'total_budget' => (string)($data['budget_total'] ?? '')], null);
                    if (!$b['ok']) $failed('budget', $b['error']);
                    $out['budget_id'] = $b['id'];
                }
                if ($withRenewal) {
                    // Into the 1st Semester of this program's A.Y. (TermSchedule::save sets it for renewal windows).
                    $w = TermSchedule::save(['purpose' => 'renewal', 'program_id' => $p['id'],
                                             'opens_at' => (string)($data['renewal_opens_at'] ?? ''),
                                             'submission_deadline' => (string)($data['renewal_deadline'] ?? '')], null, $adminId, notify: false);
                    if (!$w['ok']) $failed('renewal', $w['error']);
                    $out['renewal_id'] = $w['id'];
                }
                return $out;
            });
        } catch (ProgramSetupFailed $e) {
            return ['ok' => false, 'part' => $e->part, 'error' => $e->getMessage()];
        }
        $r['notified'] = $r['renewal_id'] ? TermSchedule::notifyAffected(TermSchedule::find($r['renewal_id']), 'created') : 0;
        return $r;
    }
}

/** A part of the one-form setup was refused: rolls the whole transaction back. */
final class ProgramSetupFailed extends DomainException
{
    public function __construct(public readonly string $part, string $message)
    {
        parent::__construct($message);
    }
}
