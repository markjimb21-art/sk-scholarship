<?php
declare(strict_types=1);

/**
 * Academic years (Releasing of budget → Academic years). The ONLY source of year lists: budgets, claim/renewal windows,
 * the releases filter, analytics and reports all list these. Exactly one is current: new scholars start in its
 * 1st Semester (Application::approve). A program has no academic year (docs/CHANGE_PLAN_4.md).
 */
final class AcademicYear
{
    /** Every year, newest first: id, label, is_current, created_by, created_at. */
    public static function all(): array
    {
        return Database::conn()->query("SELECT * FROM academic_years ORDER BY label DESC")->fetchAll();
    }

    /** Labels newest first, for every academic-year <select>. */
    public static function options(): array
    {
        return Database::conn()->query("SELECT label FROM academic_years ORDER BY label DESC")->fetchAll(PDO::FETCH_COLUMN);
    }

    /** The current year row, or null when there is none yet. */
    public static function current(): ?array
    {
        return Database::conn()->query("SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch() ?: null;
    }

    /** The current year's label, or null. */
    public static function currentLabel(): ?string
    {
        return self::current()['label'] ?? null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM academic_years WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function exists(string $label): bool
    {
        $stmt = Database::conn()->prepare("SELECT 1 FROM academic_years WHERE label = ? LIMIT 1");
        $stmt->execute([$label]);
        return (bool)$stmt->fetchColumn();
    }

    /** "Academic year X doesn't exist …" for the server checks of budgets, windows and renewals; null when it exists. */
    public static function missingError(string $label): ?string
    {
        return self::exists($label) ? null
            : "Academic year $label doesn't exist yet — create it under Releasing of budget → Academic years.";
    }

    /**
     * The academic year containing a date: from ACADEMIC_YEAR_START_MONTH (June) on it is that year's A.Y.
     * (2026-10-16 → 2026-2027), before it the one that began the year before (2027-02-01 → 2026-2027).
     */
    public static function labelFor(string $date): string
    {
        $y = (int)substr($date, 0, 4);
        if ((int)substr($date, 5, 2) < ACADEMIC_YEAR_START_MONTH) $y--;
        return $y . '-' . ($y + 1);
    }

    /** The year after the latest one, or today's year (labelFor) when there is none yet. */
    public static function suggestNext(): string
    {
        $latest = Database::conn()->query("SELECT MAX(label) FROM academic_years")->fetchColumn();
        if (!$latest) return self::labelFor(date('Y-m-d'));
        $y = (int)substr((string)$latest, 0, 4) + 1;
        return $y . '-' . ($y + 1);
    }

    /** Error message for a label that isn't YYYY-YYYY with the second year = the first + 1, else null. */
    public static function invalid(string $label): ?string
    {
        if (!preg_match('/^(\d{4})-(\d{4})$/', $label, $m) || (int)$m[2] !== (int)$m[1] + 1 || (int)$m[1] < 2000 || (int)$m[1] > 2099) {
            return 'An academic year looks like 2027-2028: two years in a row, from 2000 on.';
        }
        return null;
    }

    /**
     * Create a year. In one transaction: the row; it becomes current if asked or if no year is current yet; and, when
     * $withBudgets, one budget per program with Active scholars at the suggested total (Budget::suggestedTotal),
     * skipping programs that already have a budget for that year. Audit-logged (budgets through Budget::save).
     * Returns ['ok'=>true,'id','label','current'=>bool,'budgets'=>[['program_name','total']…]] or ['ok'=>false,'error'].
     */
    public static function create(string $label, bool $makeCurrent, bool $withBudgets, int $userId): array
    {
        $label = trim($label);
        if ($err = self::invalid($label)) return ['ok' => false, 'error' => $err];
        if (self::exists($label)) return ['ok' => false, 'error' => "Academic year $label already exists."];
        $makeCurrent = $makeCurrent || self::current() === null;
        $budgets = [];
        try {
            $id = Database::transaction(static function () use ($label, $makeCurrent, $withBudgets, $userId, &$budgets): int {
                $pdo = Database::conn();
                $pdo->prepare("INSERT INTO academic_years (label, created_by) VALUES (?, ?)")->execute([$label, $userId ?: null]);
                $id = (int)$pdo->lastInsertId();
                AuditLog::write('academic_year_created', 'academic_years', $id, "Academic year $label created", null, ['label' => $label, 'by' => $userId]);
                if ($makeCurrent) self::switchCurrent($id, $userId);
                if ($withBudgets) {
                    foreach (Program::options() as $p) {
                        if (Budget::forProgramYear((int)$p['id'], $label)) continue;
                        $s = Budget::suggestedTotal((int)$p['id']);   // null: no Active scholars (or no amount)
                        if (!$s) continue;
                        $r = Budget::save(['program_id' => (int)$p['id'], 'academic_year' => $label, 'total_budget' => $s['total']], null);
                        if (!$r['ok']) throw new DomainException($r['error']);
                        $budgets[] = ['program_name' => $p['program_name'], 'total' => $s['total']];
                    }
                }
                return $id;
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) return ['ok' => false, 'error' => "Academic year $label already exists."];
            throw $e;
        }
        return ['ok' => true, 'id' => $id, 'label' => $label, 'current' => $makeCurrent, 'budgets' => $budgets];
    }

    /** Make a year the current one (the previous current year stops being current). */
    public static function setCurrent(int $id, int $userId): array
    {
        $year = self::find($id);
        if (!$year) return ['ok' => false, 'error' => 'Academic year not found.'];
        if ((int)$year['is_current'] === 1) return ['ok' => true, 'label' => $year['label']];
        Database::transaction(static fn() => self::switchCurrent($id, $userId));
        return ['ok' => true, 'label' => $year['label']];
    }

    /** Inside a transaction: clear the current year first (the UNIQUE index allows only one), then set the new one. */
    private static function switchCurrent(int $id, int $userId): void
    {
        $pdo = Database::conn();
        $old = self::current();
        $pdo->exec("UPDATE academic_years SET is_current = 0 WHERE is_current = 1");
        $pdo->prepare("UPDATE academic_years SET is_current = 1 WHERE id = ?")->execute([$id]);
        $new = self::find($id);
        AuditLog::write('academic_year_current_changed', 'academic_years', $id,
            "Current academic year: {$new['label']}" . ($old ? " (was {$old['label']})" : ''),
            ['current' => $old['label'] ?? null], ['current' => $new['label'], 'by' => $userId]);
    }

    /** What uses a year: semesters, budgets, claim/renewal windows and renewal requests with that label. */
    public static function usage(string $label): array
    {
        $out = [];
        foreach (['scholar_terms' => 'semester(s)', 'budgets' => 'budget(s)', 'term_schedules' => 'claim/renewal window(s)', 'renewal_requests' => 'renewal request(s)'] as $table => $what) {
            $stmt = Database::conn()->prepare("SELECT COUNT(*) FROM `$table` WHERE academic_year = ?");
            $stmt->execute([$label]);
            $n = (int)$stmt->fetchColumn();
            if ($n) $out[$what] = $n;
        }
        return $out;
    }

    /** Why a year can't be deleted (current, or in use), or null when it can. */
    public static function deleteBlocker(array $year): ?string
    {
        if ((int)$year['is_current'] === 1) return 'It is the current academic year — make another one current first.';
        $usage = self::usage($year['label']);
        if ($usage) {
            return 'It is used by ' . implode(', ', array_map(static fn($n, $w) => "$n $w", $usage, array_keys($usage))) . '.';
        }
        return null;
    }

    /** Delete a year nothing uses and that isn't current (a label, not anyone's record). Audit-logged. */
    public static function delete(int $id, int $userId): array
    {
        $year = self::find($id);
        if (!$year) return ['ok' => false, 'error' => 'Academic year not found.'];
        if ($why = self::deleteBlocker($year)) return ['ok' => false, 'error' => "{$year['label']} can't be deleted. $why"];
        Database::conn()->prepare("DELETE FROM academic_years WHERE id = ?")->execute([$id]);
        AuditLog::write('academic_year_deleted', 'academic_years', $id, "Academic year {$year['label']} deleted", ['label' => $year['label']], ['by' => $userId]);
        return ['ok' => true, 'label' => $year['label']];
    }

    /**
     * Per program, what a year has: scholars (semesters with that label), the budget, and the four windows —
     * claim of the 1st / 2nd Semester and renewal into the 1st / 2nd Semester ('schedule' row + 'state', or null).
     */
    public static function overview(string $label): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT s.program_id, COUNT(DISTINCT t.scholar_id) FROM scholar_terms t JOIN scholars s ON s.id = t.scholar_id
             WHERE t.academic_year = ? GROUP BY s.program_id"
        );
        $stmt->execute([$label]);
        $scholars = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $rows = [];
        foreach (Program::options() as $p) {
            $pid = (int)$p['id'];
            $windows = [];
            foreach (['release', 'renewal'] as $purpose) {
                foreach (SEMESTERS as $sem) {
                    $w = TermSchedule::forSemester($pid, $label, $sem, $purpose);
                    $windows[$purpose][$sem] = $w ? ['schedule' => $w, 'state' => TermSchedule::state($w)] : null;
                }
            }
            $rows[] = ['program' => $p, 'scholars' => (int)($scholars[$pid] ?? 0), 'budget' => Budget::forProgramYear($pid, $label), 'windows' => $windows];
        }
        return $rows;
    }
}
