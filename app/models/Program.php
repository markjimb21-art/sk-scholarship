<?php
declare(strict_types=1);

/** Scholarship programs (B1). is_active = the admin has opened it; applicants can apply only inside its dates. */
final class Program
{
    public static function all(): array
    {
        return Database::conn()->query(
            "SELECT sp.*,
                    (SELECT COUNT(*) FROM applications a WHERE a.program_id = sp.id AND a.status <> 'Draft') AS application_count,
                    (SELECT COUNT(*) FROM applications a WHERE a.program_id = sp.id AND a.status = 'Draft') AS draft_count,
                    (SELECT COUNT(*) FROM scholars s WHERE s.program_id = sp.id) AS scholar_count
             FROM scholarship_programs sp
             ORDER BY sp.application_start DESC, sp.id DESC"
        )->fetchAll();
    }

    /** id + name + A.Y. for program pickers, newest first. */
    public static function options(): array
    {
        return Database::conn()->query(
            "SELECT id, program_name, academic_year FROM scholarship_programs ORDER BY application_start DESC, id DESC"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM scholarship_programs WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * The program applicants can currently apply to: open AND today inside its application dates.
     * If several overlap, the one that opened most recently wins.
     */
    public static function open(): ?array
    {
        $today = date('Y-m-d');
        $stmt = Database::conn()->prepare(
            "SELECT * FROM scholarship_programs
             WHERE is_active = 1 AND application_start <= ? AND application_end >= ?
             ORDER BY application_start DESC, id DESC LIMIT 1"
        );
        $stmt->execute([$today, $today]);
        return $stmt->fetch() ?: null;
    }

    /** Is today inside application_start..application_end (inclusive)? Works on any row with those two keys. */
    public static function windowOpen(array $program): bool
    {
        $today = date('Y-m-d');
        return $program['application_start'] <= $today && $today <= $program['application_end'];
    }

    /** Program chosen on an admin page (?program_id=), defaulting to the newest program. */
    public static function fromRequest(): ?array
    {
        return self::selected(get_id('program_id'));
    }

    /** The newest program unless $programId names one that exists. */
    public static function selected(?int $programId): ?array
    {
        if ($programId) {
            $p = self::find($programId);
            if ($p) return $p;
        }
        $row = Database::conn()->query(
            "SELECT * FROM scholarship_programs ORDER BY application_start DESC, id DESC LIMIT 1"
        )->fetch();
        return $row ?: null;
    }

    /**
     * Slots used (B5 counter) = scholars created from this program, not counting graduated scholars or scholars who
     * were ended before a second semester was opened for them (their slot is free again).
     */
    public static function slotsUsed(int $programId): int
    {
        $stmt = Database::conn()->prepare(
            "SELECT COUNT(*) FROM scholars s
             WHERE s.program_id = ?
               AND NOT (s.status = 'Ended' AND s.end_reason = 'Graduated')
               AND NOT (s.status = 'Ended' AND (SELECT COUNT(*) FROM scholar_terms t WHERE t.scholar_id = s.id) <= 1)"
        );
        $stmt->execute([$programId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Create or update a program. Validates everything server-side (dates, numbers >= 0).
     * Returns ['ok'=>true,'id'=>int] or ['ok'=>false,'error'=>string].
     */
    public static function save(array $data, ?int $id, int $actorId): array
    {
        $name  = trim((string)($data['program_name'] ?? ''));
        $start = trim((string)($data['application_start'] ?? ''));
        $end   = trim((string)($data['application_end'] ?? ''));

        if ($name === '') return ['ok' => false, 'error' => 'Program name is required.'];
        $validDate = static fn(string $d): bool => ($t = DateTime::createFromFormat('Y-m-d', $d)) !== false && $t->format('Y-m-d') === $d;
        if (!$validDate($start) || !$validDate($end)) return ['ok' => false, 'error' => 'Application start and end dates are required.'];
        if ($end < $start) return ['ok' => false, 'error' => 'Application end date cannot be before the start date.'];
        // The academic year follows the application start date — fixed once the program has scholars (their semesters use it).
        $old = $id ? self::find($id) : null;
        if ($id && !$old) return ['ok' => false, 'error' => 'Program not found.'];
        $year = $old && self::hasScholars($id) ? $old['academic_year'] : self::academicYearFor($start);

        // Numbers: blank limit => NULL (no limit); anything given must be a number >= 0.
        $labels = ['amount_per_semester' => 'Amount per semester', 'slots_available' => 'Number of slots',
                   'min_gpa' => 'Minimum GWA', 'max_family_income' => 'Maximum family income', 'max_age' => 'Maximum age'];
        $n = [];
        foreach ($labels as $field => $label) {
            $v = trim((string)($data[$field] ?? ''));
            if ($v === '') { $n[$field] = null; continue; }
            if (!is_numeric($v) || (float)$v < 0) return ['ok' => false, 'error' => "$label must be a number ≥ 0."];
            $n[$field] = in_array($field, ['max_age', 'slots_available'], true) ? (int)$v : round((float)$v, 2);
        }
        if (!$n['amount_per_semester']) return ['ok' => false, 'error' => 'Amount per semester is required and must be more than 0.'];
        if (!$n['slots_available'])     return ['ok' => false, 'error' => 'Number of slots is required and must be more than 0.'];
        if ($n['min_gpa'] !== null && ($n['min_gpa'] < 1 || $n['min_gpa'] > 5)) {
            return ['ok' => false, 'error' => 'Minimum GWA must be between 1.00 and 5.00 (1.00 is highest).'];
        }

        $row = [
            'program_name'        => $name,
            'academic_year'       => $year,
            'amount_per_semester' => $n['amount_per_semester'],
            'min_gpa'             => $n['min_gpa'],
            'max_family_income'   => $n['max_family_income'],
            'max_age'             => $n['max_age'],
            'slots_available'     => $n['slots_available'],
            'application_start'   => $start,
            'application_end'     => $end,
            'is_active'           => !empty($data['is_active']) ? 1 : 0,
        ];

        $pdo = Database::conn();
        if ($id) {
            if ($n['slots_available'] < self::slotsUsed($id)) {
                return ['ok' => false, 'error' => 'Slots cannot be fewer than the ' . self::slotsUsed($id) . ' already used.'];
            }
            $set = implode(', ', array_map(static fn($k) => "$k = :$k", array_keys($row)));
            $pdo->prepare("UPDATE scholarship_programs SET $set WHERE id = :id")->execute($row + ['id' => $id]);
            AuditLog::write('program_updated', 'scholarship_programs', $id, "Updated program \"$name\" ($year)", $old, $row);
        } else {
            $cols = array_keys($row);
            $pdo->prepare(
                "INSERT INTO scholarship_programs (" . implode(', ', $cols) . ", created_by)
                 VALUES (:" . implode(', :', $cols) . ", :created_by)"
            )->execute($row + ['created_by' => $actorId]);
            $id = (int)$pdo->lastInsertId();
            AuditLog::write('program_created', 'scholarship_programs', $id, "Created program \"$name\" ($year)", null, $row);
        }
        return ['ok' => true, 'id' => $id];
    }

    /**
     * The academic year of a program, from its application start date: a start from ACADEMIC_YEAR_START_MONTH (June) to
     * December is that year's A.Y. (2026-10-14 → 2026-2027); January–May belongs to the A.Y. that began the year before
     * (2027-02-01 → 2026-2027). Also shown live in the program form (pages/programs.js — same rule).
     */
    public static function academicYearFor(string $startDate): string
    {
        $y = (int)substr($startDate, 0, 4);
        if ((int)substr($startDate, 5, 2) < ACADEMIC_YEAR_START_MONTH) $y--;
        return $y . '-' . ($y + 1);
    }

    /** Has this program made any scholar yet? (Its academic year is then fixed.) */
    public static function hasScholars(int $id): bool
    {
        $stmt = Database::conn()->prepare("SELECT 1 FROM scholars WHERE program_id = ? LIMIT 1");
        $stmt->execute([$id]);
        return (bool)$stmt->fetchColumn();
    }

    /** Open <-> close. Returns the new state, or null if not found. */
    public static function toggle(int $id): ?bool
    {
        $program = self::find($id);
        if (!$program) return null;
        $newState = $program['is_active'] ? 0 : 1;
        Database::conn()->prepare("UPDATE scholarship_programs SET is_active = ? WHERE id = ?")->execute([$newState, $id]);
        AuditLog::write('program_' . ($newState ? 'opened' : 'closed'), 'scholarship_programs', $id,
            ($newState ? 'Opened' : 'Closed') . " program \"{$program['program_name']}\"",
            ['is_active' => (int)$program['is_active']], ['is_active' => $newState]);
        return (bool)$newState;
    }

    /**
     * Delete a program nobody SUBMITTED an application to — so it has no screened applications or scholars, whose
     * records are never deleted (docs/PROCESS.md "Data retention"). Unfinished Drafts on it are deleted with it (their
     * documents and files too) and each applicant is told; its own setup also goes: budgets, claim/renewal windows and
     * interview schedules. Audit-logged. Returns ['ok'=>true,'drafts'=>n] or ['ok'=>false,'error'].
     */
    public static function delete(int $id, int $actorId): array
    {
        $program = self::find($id);
        if (!$program) return ['ok' => false, 'error' => 'Program not found.'];
        $pdo = Database::conn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE program_id = ? AND status <> 'Draft'");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['ok' => false, 'error' => "People have submitted applications to \"{$program['program_name']}\", so it is kept with their records. Close it instead."];
        }
        $stmt = $pdo->prepare("SELECT id, application_code FROM applications WHERE program_id = ? AND status = 'Draft'");
        $stmt->execute([$id]);
        $drafts = $stmt->fetchAll();
        $files = [];
        $users = [];
        foreach ($drafts as $d) {
            $f = $pdo->prepare("SELECT file_path FROM documents WHERE application_id = ? AND file_path <> ''");
            $f->execute([(int)$d['id']]);
            $files = array_merge($files, $f->fetchAll(PDO::FETCH_COLUMN));
            $users[] = Application::userId((int)$d['id']);
        }

        Database::transaction(static function () use ($pdo, $id, $program, $actorId, $drafts): void {
            $removed = [];
            foreach ($drafts as $d) {
                // documents, interview attempts and slot requests of the application go with it (ON DELETE CASCADE)
                $pdo->prepare("DELETE FROM applications WHERE id = ? AND status = 'Draft'")->execute([(int)$d['id']]);
                AuditLog::write('application_deleted', 'applications', (int)$d['id'],
                    "Draft {$d['application_code']} deleted with its program \"{$program['program_name']}\"", null, ['by' => $actorId]);
            }
            if ($drafts) $removed['draft_applications'] = count($drafts);
            foreach (['budgets', 'term_schedules', 'interview_schedules'] as $table) {
                $stmt = $pdo->prepare("DELETE FROM `$table` WHERE program_id = ?");
                $stmt->execute([$id]);
                if ($stmt->rowCount()) $removed[$table] = $stmt->rowCount();
            }
            $pdo->prepare("DELETE FROM scholarship_programs WHERE id = ?")->execute([$id]);
            AuditLog::write('program_deleted', 'scholarship_programs', $id,
                "Deleted program \"{$program['program_name']}\" (A.Y. {$program['academic_year']}; no submitted applications)",
                ['program_name' => $program['program_name'], 'academic_year' => $program['academic_year']] + $removed, ['by' => $actorId]);
        });
        foreach ($files as $rel) DocumentStorage::deleteFile($rel);   // only after the rows are gone
        foreach (array_filter($users) as $uid) {
            Notification::send((int)$uid, 'Application removed',
                "The scholarship program \"{$program['program_name']}\" was removed by the SK office, so your unfinished application for it was removed too. "
                . 'You can apply again when a program is open.', 'warning', url('applicant/dashboard'), sendEmail: true);
        }
        return ['ok' => true, 'drafts' => count($drafts)];
    }
}
