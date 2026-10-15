<?php
declare(strict_types=1);

/**
 * Claim and renewal documents of a semester (term_documents): School ID + Grades for a claim (A8), Enrollment Form +
 * School ID for a renewal (A9). The School ID is a Front and a Back picture (Document::sidesFor); the others are one
 * file. Every upload is a new version; the newest of each type and side is the one that counts.
 */
final class TermDocument
{
    /**
     * The documents submitted for one purpose of a term: type => [side key ('' = one file, 'Front', 'Back') => row], the
     * NEWEST version of each side (Document::latestSides; older re-uploads stay in the table as history). Not filtered on
     * is_current: a claim keeps showing what was submitted for it even after a later renewal upload became the
     * applicant's current School ID.
     */
    public static function forTerm(int $termId, string $purpose): array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM term_documents WHERE term_id = ? AND purpose = ? ORDER BY id");
        $stmt->execute([$termId, $purpose]);
        $byType = [];
        foreach ($stmt->fetchAll() as $d) $byType[$d['type']][] = $d;
        return array_map([Document::class, 'latestSides'], $byType);
    }

    /** Set status/remarks on the newest version (every side) of the given types (default: all) of a term + purpose. */
    public static function mark(int $termId, string $purpose, string $status, ?string $remarks, ?array $types = null): void
    {
        $stmt = Database::conn()->prepare("UPDATE term_documents SET status = ?, remarks = ? WHERE id = ?");
        foreach (self::forTerm($termId, $purpose) as $type => $set) {
            if ($types !== null && !in_array($type, $types, true)) continue;
            foreach ($set as $d) $stmt->execute([$status, $remarks, (int)$d['id']]);
        }
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT d.*, t.scholar_id, s.applicant_id FROM term_documents d
             JOIN scholar_terms t ON t.id = d.term_id JOIN scholars s ON s.id = t.scholar_id
             WHERE d.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Every required document for $purpose is uploaded — both sides of a two-sided one (one file with the whole document
     * also counts) — and none is waiting to be re-uploaded (Resubmit) or Invalid.
     */
    public static function ready(int $termId, string $purpose): bool
    {
        $docs = self::forTerm($termId, $purpose);
        foreach (TERM_DOCUMENT_TYPES[$purpose] as $type) {
            if (!Document::covers($docs[$type] ?? [], Document::sidesFor($type), ['Resubmit', 'Invalid'])) return false;
        }
        return true;
    }

    /** B7/B9 "the file is invalid": mark the newest version of each type (every side) Invalid (row kept, file removed). */
    public static function invalidateNewest(int $termId, string $purpose, array $types, string $reason, int $adminId): void
    {
        $types = array_values(array_intersect($types, TERM_DOCUMENT_TYPES[$purpose] ?? [])) ?: (TERM_DOCUMENT_TYPES[$purpose] ?? []);
        foreach (self::forTerm($termId, $purpose) as $type => $set) {
            if (!in_array($type, $types, true)) continue;
            foreach ($set as $d) {
                if ($d['status'] !== 'Invalid') Document::markInvalid('term_documents', (int)$d['id'], $reason, $adminId);
            }
        }
    }

    /** What the scholar and staff see for a type, e.g. "College grades (Certificate of Grades)" for Grades. */
    public static function label(string $type): string
    {
        return TERM_DOCUMENT_LABELS[$type] ?? $type;
    }

    /**
     * Store one claim or renewal document (A8 / A9) as a NEW version: the previous file is kept, and every older
     * version of that type and side for the applicant (application, claim or renewal) is superseded
     * (Document::supersede). $side: 'Front'/'Back' for the School ID, null for the others.
     * The caller checks ownership; this checks the process rules.
     * Returns ['ok'=>true,'id','original','size','mime'] or ['ok'=>false,'error'].
     */
    public static function upload(array $term, string $purpose, string $type, array $file, ?string $side = null): array
    {
        if (!in_array($type, TERM_DOCUMENT_TYPES[$purpose] ?? [], true)) return ['ok' => false, 'error' => 'Invalid document type.'];
        if (!in_array($side, Document::sidesFor($type), true)) {
            return ['ok' => false, 'error' => $side === null ? "Upload the front and the back of your $type separately." : 'Invalid side.'];
        }
        $allowed = $purpose === 'claim' ? Term::claimEditable($term) : Renewal::editable($term);
        if (!$allowed) return ['ok' => false, 'error' => Term::closedReason($term, $purpose) ?? 'These documents can’t be changed right now.'];

        $stored = DocumentStorage::store($file, TERM_DOCUMENT_PATH, 'storage/term-documents/', (string)(int)$term['id']);
        if (!$stored['ok']) return $stored;

        $what = $type . ($side ? ' (' . strtolower($side) . ')' : '');
        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO term_documents (term_id, purpose, type, side, file_path, original_filename, file_size, mime_type, uploaded_at, is_current)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
            )->execute([(int)$term['id'], $purpose, $type, $side, $stored['rel'], $stored['original'], $stored['size'], $stored['mime'], date('Y-m-d H:i:s')]);
            $docId = (int)$pdo->lastInsertId();
            AuditLog::write('term_document_uploaded', 'term_documents', $docId,
                "Uploaded $what ($purpose) for {$term['academic_year']} {$term['semester']}");
            Document::supersede((int)$term['applicant_id'], $type, 'term_documents', $docId, $side);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            DocumentStorage::deleteFile($stored['rel']);
            throw $e;
        }
        return ['ok' => true, 'id' => $docId, 'original' => $stored['original'], 'size' => $stored['size'], 'mime' => $stored['mime']];
    }
}
