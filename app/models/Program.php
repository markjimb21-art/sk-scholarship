<?php
declare(strict_types=1);

final class Program
{
    public static function all(): array
    {
        return Database::conn()->query(
            "SELECT sp.*,
                    (SELECT COUNT(*) FROM applications a WHERE a.program_id = sp.id) AS application_count,
                    (SELECT COUNT(*) FROM scholars s WHERE s.program_id = sp.id
                        AND s.status NOT IN ('Withdrawn','Disqualified')) AS scholar_count
             FROM scholarship_programs sp
             ORDER BY sp.application_start DESC, sp.id DESC"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM scholarship_programs WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * The program applicants can currently apply to: active AND today inside its application window.
     * If several overlap, the one that opened most recently wins.
     */
    public static function open(): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM scholarship_programs
             WHERE is_active = 1 AND application_start <= ? AND application_end >= ?
             ORDER BY application_start DESC, id DESC LIMIT 1"
        );
        $today = date('Y-m-d');
        $stmt->execute([$today, $today]);
        return $stmt->fetch() ?: null;
    }

    /** Is today inside the program's application_start..application_end window (inclusive)? */
    public static function windowOpen(array $program): bool
    {
        $today = date('Y-m-d');
        return $program['application_start'] <= $today && $today <= $program['application_end'];
    }

    /** Scholars already counted against the program's slots (everyone except Withdrawn/Disqualified). */
    public static function slotsUsed(int $programId): int
    {
        $stmt = Database::conn()->prepare(
            "SELECT COUNT(*) FROM scholars WHERE program_id = ? AND status NOT IN ('Withdrawn','Disqualified')"
        );
        $stmt->execute([$programId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Create or update a program. Validates everything server-side (dates, non-negative numbers).
     * Returns ['ok'=>true,'id'=>int] or ['ok'=>false,'error'=>string].
     */
    public static function save(array $data, ?int $id, int $actorId): array
    {
        $name  = trim((string)($data['program_name'] ?? ''));
        $year  = trim((string)($data['academic_year'] ?? ''));
        $start = trim((string)($data['application_start'] ?? ''));
        $end   = trim((string)($data['application_end'] ?? ''));

        if ($name === '') return ['ok' => false, 'error' => 'Program name is required.'];
        if (!preg_match('/^\d{4}-\d{4}$/', $year)) return ['ok' => false, 'error' => 'Academic year must look like 2026-2027.'];
        $validDate = static fn(string $d): bool => ($t = DateTime::createFromFormat('Y-m-d', $d)) !== false && $t->format('Y-m-d') === $d;
        if (!$validDate($start) || !$validDate($end)) return ['ok' => false, 'error' => 'Application start and end dates are required.'];
        if ($end < $start) return ['ok' => false, 'error' => 'Application end date cannot be before the start date.'];

        // Optional numeric criteria: blank => NULL (no limit); anything given must be a number >= 0.
        $numbers = [];
        $labels = ['min_gpa' => 'Minimum GPA', 'max_family_income' => 'Maximum family income', 'max_age' => 'Maximum age',
                   'budget_amount' => 'Budget amount', 'slots_available' => 'Slots available'];
        foreach ($labels as $field => $label) {
            $v = trim((string)($data[$field] ?? ''));
            if ($v === '') { $numbers[$field] = null; continue; }
            if (!is_numeric($v) || (float)$v < 0) return ['ok' => false, 'error' => "$label must be a number ≥ 0."];
            $numbers[$field] = in_array($field, ['max_age', 'slots_available'], true) ? (int)$v : (float)$v;
        }
        if ($numbers['min_gpa'] !== null && ($numbers['min_gpa'] < 1 || $numbers['min_gpa'] > 5)) {
            return ['ok' => false, 'error' => 'Minimum GPA must be between 1.00 and 5.00 (Philippine grading, 1.00 is highest).'];
        }

        $row = [
            'program_name'         => $name,
            'description'          => trim((string)($data['description'] ?? '')) ?: null,
            'academic_year'        => $year,
            'semester'             => trim((string)($data['semester'] ?? '')) ?: null,
            'min_gpa'              => $numbers['min_gpa'],
            'max_family_income'    => $numbers['max_family_income'],
            'max_age'              => $numbers['max_age'],
            'citizenship_required' => trim((string)($data['citizenship_required'] ?? '')) ?: null,
            'required_residency'   => trim((string)($data['required_residency'] ?? '')) ?: null,
            'budget_amount'        => $numbers['budget_amount'] ?? 0,
            'slots_available'      => $numbers['slots_available'] ?? 0,
            'application_start'    => $start,
            'application_end'      => $end,
            'is_active'            => !empty($data['is_active']) ? 1 : 0,
        ];

        $pdo = Database::conn();
        if ($id) {
            $old = self::find($id);
            if (!$old) return ['ok' => false, 'error' => 'Program not found.'];
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

    public static function toggle(int $id): ?bool
    {
        $program = self::find($id);
        if (!$program) return null;
        $newState = $program['is_active'] ? 0 : 1;
        Database::conn()->prepare("UPDATE scholarship_programs SET is_active = ? WHERE id = ?")->execute([$newState, $id]);
        AuditLog::write('program_status_changed', 'scholarship_programs', $id,
            ($newState ? 'Activated' : 'Deactivated') . " program \"{$program['program_name']}\"",
            ['is_active' => (int)$program['is_active']], ['is_active' => $newState]);
        return (bool)$newState;
    }
}
