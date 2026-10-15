<?php
declare(strict_types=1);

/**
 * Budgets per program + academic year (B6). "Released" is always re-summed from scholarship_releases
 * (via their semester), so Remaining = Total − Released can never disagree with the actual releases.
 */
final class Budget
{
    /** All budgets with Released, Remaining and Committed. */
    public static function all(): array
    {
        $rows = Database::conn()->query(
            "SELECT b.*, sp.program_name, sp.amount_per_semester
             FROM budgets b JOIN scholarship_programs sp ON sp.id = b.program_id
             ORDER BY b.academic_year DESC, sp.program_name"
        )->fetchAll();
        foreach ($rows as &$r) $r['committed'] = self::committed((int)$r['program_id'], $r['academic_year']);
        unset($r);
        return $rows;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT b.*, sp.program_name FROM budgets b JOIN scholarship_programs sp ON sp.id = b.program_id WHERE b.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function forProgramYear(int $programId, string $academicYear): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT b.*, sp.program_name FROM budgets b JOIN scholarship_programs sp ON sp.id = b.program_id
             WHERE b.program_id = ? AND b.academic_year = ?"
        );
        $stmt->execute([$programId, $academicYear]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['committed'] = self::committed($programId, $academicYear);
        return $row;
    }

    /**
     * Suggested total budget for a program's academic year = number of Active scholars of the program × (amount per
     * semester × 2 semesters). Returns ['total','scholars','amount'], or null when the program is missing, has no
     * amount per semester, or has no Active scholar yet. A suggestion only: the budget form never fills it in without
     * the admin clicking "Use this".
     */
    public static function suggestedTotal(int $programId): ?array
    {
        $program = Program::find($programId);
        if (!$program) return null;
        $stmt = Database::conn()->prepare("SELECT COUNT(*) FROM scholars WHERE program_id = ? AND status = 'Active'");
        $stmt->execute([$programId]);
        $scholars = (int)$stmt->fetchColumn();
        $amount = (float)$program['amount_per_semester'];
        if ($scholars <= 0 || $amount <= 0) return null;
        return ['total' => round($scholars * $amount * SEMESTERS_PER_YEAR, 2), 'scholars' => $scholars, 'amount' => $amount];
    }

