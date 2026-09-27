<?php
declare(strict_types=1);

final class Budget
{
    public static function all(): array
    {
        $pdo = Database::conn();
        return $pdo->query(
            "SELECT b.*, sp.program_name, sp.academic_year AS program_year
             FROM budgets b
             JOIN scholarship_programs sp ON sp.id = b.program_id
             ORDER BY b.academic_year DESC, sp.program_name ASC"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT b.*, sp.program_name FROM budgets b JOIN scholarship_programs sp ON sp.id = b.program_id WHERE b.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function programs(): array
    {
        return Database::conn()->query("SELECT id, program_name, academic_year FROM scholarship_programs ORDER BY program_name")->fetchAll();
    }

    /** Totals for the summary cards. `released` always comes from budgets.released, which syncReleased() keeps in sync with scholarship_releases. */
    public static function summary(): array
    {
        $row = Database::conn()->query(
            "SELECT COALESCE(SUM(total_budget),0) AS total_budget,
                    COALESCE(SUM(allocated),0)    AS total_allocated,
                    COALESCE(SUM(released),0)     AS total_released,
                    COALESCE(SUM(total_budget),0) - COALESCE(SUM(released),0) AS remaining
             FROM budgets"
        )->fetch();
        $row['utilization_pct'] = ((float)$row['total_budget'] > 0)
            ? round((float)$row['total_released'] / (float)$row['total_budget'] * 100, 1)
            : 0.0;
        return $row;
    }

    /**
     * Create or update a budget row (upsert keyed by id). Validates everything server-side —
     * the client-side checks in the view are a convenience only, never the source of truth.
     * Returns ['ok'=>true] or ['ok'=>false,'error'=>string].
     */
    public static function save(array $data, ?int $id = null): array
    {
        $programId = (int)($data['program_id'] ?? 0);
        $year      = trim((string)($data['academic_year'] ?? ''));
        $total     = $data['total_budget'] ?? '';
        $allocated = $data['allocated'] ?? '0';
        $notes     = trim((string)($data['notes'] ?? '')) ?: null;

        if ($programId <= 0)        return ['ok' => false, 'error' => 'Scholarship program is required.'];
        if ($year === '')           return ['ok' => false, 'error' => 'Academic year is required.'];
        if (!in_array($year, self::academicYears(), true)) {
            return ['ok' => false, 'error' => 'Choose an academic year from the list (years come from existing programs and budgets).'];
        }
        if (!is_numeric($total) || (float)$total < 0)     return ['ok' => false, 'error' => 'Total budget must be a number ≥ 0.'];
        if (!is_numeric($allocated) || (float)$allocated < 0) return ['ok' => false, 'error' => 'Allocated amount must be a number ≥ 0.'];
        if ((float)$allocated > (float)$total) return ['ok' => false, 'error' => 'Allocated amount cannot exceed the total budget.'];

        $pdo = Database::conn();

        // Refuse a total below what has actually been released for this program + year. Measured from the
        // scholarship_releases rows (source of truth), so releases recorded before the budget existed count too.
        $currentReleased = self::actualReleased($programId, $year);
        if ((float)$total < $currentReleased) {
            return ['ok' => false, 'error' => 'Total budget cannot be less than the amount already released (₱' . number_format($currentReleased, 2) . ').'];
        }

        try {
            if ($id) {
                $stmt = $pdo->prepare(
                    "UPDATE budgets SET program_id=?, academic_year=?, total_budget=?, allocated=?, notes=? WHERE id=?"
                );
                $stmt->execute([$programId, $year, (float)$total, (float)$allocated, $notes, $id]);
                AuditLog::write('budget_updated', 'budgets', $id, "Budget updated for program #$programId ($year)");
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO budgets (program_id, academic_year, total_budget, allocated, notes) VALUES (?,?,?,?,?)"
                );
                $stmt->execute([$programId, $year, (float)$total, (float)$allocated, $notes]);
                $id = (int)$pdo->lastInsertId();
                AuditLog::write('budget_created', 'budgets', $id, "Budget created for program #$programId ($year)");
            }
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) { // duplicate unique key (program_id, academic_year)
                return ['ok' => false, 'error' => 'A budget for this program and academic year already exists.'];
            }
            throw $e;
        }

        // Pull in releases recorded before this budget row existed (or before its program/year was changed).
        self::syncReleased($programId, $year);

        return ['ok' => true, 'id' => $id];
    }

