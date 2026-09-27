<?php
declare(strict_types=1);

/**
 * Renewal requests (A9 / B9): made from the current semester once its cash was Released; approving one
 * opens the next semester. Documents are stored as term_documents (purpose 'renewal') of the FROM semester.
 */
final class Renewal
{
    /** Renewal status map. 'submit' from null = a brand-new request. */
    public const TRANSITIONS = [
        'submit'   => ['from' => [null, 'Resubmit'], 'to' => 'Submitted'],
        'approve'  => ['from' => ['Submitted'],      'to' => 'Approved'],
        'resubmit' => ['from' => ['Submitted'],      'to' => 'Resubmit'],
    ];

    public static function can(string $action, ?string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$action]['from'] ?? [], true);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT rr.*, s.scholar_code, s.status AS scholar_status, s.program_id,
                    sp.program_name, u.id AS user_id, u.full_name, u.email,
                    ft.academic_year AS from_academic_year, ft.semester AS from_semester, ft.gwa AS from_gwa
             FROM renewal_requests rr
             JOIN scholars s ON s.id = rr.scholar_id
             JOIN scholarship_programs sp ON sp.id = s.program_id
             JOIN applicants ap ON ap.id = s.applicant_id
             JOIN users u ON u.id = ap.user_id
             LEFT JOIN scholar_terms ft ON ft.id = rr.from_term_id
             WHERE rr.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** The renewal request made from a semester, if any. */
    public static function forTerm(int $termId): ?array
    {
        $stmt = Database::conn()->prepare("SELECT id FROM renewal_requests WHERE from_term_id = ? LIMIT 1");
        $stmt->execute([$termId]);
        $id = $stmt->fetchColumn();
        return $id ? self::find((int)$id) : null;
    }

    /**
     * May the scholar upload renewal documents / submit a renewal from this semester?
     * Active scholar, this is the current semester, its cash was Released, and no request is pending/approved.
     */
    public static function editable(array $term): bool
    {
        if ($term['scholar_status'] !== 'Active' || $term['claim_status'] !== 'Released' || !Term::isCurrent($term)) return false;
        $req = self::forTerm((int)$term['id']);
        return self::can('submit', $req['status'] ?? null);
    }

    /** Renewal requests with a status (B9 queue), oldest first. */
    public static function byStatus(string $status): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT rr.*, s.scholar_code, s.status AS scholar_status, sp.program_name, u.full_name,
                    ft.academic_year AS from_academic_year, ft.semester AS from_semester, ft.gwa AS from_gwa
             FROM renewal_requests rr
             JOIN scholars s ON s.id = rr.scholar_id
             JOIN scholarship_programs sp ON sp.id = s.program_id
             JOIN applicants ap ON ap.id = s.applicant_id
             JOIN users u ON u.id = ap.user_id
             LEFT JOIN scholar_terms ft ON ft.id = rr.from_term_id
             WHERE rr.status = ? ORDER BY rr.submitted_at, rr.id"
        );
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }

    /** Guarded status change (conditional on the status the caller saw). Throws DomainException. */
    private static function changeStatus(array $req, string $action, int $adminId, array $set, string $note = ''): void
    {
        if (!self::can($action, $req['status'])) {
            throw new DomainException('This action is not possible while the renewal is "' . $req['status'] . '".');
        }
        $to = self::TRANSITIONS[$action]['to'];
        $cols = ['status' => $to] + $set;
        $assign = implode(', ', array_map(static fn($k) => "`$k` = :$k", array_keys($cols)));
        $stmt = Database::conn()->prepare("UPDATE renewal_requests SET $assign WHERE id = :id AND status = :from_status");
        $stmt->execute($cols + ['id' => (int)$req['id'], 'from_status' => $req['status']]);
        if ($stmt->rowCount() !== 1) throw new DomainException('This renewal was changed by someone else. Please reload the page.');
        AuditLog::write('renewal_' . $action, 'renewal_requests', (int)$req['id'],
            "Renewal for {$req['academic_year']} {$req['semester']}: {$req['status']} → $to" . ($note !== '' ? " — $note" : ''),
            ['status' => $req['status']], ['status' => $to] + $set + ['by' => $adminId]);
    }

    /** B9 Approved: opens the next semester (claim Waiting for Requirements). Only for an Active scholar. */
    public static function approve(int $id, int $adminId): array
    {
        $req = self::find($id);
        if (!$req) return ['ok' => false, 'error' => 'Renewal not found.'];
        if ($req['scholar_status'] !== 'Active') return ['ok' => false, 'error' => 'This scholarship has ended, so it can’t be renewed.'];
        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            self::changeStatus($req, 'approve', $adminId, ['remarks' => null, 'reviewed_by' => $adminId, 'reviewed_at' => date('Y-m-d H:i:s')]);
            $pdo->prepare(
                "INSERT INTO scholar_terms (scholar_id, academic_year, semester, opened_via, claim_status)
                 VALUES (?, ?, ?, 'renewal', 'Waiting for Requirements')"
            )->execute([(int)$req['scholar_id'], $req['academic_year'], $req['semester']]);
            AuditLog::write('term_opened', 'scholar_terms', (int)$pdo->lastInsertId(),
                "Opened {$req['academic_year']} {$req['semester']} for {$req['scholar_code']} (renewal approved)");
            if ($req['from_term_id']) {
                $pdo->prepare("UPDATE term_documents SET status = 'Verified', remarks = NULL WHERE term_id = ? AND purpose = 'renewal'")
                    ->execute([(int)$req['from_term_id']]);
            }
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((int)($e->errorInfo[1] ?? 0) === 1062) return ['ok' => false, 'error' => 'That semester is already open for this scholar.'];
            throw $e;
        }
        Notification::send((int)$req['user_id'], 'Renewal approved',
            "Your renewal was approved. {$req['academic_year']} {$req['semester']} is now open — upload your School ID and Grades to claim your cash.",
            'success', url('applicant/scholarship'), sendEmail: true);
        return ['ok' => true];
    }

    /** B9 Resubmit (remarks required): the chosen documents (default: both) must be uploaded again. */
    public static function resubmit(int $id, string $remarks, array $types, int $adminId): array
    {
        $req = self::find($id);
        if (!$req) return ['ok' => false, 'error' => 'Renewal not found.'];
        $remarks = trim($remarks);
        if ($remarks === '') return ['ok' => false, 'error' => 'Write what the scholar must re-upload.'];
        $types = array_values(array_intersect($types, TERM_DOCUMENT_TYPES['renewal'])) ?: TERM_DOCUMENT_TYPES['renewal'];
        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            self::changeStatus($req, 'resubmit', $adminId, ['remarks' => $remarks, 'reviewed_by' => $adminId, 'reviewed_at' => date('Y-m-d H:i:s')], $remarks);
            $mark = $pdo->prepare("UPDATE term_documents SET status = 'Resubmit', remarks = ? WHERE term_id = ? AND purpose = 'renewal' AND type = ?");
            foreach ($types as $t) $mark->execute([$remarks, (int)$req['from_term_id'], $t]);
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        Notification::send((int)$req['user_id'], 'Renewal: please re-upload',
            'Please re-upload your ' . implode(' and ', $types) . " for your {$req['academic_year']} {$req['semester']} renewal.\nRemarks: $remarks",
            'warning', url('applicant/scholarship'), sendEmail: true);
        return ['ok' => true];
    }

    /** A9: submit (or resubmit) the renewal for the next semester. */
    public static function submit(array $term, int $userId): array
    {
        if (!self::editable($term)) return ['ok' => false, 'error' => 'A renewal can’t be submitted right now.'];
        if (!Term::documentsReady((int)$term['id'], 'renewal')) {
            return ['ok' => false, 'error' => 'Upload both your Enrollment Form and School ID first.'];
        }

        $pdo = Database::conn();
        $req = self::forTerm((int)$term['id']);
        $now = date('Y-m-d H:i:s');
        if ($req) {
            $stmt = $pdo->prepare("UPDATE renewal_requests SET status = 'Submitted', submitted_at = ? WHERE id = ? AND status = 'Resubmit'");
            $stmt->execute([$now, $req['id']]);
            if ($stmt->rowCount() !== 1) return ['ok' => false, 'error' => 'This renewal was changed by someone else. Please reload the page.'];
            $id = (int)$req['id'];
            $target = ['academic_year' => $req['academic_year'], 'semester' => $req['semester']];
        } else {
            $target = Term::nextSemester($term['academic_year'], $term['semester']);
            try {
                $pdo->prepare(
                    "INSERT INTO renewal_requests (scholar_id, from_term_id, academic_year, semester, status, submitted_at)
                     VALUES (?, ?, ?, ?, 'Submitted', ?)"
                )->execute([(int)$term['scholar_id'], (int)$term['id'], $target['academic_year'], $target['semester'], $now]);
            } catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) === 1062) return ['ok' => false, 'error' => 'A renewal for that semester already exists.'];
                throw $e;
            }
            $id = (int)$pdo->lastInsertId();
        }
        AuditLog::write('renewal_submit', 'renewal_requests', $id,
            "Renewal requested for {$target['academic_year']} {$target['semester']}",
            ['status' => $req['status'] ?? null], ['status' => 'Submitted', 'by' => $userId]);
        return ['ok' => true];
    }
}
