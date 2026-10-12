<?php
declare(strict_types=1);

/**
 * Application documents (A2) plus the private file storage shared with semester documents (A8/A9):
 * validated uploads with random names, path resolution restricted to the private stores, and streaming.
 */
final class Document
{
    /** The application's documents: the NEWEST version of each type (older versions stay in the table as history). */
    public static function byApplication(int $applicationId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT d.* FROM documents d
             WHERE d.application_id = ?
               AND d.id = (SELECT MAX(d2.id) FROM documents d2 WHERE d2.application_id = d.application_id AND d2.document_type = d.document_type)
             ORDER BY FIELD(d.document_type, 'Certificate of Residency','Form 138 / Grades','Enrollment Form','School ID','2x2 Photo')"
        );
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll();
    }

    /**
     * A2 upload — the ONE place application documents are written. Never overwrites a file: a re-upload adds a new
     * version row and supersedes the older one(s) of that type for the applicant (Document::supersede), keeping the file.
     * Only an empty placeholder row (no file yet) is filled in place. The caller checks ownership and editability.
     * Returns ['ok'=>true,'id','original','size'] or ['ok'=>false,'error'].
     */
    public static function storeApplicationDocument(array $app, string $type, array $file, int $userId): array
    {
        if (!in_array($type, DOCUMENT_TYPES, true)) return ['ok' => false, 'error' => 'Invalid document type.'];
        $stored = DocumentStorage::store($file, DOCUMENT_PATH, 'storage/documents/', (string)(int)$app['id']);
        if (!$stored['ok']) return $stored;

        $newest = null;
        foreach (self::byApplication((int)$app['id']) as $d) if ($d['document_type'] === $type) $newest = $d;
        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            if ($newest && $newest['file_path'] === '') {
                $pdo->prepare("UPDATE documents SET file_path = ?, original_filename = ?, file_size = ?, mime_type = ?, status = 'Submitted',
                               uploaded_at = NOW(), remarks = NULL, is_current = 1, superseded_at = NULL WHERE id = ?")
                    ->execute([$stored['rel'], $stored['original'], $stored['size'], $stored['mime'], (int)$newest['id']]);
                $docId = (int)$newest['id'];
            } else {
                $pdo->prepare("INSERT INTO documents (application_id, document_type, file_path, original_filename, file_size, mime_type, status, uploaded_at, is_current)
                               VALUES (?, ?, ?, ?, ?, ?, 'Submitted', NOW(), 1)")
                    ->execute([(int)$app['id'], $type, $stored['rel'], $stored['original'], $stored['size'], $stored['mime']]);
                $docId = (int)$pdo->lastInsertId();
            }
            AuditLog::write('document_uploaded', 'documents', $docId,
                "Uploaded $type for application {$app['application_code']}" . ($newest && $newest['file_path'] !== '' ? ' (new version; the previous file is kept)' : ''),
                null, ['by' => $userId]);
            self::supersede((int)$app['applicant_id'], $type, 'documents', $docId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            DocumentStorage::deleteFile($stored['rel']);   // nothing references the new file
            throw $e;
        }
        return ['ok' => true, 'id' => $docId, 'original' => $stored['original'], 'size' => $stored['size']];
    }

    /**
     * Flag a document (application or claim/renewal) as INVALID with a reason: the row is kept (status Invalid, reason,
     * who, when; no longer current) and only the file is removed. This is the only way a document file is deleted.
     */
    public static function markInvalid(string $table, int $id, string $reason, int $actorId): array
    {
        if (!in_array($table, ['documents', 'term_documents'], true)) return ['ok' => false, 'error' => 'Unknown document table.'];
        $reason = trim($reason);
        if ($reason === '') return ['ok' => false, 'error' => 'Write why the document is invalid.'];
        $stmt = Database::conn()->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        if (!$doc || $doc['file_path'] === '') return ['ok' => false, 'error' => 'Document not found.'];
        if ($doc['status'] === 'Invalid') return ['ok' => false, 'error' => 'This document is already marked invalid.'];
        $now = date('Y-m-d H:i:s');
        Database::conn()->prepare(
            "UPDATE `$table` SET status = 'Invalid', invalid_reason = ?, invalidated_by = ?, invalidated_at = ?, file_removed_at = ?,
                    is_current = 0, superseded_at = COALESCE(superseded_at, ?) WHERE id = ? AND status <> 'Invalid'"
        )->execute([mb_substr($reason, 0, 1000), $actorId ?: null, $now, $now, $now, $id]);
        DocumentStorage::deleteFile($doc['file_path']);
        $type = $doc['document_type'] ?? $doc['type'];
        AuditLog::write('document_invalidated', $table, $id, "$type marked invalid and its file removed: $reason",
            ['status' => $doc['status']], ['status' => 'Invalid', 'by' => $actorId]);
        return ['ok' => true];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Names of the required document types that have no uploaded file yet. */
    public static function missingTypes(int $applicationId): array
    {
        $uploaded = [];
        foreach (self::byApplication($applicationId) as $d) {
            if ($d['file_path'] !== '' && $d['status'] !== 'Invalid') $uploaded[] = $d['document_type'];
        }
        return array_values(array_diff(DOCUMENT_TYPES, $uploaded));
    }

    /* ---------------- versions across documents + term_documents (docs/CHANGE_PLAN_2.md §5) ---------------- */

    /**
     * A new upload of $type is the applicant's current one: mark every other current row of that type for that
     * applicant — in BOTH tables — as superseded (is_current = 0, superseded_at = now). Files are kept.
     * $keepTable/$keepId is the new row itself. Each change is audit-logged. Returns how many rows were superseded.
     */
    public static function supersede(int $applicantId, string $type, string $keepTable, int $keepId): int
    {
        $pdo = Database::conn();
        $now = date('Y-m-d H:i:s');
        $found = [];
        $stmt = $pdo->prepare(
            "SELECT d.id FROM documents d JOIN applications a ON a.id = d.application_id
             WHERE a.applicant_id = ? AND d.document_type = ? AND d.is_current = 1 AND d.file_path <> ''"
        );
        $stmt->execute([$applicantId, $type]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) $found[] = ['documents', (int)$id];
        $stmt = $pdo->prepare(
            "SELECT td.id FROM term_documents td
             JOIN scholar_terms t ON t.id = td.term_id JOIN scholars s ON s.id = t.scholar_id
             WHERE s.applicant_id = ? AND td.type = ? AND td.is_current = 1"
        );
        $stmt->execute([$applicantId, $type]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) $found[] = ['term_documents', (int)$id];

        $count = 0;
        foreach ($found as [$table, $id]) {
            if ($table === $keepTable && $id === $keepId) continue;
            $pdo->prepare("UPDATE `$table` SET is_current = 0, superseded_at = ? WHERE id = ? AND is_current = 1")->execute([$now, $id]);
            AuditLog::write('document_superseded', $table, $id,
                "$type superseded by a newer upload ($keepTable #$keepId)", ['is_current' => 1], ['is_current' => 0, 'superseded_at' => $now]);
            $count++;
        }
        return $count;
    }

    /**
     * Every version of one document type for an applicant, from the application(s) and from claims/renewals,
     * newest first. Each row: source_table, id, type, original_filename, mime_type, uploaded_at, status, is_current,
     * superseded_at, uploaded_during (Application | Claim | Renewal), context (application code or semester),
     * admin_url (where an admin opens it).
     */
    public static function history(int $applicantId, string $type): array
    {
        return self::versions($applicantId, $type, false);
    }

    /** The applicant's current version of a document type (newest is_current row across both tables), or null. */
    public static function current(int $applicantId, string $type): ?array
    {
        return self::versions($applicantId, $type, true)[0] ?? null;
    }

    private static function versions(int $applicantId, string $type, bool $currentOnly): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM (
                SELECT 'documents' AS source_table, d.id, CAST(d.document_type AS CHAR(40)) AS type, d.original_filename,
                       d.mime_type, d.uploaded_at, CAST(d.status AS CHAR(20)) AS status, d.is_current, d.superseded_at, d.invalid_reason,
                       'Application' AS uploaded_during, a.application_code AS context
                FROM documents d JOIN applications a ON a.id = d.application_id
                WHERE a.applicant_id = :applicant_doc AND d.document_type = :type_doc AND d.file_path <> ''
                UNION ALL
                SELECT 'term_documents', td.id, CAST(td.type AS CHAR(40)), td.original_filename,
                       td.mime_type, td.uploaded_at, CAST(td.status AS CHAR(20)), td.is_current, td.superseded_at, td.invalid_reason,
                       IF(td.purpose = 'claim', 'Claim', 'Renewal'),
                       IF(td.purpose = 'claim', CONCAT(t.academic_year, ' ', t.semester),
                          CONCAT('for ', COALESCE(CONCAT(rr.academic_year, ' ', rr.semester), 'the next semester')))
                FROM term_documents td
                JOIN scholar_terms t ON t.id = td.term_id
                JOIN scholars s ON s.id = t.scholar_id
                LEFT JOIN renewal_requests rr ON rr.from_term_id = t.id AND td.purpose = 'renewal'
                WHERE s.applicant_id = :applicant_term AND td.type = :type_term
             ) v " . ($currentOnly ? 'WHERE v.is_current = 1 ' : '') . "
             ORDER BY v.uploaded_at DESC, v.source_table DESC, v.id DESC"
        );
        $stmt->execute(['applicant_doc' => $applicantId, 'type_doc' => $type, 'applicant_term' => $applicantId, 'type_term' => $type]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['admin_url'] = url(($r['source_table'] === 'documents' ? 'admin/documents/view&id=' : 'admin/term-documents/view&id=') . (int)$r['id']);
        }
        unset($r);
        return $rows;
    }

    /** Screening qualified the application: every uploaded document counts as verified. */
    public static function markAllVerified(int $applicationId): void
    {
        // Only the newest, valid version of each type — older versions keep their own status.
        $stmt = Database::conn()->prepare("UPDATE documents SET status = 'Verified' WHERE id = ?");
        foreach (self::byApplication($applicationId) as $d) {
            if ($d['file_path'] !== '' && $d['status'] !== 'Invalid') $stmt->execute([(int)$d['id']]);
        }
    }

    /** Stream an application document (row from `documents`). */
    public static function sendApplicationDocument(array $doc, bool $download = false): void
    {
        DocumentStorage::send((string)$doc['file_path'], $doc['original_filename'] ?? null, (string)$doc['document_type'],
            'documents', (int)$doc['id'], $download);
    }
}