    /** Sum of the actual releases for scholars of a program in an academic year. */
    public static function actualReleased(int $programId, string $academicYear): float
    {
        $stmt = Database::conn()->prepare(
            "SELECT COALESCE(SUM(r.amount), 0)
             FROM scholarship_releases r
             JOIN scholar_terms t ON t.id = r.term_id
             JOIN scholars s ON s.id = t.scholar_id
             WHERE s.program_id = ? AND t.academic_year = ?"
        );
        $stmt->execute([$programId, $academicYear]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Committed = Σ over Active scholars of the program of
     *             (amount per semester × semesters of this A.Y. not yet released to them), SEMESTERS_PER_YEAR per A.Y.
     */
    public static function committed(int $programId, string $academicYear): float
    {
        $stmt = Database::conn()->prepare(
            "SELECT sp.amount_per_semester,
                    COALESCE(SUM(GREATEST(0, " . SEMESTERS_PER_YEAR . " - (SELECT COUNT(*) FROM scholar_terms t
                                                   JOIN scholarship_releases r ON r.term_id = t.id
                                                   WHERE t.scholar_id = s.id AND t.academic_year = ?))), 0) AS unreleased
             FROM scholarship_programs sp
             LEFT JOIN scholars s ON s.program_id = sp.id AND s.status = 'Active'
             WHERE sp.id = ?
             GROUP BY sp.id"
        );
        $stmt->execute([$academicYear, $programId]);
        $row = $stmt->fetch();
        return $row ? (float)$row['amount_per_semester'] * (int)$row['unreleased'] : 0.0;
    }

    /** Re-sum budgets.released for (program, A.Y.) from the actual releases. No-op when no budget row exists. */
    public static function syncReleased(int $programId, string $academicYear): void
    {
        $actual = self::actualReleased($programId, $academicYear);
        $stmt = Database::conn()->prepare("UPDATE budgets SET released = ? WHERE program_id = ? AND academic_year = ?");
        $stmt->execute([$actual, $programId, $academicYear]);
        if ($stmt->rowCount() > 0) {
            AuditLog::write('budget_release_synchronized', 'budgets', null,
                "Released re-summed for program #$programId ($academicYear): ₱" . number_format($actual, 2));
        }
    }

    /**
     * B8 rule: a release needs a budget for the program + A.Y., and must not exceed its remaining amount.
     * Returns null when allowed, otherwise the reason. Call inside the release transaction.
     */
    public static function releaseBlocker(int $programId, string $programName, string $academicYear, float $amount): ?string
    {
        $stmt = Database::conn()->prepare("SELECT total_budget FROM budgets WHERE program_id = ? AND academic_year = ? FOR UPDATE");
        $stmt->execute([$programId, $academicYear]);
        $total = $stmt->fetchColumn();
        if ($total === false) return "Create a budget for $programName $academicYear first.";
        $remaining = (float)$total - self::actualReleased($programId, $academicYear);
        if ($amount > $remaining + 0.001) {
            return 'This release (' . peso($amount) . ') is more than the remaining budget for '
                . "$programName $academicYear (₱" . number_format($remaining, 2) . ').';
        }
        return null;
    }

    /** Create or update a budget. Total may not be less than what was already released. */
    public static function save(array $data, ?int $id): array
    {
        $programId = (int)($data['program_id'] ?? 0);
        $year = trim((string)($data['academic_year'] ?? ''));
        $total = trim((string)($data['total_budget'] ?? ''));

        $program = Program::find($programId);
        if (!$program) return ['ok' => false, 'error' => 'Choose a scholarship program.'];
        if ($err = AcademicYear::missingError($year)) return ['ok' => false, 'error' => $err];
        if (!is_numeric($total) || (float)$total <= 0) return ['ok' => false, 'error' => 'Total budget must be a number greater than 0.'];
        $released = self::actualReleased($programId, $year);
        if ((float)$total < $released) {
            return ['ok' => false, 'error' => 'Total budget can’t be less than the ' . peso($released) . " already released for {$program['program_name']} $year."];
        }

        $pdo = Database::conn();
        try {
            if ($id) {
                $old = self::find($id);
                if (!$old) return ['ok' => false, 'error' => 'Budget not found.'];
                $pdo->prepare("UPDATE budgets SET program_id = ?, academic_year = ?, total_budget = ? WHERE id = ?")
                    ->execute([$programId, $year, (float)$total, $id]);
                AuditLog::write('budget_updated', 'budgets', $id, "Budget for {$program['program_name']} $year set to ₱" . number_format((float)$total, 2), $old);
                if ($old['program_id'] != $programId || $old['academic_year'] !== $year) self::syncReleased((int)$old['program_id'], $old['academic_year']);
            } else {
                $pdo->prepare("INSERT INTO budgets (program_id, academic_year, total_budget) VALUES (?, ?, ?)")
                    ->execute([$programId, $year, (float)$total]);
                $id = (int)$pdo->lastInsertId();
                AuditLog::write('budget_created', 'budgets', $id, "Budget for {$program['program_name']} $year: ₱" . number_format((float)$total, 2));
            }
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) return ['ok' => false, 'error' => 'A budget for this program and academic year already exists.'];
            throw $e;
        }
        self::syncReleased($programId, $year);   // counts releases recorded before this budget existed
        return ['ok' => true, 'id' => $id];
    }

    /** Only a budget with nothing released against it can be deleted. */
    public static function delete(int $id): array
    {
        $b = self::find($id);
        if (!$b) return ['ok' => false, 'error' => 'Budget not found.'];
        if (self::actualReleased((int)$b['program_id'], $b['academic_year']) > 0) {
            return ['ok' => false, 'error' => 'Money was already released against this budget, so it can’t be deleted.'];
        }
        Database::conn()->prepare("DELETE FROM budgets WHERE id = ?")->execute([$id]);
        AuditLog::write('budget_deleted', 'budgets', $id, "Deleted budget for {$b['program_name']} {$b['academic_year']}", $b);
        return ['ok' => true];
    }
}
