<?php
declare(strict_types=1);

/** Scholarship programs (B1). is_active = the admin has opened it; applicants can apply only inside its dates. */
final class Program
{
    public static function all(): array
    {
        return Database::conn()->query(
            "SELECT sp.*,
                    (SELECT COUNT(*) FROM applications a WHERE a.program_id = sp.id AND a.status <> 'Draft') AS application_count
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
     * Slots used (B5 counter) = scholars created from this program, not counting scholars who were ended
     * before a second semester was opened for them (their slot is free again).
     */
    public static function slotsUsed(int $programId): int
    {
        $stmt = Database::conn()->prepare(
            "SELECT COUNT(*) FROM scholars s
             WHERE s.program_id = ?
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
        $year  = trim((string)($data['academic_year'] ?? ''));
        $start = trim((string)($data['application_start'] ?? ''));
        $end   = trim((string)($data['application_end'] ?? ''));

        if ($name === '') return ['ok' => false, 'error' => 'Program name is required.'];
        if (!preg_match('/^(\d{4})-(\d{4})$/', $year, $m) || (int)$m[2] !== (int)$m[1] + 1) {
            return ['ok' => false, 'error' => 'Academic year must look like 2026-2027.'];
        }
        $validDate = static fn(string $d): bool => ($t = DateTime::createFromFormat('Y-m-d', $d)) !== false && $t->format('Y-m-d') === $d;
        if (!$validDate($start) || !$validDate($end)) return ['ok' => false, 'error' => 'Application start and end dates are required.'];
        if ($end < $start) return ['ok' => false, 'error' => 'Application end date cannot be before the start date.'];

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
            $old = self::find($id);
            if (!$old) return ['ok' => false, 'error' => 'Program not found.'];
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
}
