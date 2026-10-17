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
                    (SELECT COUNT(*) FROM applications a WHERE a.program_id = sp.id) AS any_application_count,
                    (SELECT COUNT(DISTINCT t.scholar_id) FROM scholar_terms t WHERE t.program_id = sp.id) AS scholar_count
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
     * Seats of a program (decision D5, docs/CHANGE_PLAN_5.md §6). One program = one academic-year intake, shared by
     * returning scholars and new ones. A scholar holds a seat in P when their first semester in P is a 1st Semester:
     * opened by a renewal = returning, by an approval = new. A scholar ended with only one semester in P frees the
     * seat; a graduated scholar keeps it. Renewal requests into P still Submitted / Resubmit (Active scholars without a
     * semester in P yet) hold a seat until decided — also after the deadline; scholars who never submitted hold none.
     * Returns ['slots','returning','new','pending','used' (returning + new),'free' (slots − returning − pending − new, ≥ 0)].
     */
    public static function seats(int $programId): array
    {
        $pdo = Database::conn();
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(ft.opened_via = 'renewal'), 0) AS returning_count, COALESCE(SUM(ft.opened_via = 'approval'), 0) AS new_count
             FROM scholars s
             JOIN scholar_terms ft ON ft.id = (SELECT x.id FROM scholar_terms x WHERE x.scholar_id = s.id AND x.program_id = :pid_first
                                               ORDER BY x.academic_year, x.semester LIMIT 1)
             WHERE ft.semester = '1st Semester'
               AND NOT (s.status = 'Ended'
                        AND (SELECT COUNT(*) FROM scholar_terms c WHERE c.scholar_id = s.id AND c.program_id = :pid_count) <= 1)"
        );
        $stmt->execute(['pid_first' => $programId, 'pid_count' => $programId]);
        $held = $stmt->fetch();
        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT rr.scholar_id) FROM renewal_requests rr JOIN scholars s ON s.id = rr.scholar_id
             WHERE rr.program_id = :pid_rr AND rr.semester = '1st Semester' AND rr.status IN ('Submitted', 'Resubmit') AND s.status = 'Active'
               AND NOT EXISTS (SELECT 1 FROM scholar_terms t WHERE t.scholar_id = rr.scholar_id AND t.program_id = :pid_term)"
        );
        $stmt->execute(['pid_rr' => $programId, 'pid_term' => $programId]);
        $pending = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT slots_available FROM scholarship_programs WHERE id = ?");
        $stmt->execute([$programId]);
        $slots = (int)$stmt->fetchColumn();
        $returning = (int)$held['returning_count'];
        $new = (int)$held['new_count'];
        return ['slots' => $slots, 'returning' => $returning, 'new' => $new, 'pending' => $pending,
                'used' => $returning + $new, 'free' => max(0, $slots - $returning - $pending - $new)];
    }

    /** "Slots 300 · Returning 120 (+15 pending renewal) · New approved 140 · Free 25" (decisions, dashboard, programs). */
    public static function seatsLine(array $seats): string
    {
        return "Slots {$seats['slots']} · Returning {$seats['returning']}"
            . ($seats['pending'] ? " (+{$seats['pending']} pending renewal)" : '')
            . " · New approved {$seats['new']} · Free {$seats['free']}";
    }

    /**
     * Seats taken for good (B5 counter, the floor for lowering a program's slots) = returning + new scholars (seats()).
     * Pending renewals are not included: they may still be refused.
     */
    public static function slotsUsed(int $programId): int
    {
        return self::seats($programId)['used'];
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
        // The academic year is typed (the form pre-fills it from the opening date, academicYearFor); left blank it follows
        // the opening date. Fixed once the program has scholars: their semesters use it.
        $old = $id ? self::find($id) : null;
        if ($id && !$old) return ['ok' => false, 'error' => 'Program not found.'];
        if ($old && self::hasScholars($id)) {
            $year = $old['academic_year'];
        } else {
            $year = trim((string)($data['academic_year'] ?? '')) ?: self::academicYearFor($start);
            if (!preg_match('/^(\d{4})-(\d{4})$/', $year, $m) || (int)$m[2] !== (int)$m[1] + 1) {
                return ['ok' => false, 'error' => 'Academic year must look like 2026-2027 (the second year is the first + 1).'];
            }
        }
        // One program per academic year (uniq_program_year): a renewal into a year must know which program it goes into.
        if ($other = self::forAcademicYear($year)) {
            if ((int)$other['id'] !== (int)$id) return ['ok' => false, 'error' => self::yearTakenMessage($year, $other['program_name'])];
        }

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
        try {
            if ($id) {
                $seats = self::seats($id);
                if ($n['slots_available'] < $seats['used']) {
                    return ['ok' => false, 'error' => "Slots cannot be fewer than the {$seats['used']} already taken ({$seats['returning']} returning, {$seats['new']} new)."];
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
        } catch (PDOException $e) {
            // Another save took the year in the meantime.
            if ((int)($e->errorInfo[1] ?? 0) === 1062) return ['ok' => false, 'error' => self::yearTakenMessage($year, null)];
            throw $e;
        }
        return ['ok' => true, 'id' => $id];
    }

    /** The program of an academic year (at most one, uniq_program_year), or null. */
    public static function forAcademicYear(string $academicYear): ?array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM scholarship_programs WHERE academic_year = ? LIMIT 1");
        $stmt->execute([$academicYear]);
        return $stmt->fetch() ?: null;
    }

    private static function yearTakenMessage(string $year, ?string $programName): string
    {
        return "A.Y. $year already has a program" . ($programName !== null ? " (\"$programName\")" : '')
            . '. There is one program per academic year, so returning scholars know which one they renew into — edit that program instead.';
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

    /** Does any scholar have a semester in this program (new or returning)? Its academic year is then fixed. */
    public static function hasScholars(int $id): bool
    {
        $stmt = Database::conn()->prepare("SELECT 1 FROM scholar_terms WHERE program_id = ? LIMIT 1");
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
     * Delete a program nobody applied to (not even a Draft) and no scholar renewed into — so it has no applications,
     * semesters or renewals, whose records are never deleted (docs/PROCESS.md "Data retention"). Its own setup goes with
     * it: budgets, claim/renewal windows and interview schedules (empty, since there are no applications). Audit-logged.
     * Returns ['ok'=>true] or ['ok'=>false,'error'].
     */
    public static function delete(int $id, int $actorId): array
    {
        $program = self::find($id);
        if (!$program) return ['ok' => false, 'error' => 'Program not found.'];
        $count = static function (string $table) use ($id): int {
            $stmt = Database::conn()->prepare("SELECT COUNT(*) FROM `$table` WHERE program_id = ?");
            $stmt->execute([$id]);
            return (int)$stmt->fetchColumn();
        };
        if ($count('applications') > 0) {
            return ['ok' => false, 'error' => "People have applied to \"{$program['program_name']}\", so it is kept with their records. Close it instead."];
        }
        if ($count('scholar_terms') > 0 || $count('renewal_requests') > 0) {
            return ['ok' => false, 'error' => "Scholars renewed into \"{$program['program_name']}\", so it is kept with their records. Close it instead."];
        }
        Database::transaction(static function () use ($id, $program, $actorId): void {
            $pdo = Database::conn();
            $removed = [];
            foreach (['budgets', 'term_schedules', 'interview_schedules'] as $table) {
                $stmt = $pdo->prepare("DELETE FROM `$table` WHERE program_id = ?");
                $stmt->execute([$id]);
                if ($stmt->rowCount()) $removed[$table] = $stmt->rowCount();
            }
            $pdo->prepare("DELETE FROM scholarship_programs WHERE id = ?")->execute([$id]);
            AuditLog::write('program_deleted', 'scholarship_programs', $id,
                "Deleted program \"{$program['program_name']}\" (A.Y. {$program['academic_year']}; nobody had applied)",
                ['program_name' => $program['program_name'], 'academic_year' => $program['academic_year']] + $removed, ['by' => $actorId]);
        });
        return ['ok' => true];
    }
}
