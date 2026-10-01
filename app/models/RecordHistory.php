<?php
declare(strict_types=1);

/**
 * Change history of key applicant fields (HISTORY_FIELDS): every change keeps the old and the new value, so correct
 * earlier data is never lost when an applicant edits a section (docs/PROCESS.md "Data retention").
 */
final class RecordHistory
{
    /**
     * Compare a row before/after a save and record each changed HISTORY_FIELDS column. A first fill-in (old row missing,
     * or old value empty) is not a change and isn't recorded. Returns the number of rows written.
     */
    public static function record(string $table, int $recordId, int $applicantId, ?array $old, array $new, int $userId): int
    {
        $fields = HISTORY_FIELDS[$table] ?? [];
        if (!$old || !$fields) return 0;
        $norm = static fn($v): string => is_numeric($v) ? rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.') : trim((string)$v);
        $stmt = Database::conn()->prepare(
            "INSERT INTO record_history (table_name, record_id, applicant_id, field, old_value, new_value, changed_by, changed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $n = 0;
        foreach (array_keys($fields) as $f) {
            if (!array_key_exists($f, $new)) continue;
            $before = $old[$f] ?? null;
            if ($before === null || $before === '') continue;
            if ($norm($before) === $norm($new[$f])) continue;
            $after = $new[$f];
            // Store a number with the same decimals as the stored value (1.80, not 1.8) so before/after read alike.
            if ($after !== null && is_numeric($after) && preg_match('/\.(\d+)$/', (string)$before, $m)) $after = number_format((float)$after, strlen($m[1]), '.', '');
            $stmt->execute([$table, $recordId, $applicantId, $f, (string)$before, $after === null ? null : (string)$after, $userId ?: null, date('Y-m-d H:i:s')]);
            $n++;
        }
        return $n;
    }

    /** Every recorded change of an applicant, newest first, with the field label and who made it. */
    public static function forApplicant(int $applicantId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT h.*, u.full_name AS changed_by_name FROM record_history h LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.applicant_id = ? ORDER BY h.changed_at DESC, h.id DESC LIMIT 200"
        );
        $stmt->execute([$applicantId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) $r['label'] = HISTORY_FIELDS[$r['table_name']][$r['field']] ?? $r['field'];
        unset($r);
        return $rows;
    }
}
