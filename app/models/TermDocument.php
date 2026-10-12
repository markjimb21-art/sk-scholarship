<?php
declare(strict_types=1);

/**
 * Claim and renewal documents of a semester (term_documents): School ID + Grades for a claim (A8), Enrollment Form +
 * School ID for a renewal (A9). Every upload is a new version; the newest of each type is the one that counts.
 */
final class TermDocument
{
    /**
     * The documents submitted for one purpose of a term, keyed by type: the NEWEST version of each type (older
     * re-uploads stay in the table as history). Not filtered on is_current: a claim keeps showing what was submitted
     * for it even after a later renewal upload became the applicant's current School ID.
     */
    public static function forTerm(int $termId, string $purpose): array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM term_documents WHERE term_id = ? AND purpose = ? ORDER BY id");
        $stmt->execute([$termId, $purpose]);
        $out = [];
        foreach ($stmt->fetchAll() as $d) $out[$d['type']] = $d;   // later id wins
        return $out;
    }

    /** Set status/remarks on the newest version of the given types (default: all) of a term + purpose. */
    public static function mark(int $termId, string $purpose, string $status, ?string $remarks, ?array $types = null): void
    {
        $stmt = Database::conn()->prepare("UPDATE term_documents SET status = ?, remarks = ? WHERE id = ?");
        foreach (self::forTerm($termId, $purpose) as $type => $d) {
            if ($types === null || in_array($type, $types, true)) $stmt->execute([$status, $remarks, (int)$d['id']]);
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

    /** Both required documents for $purpose are uploaded and none is waiting to be re-uploaded (Resubmit) or Invalid. */
    public static function ready(int $termId, string $purpose): bool
    {
        $docs = self::forTerm($termId, $purpose);
        foreach (TERM_DOCUMENT_TYPES[$purpose] as $type) {
            if (!isset($docs[$type]) || in_array($docs[$type]['status'], ['Resubmit', 'Invalid'], true)) return false;
        }
        return true;
    }

    /** B7/B9 "the file is invalid": mark the newest version of each type Invalid (row kept, file removed) with the remark. */
    public static function invalidateNewest(int $termId, string $purpose, array $types, string $reason, int $adminId): void
    {
        $types = array_values(array_intersect($types, TERM_DOCUMENT_TYPES[$purpose] ?? [])) ?: (TERM_DOCUMENT_TYPES[$purpose] ?? []);
        foreach (self::forTerm($termId, $purpose) as $type => $d) {
            if (in_array($type, $types, true) && $d['status'] !== 'Invalid') Document::markInvalid('term_documents', (int)$d['id'], $reason, $adminId);
        }
    }

    /**
     * Store one claim or renewal document (A8 / A9) as a NEW version: the previous file is kept, and every older
     * version of that type for the applicant (application, claim or renewal) is superseded (Document::supersede).
     * The caller checks ownership; this checks the process rules. Returns ['ok'=>true] or ['ok'=>false,'error'].
     */
    public static function upload(array $term, string $purpose, string $type, array $file): array
    {
        if (!in_array($type, TERM_DOCUMENT_TYPES[$purpose] ?? [], true)) return ['ok' => false, 'error' => 'Invalid document type.'];
        $allowed = $purpose === 'claim' ? Term::claimEditable($term) : Renewal::editable($term);
        if (!$allowed) return ['ok' => false, 'error' => Term::closedReason($term, $purpose) ?? 'These documents can’t be changed right now.'];

        $stored = DocumentStorage::store($file, TERM_DOCUMENT_PATH, 'storage/term-documents/', (string)(int)$term['id']);
        if (!$stored['ok']) return $stored;

        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO term_documents (term_id, purpose, type, file_path, original_filename, file_size, mime_type, uploaded_at, is_current)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
            )->execute([(int)$term['id'], $purpose, $type, $stored['rel'], $stored['original'], $stored['size'], $stored['mime'], date('Y-m-d H:i:s')]);
            $docId = (int)$pdo->lastInsertId();
            AuditLog::write('term_document_uploaded', 'term_documents', $docId,
                "Uploaded $type ($purpose) for {$term['academic_year']} {$term['semester']}");
            Document::supersede((int)$term['applicant_id'], $type, 'term_documents', $docId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            DocumentStorage::deleteFile($stored['rel']);
            throw $e;
        }
        return ['ok' => true];
    }
}