    /** Distinct academic years used by budgets or programs, newest first — options for academic_year <select>s. */
    public static function academicYears(): array
    {
        return Database::conn()->query(
            "SELECT academic_year FROM budgets
             UNION
             SELECT academic_year FROM scholarship_programs
             ORDER BY academic_year DESC"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Academic years that have a budget row for this program (a release can only be recorded against one of these). */
    public static function yearsForProgram(int $programId): array
    {
        $stmt = Database::conn()->prepare("SELECT academic_year FROM budgets WHERE program_id = ? ORDER BY academic_year DESC");
        $stmt->execute([$programId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function exists(int $programId, string $academicYear): bool
    {
        $stmt = Database::conn()->prepare("SELECT 1 FROM budgets WHERE program_id = ? AND academic_year = ?");
        $stmt->execute([$programId, $academicYear]);
        return (bool)$stmt->fetchColumn();
    }

    /** Sum of scholarship_releases for (program, academic_year) — the source of truth behind budgets.released. */
    private static function actualReleased(int $programId, string $academicYear): float
    {
        $stmt = Database::conn()->prepare(
            "SELECT COALESCE(SUM(sr.amount), 0)
             FROM scholarship_releases sr
             JOIN scholars s ON s.id = sr.scholar_id
             WHERE s.program_id = ? AND sr.academic_year = ?"
        );
        $stmt->execute([$programId, $academicYear]);
        return (float)$stmt->fetchColumn();
    }

    public static function delete(int $id): void
    {
        $b = self::find($id);
        if (!$b) return;
        Database::conn()->prepare("DELETE FROM budgets WHERE id = ?")->execute([$id]);
        AuditLog::write('budget_deleted', 'budgets', $id, "Budget deleted for {$b['program_name']} ({$b['academic_year']})", $b);
    }

    /**
     * Recompute budgets.released for (program_id, academic_year) from the actual
     * scholarship_releases rows (source of truth), then persist it. Call this any time a
     * release is created or deleted. No-op if no budget row exists for that program+year.
     */
    public static function syncReleased(int $programId, string $academicYear): void
    {
        $pdo = Database::conn();
        $actual = self::actualReleased($programId, $academicYear);

        $upd = $pdo->prepare("UPDATE budgets SET released = ? WHERE program_id = ? AND academic_year = ?");
        $upd->execute([$actual, $programId, $academicYear]);
        if ($upd->rowCount() > 0) {
            AuditLog::write('budget_release_synchronized', 'budgets', null,
                "Released recalculated for program #$programId ($academicYear): ₱" . number_format($actual, 2));
        }
    }

    /** program_id + academic_year for a given scholar (used by Scholar::recordRelease/deleteRelease to know which budget row to sync). */
    public static function programAndYearForScholar(int $scholarId): ?array
    {
        $stmt = Database::conn()->prepare("SELECT program_id FROM scholars WHERE id = ?");
        $stmt->execute([$scholarId]);
        $programId = $stmt->fetchColumn();
        return $programId ? ['program_id' => (int)$programId] : null;
    }

    /** Would this release push released past the budgeted total for its program+academic_year? (Scholar::recordRelease refuses releases with no budget row before calling this.) */
    public static function wouldExceedBudget(int $programId, string $academicYear, float $additionalAmount): bool
    {
        $stmt = Database::conn()->prepare("SELECT total_budget, released FROM budgets WHERE program_id = ? AND academic_year = ?");
        $stmt->execute([$programId, $academicYear]);
        $row = $stmt->fetch();
        if (!$row) return false; // no budget defined for this program/year — nothing to enforce against
        return ((float)$row['released'] + $additionalAmount) > (float)$row['total_budget'];
    }
}
