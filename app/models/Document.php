<?php
declare(strict_types=1);

/**
 * Application documents (A2) plus the private file storage shared with semester documents (A8/A9):
 * validated uploads with random names, path resolution restricted to the private stores, and streaming.
 */
final class Document
{
    /**
     * The application's documents: for each type, the NEWEST file of each side (older versions stay in the table as
     * history), as one flat list in DOCUMENT_TYPES order, Front before Back. See latestSides().
     */
    public static function byApplication(int $applicationId): array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY id");
        $stmt->execute([$applicationId]);
        $byType = [];
        foreach ($stmt->fetchAll() as $d) $byType[$d['document_type']][] = $d;
        $out = [];
        foreach (DOCUMENT_TYPES as $type) {
            foreach (self::latestSides($byType[$type] ?? []) as $d) $out[] = $d;
        }
        return $out;
    }

    /* ---------------- front and back (documents.side / term_documents.side) ---------------- */

    /** The array key of a side: '' for one file with the whole document (side NULL), else 'Front' / 'Back'. */
    public static function sideKey(?string $side): string
    {
        return $side ?? '';
    }

    /** Rows of documents and/or term_documents grouped as type => [side key => row]. */
    public static function bySide(array $rows): array
    {
        $out = [];
        foreach ($rows as $d) $out[$d['document_type'] ?? $d['type']][self::sideKey($d['side'] ?? null)] = $d;
        return $out;
    }

    /**
     * The newest file of each side among one type's versions (given oldest first): side key => row, ordered '', Front,
     * Back. A one-file version replaces both sides; a Front or Back replaces that side and any one-file version.
     */
    public static function latestSides(array $rows): array
    {
        $set = [];
        foreach ($rows as $d) {
            $key = self::sideKey($d['side'] ?? null);
            if ($key === '') $set = [];
            else unset($set['']);
            $set[$key] = $d;
        }
        return self::ordered($set);
    }

    private static function ordered(array $set): array
    {
        $order = ['' => 0, 'Front' => 1, 'Back' => 2];
        uksort($set, static fn($a, $b) => $order[$a] <=> $order[$b]);
        return $set;
    }

    /** The sides a document must have: DOCUMENT_SIDES (a Front and a Back picture, TWO_SIDED_DOCUMENT_TYPES) or [null] (one file). */
    public static function sidesFor(string $type): array
    {
        return in_array($type, TWO_SIDED_DOCUMENT_TYPES, true) ? DOCUMENT_SIDES : [null];
    }

    /**
     * Does $set (one type: side key => row, from latestSides/bySide) hold the $needed sides (sidesFor)? Only a stored
     * file whose status is not in $unusable counts. One file with the whole document covers both sides; a one-file
     * document is covered by any usable file (e.g. a Form 138 front uploaded before the year level changed).
     */
    public static function covers(array $set, array $needed, array $unusable = ['Invalid']): bool
    {
        $usable = array_filter($set, static fn($d) => $d['file_path'] !== '' && !in_array($d['status'], $unusable, true));
        if (isset($usable[''])) return true;
        if ($needed === [null]) return $usable !== [];
        foreach ($needed as $side) {
            if (!isset($usable[$side])) return false;
        }
        return true;
    }

    /**
     * A2 upload — the ONE place application documents are written. Never overwrites a file: a re-upload adds a new
     * version row and supersedes the older one(s) of that type and side for the applicant (Document::supersede), keeping
     * the file. Only an empty placeholder row (no file yet) is filled in place. $side: 'Front'/'Back' for a two-sided
     * document, null for one file (sidesFor). The caller checks ownership and editability.
     * Returns ['ok'=>true,'id','original','size','mime'] or ['ok'=>false,'error'].
     */
    public static function storeApplicationDocument(array $app, string $type, array $file, int $userId, ?string $side = null): array
    {
        if (!in_array($type, DOCUMENT_TYPES, true)) return ['ok' => false, 'error' => 'Invalid document type.'];
        if (!in_array($side, self::sidesFor($type), true)) {
            return ['ok' => false, 'error' => $side === null ? "Upload the front and the back of your $type separately." : 'Invalid side.'];
        }
        $stored = DocumentStorage::store($file, DOCUMENT_PATH, 'storage/documents/', (string)(int)$app['id']);
        if (!$stored['ok']) return $stored;

        $set = self::bySide(self::byApplication((int)$app['id']))[$type] ?? [];
        $placeholder = isset($set['']) && $set['']['file_path'] === '' ? $set[''] : null;
        $hadFile = (bool)array_filter($set, static fn($d) => $d['file_path'] !== '');
        $what = $type . ($side ? ' (' . strtolower($side) . ')' : '');
        $pdo = Database::conn();
        try {
            $pdo->beginTransaction();
            if ($placeholder) {
                $pdo->prepare("UPDATE documents SET file_path = ?, original_filename = ?, file_size = ?, mime_type = ?, status = 'Submitted',
                               uploaded_at = NOW(), remarks = NULL, is_current = 1, superseded_at = NULL, side = ? WHERE id = ?")
                    ->execute([$stored['rel'], $stored['original'], $stored['size'], $stored['mime'], $side, (int)$placeholder['id']]);
                $docId = (int)$placeholder['id'];
            } else {
                $pdo->prepare("INSERT INTO documents (application_id, document_type, side, file_path, original_filename, file_size, mime_type, status, uploaded_at, is_current)
                               VALUES (?, ?, ?, ?, ?, ?, ?, 'Submitted', NOW(), 1)")
                    ->execute([(int)$app['id'], $type, $side, $stored['rel'], $stored['original'], $stored['size'], $stored['mime']]);
                $docId = (int)$pdo->lastInsertId();
            }
            AuditLog::write('document_uploaded', 'documents', $docId,
                "Uploaded $what for application {$app['application_code']}" . ($hadFile ? ' (new version; the previous file is kept)' : ''),
                null, ['by' => $userId]);
            self::supersede((int)$app['applicant_id'], $type, 'documents', $docId, $side);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            DocumentStorage::deleteFile($stored['rel']);   // nothing references the new file
            throw $e;
        }
        return ['ok' => true, 'id' => $docId, 'original' => $stored['original'], 'size' => $stored['size'], 'mime' => $stored['mime']];
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

    /** Names of the required document types without a complete upload yet (a two-sided one needs the front AND the back). */
    public static function missingTypes(int $applicationId): array
    {
        $sets = self::bySide(self::byApplication($applicationId));
        return array_values(array_filter(DOCUMENT_TYPES, static fn($type) => !self::covers($sets[$type] ?? [], self::sidesFor($type))));
    }

    /* ---------------- versions across documents + term_documents (docs/CHANGE_PLAN_2.md §5) ---------------- */

    /**
     * A new upload of $type is the applicant's current one: mark every other current row of that type for that
     * applicant — in BOTH tables — as superseded (is_current = 0, superseded_at = now). Files are kept.
     * $side: a new Front (or Back) supersedes only the current Front (or Back) and any one-file version; a new one-file
     * version (null) supersedes every side. $keepTable/$keepId is the new row itself. Each change is audit-logged.
     * Returns how many rows were superseded.
     */
    public static function supersede(int $applicantId, string $type, string $keepTable, int $keepId, ?string $side = null): int
    {
        $pdo = Database::conn();
        $now = date('Y-m-d H:i:s');
        $found = [];
        $sideSql = $side === null ? '' : ' AND (x.side IS NULL OR x.side = ?)';
        $params = $side === null ? [$applicantId, $type] : [$applicantId, $type, $side];
        $stmt = $pdo->prepare(
            "SELECT x.id FROM documents x JOIN applications a ON a.id = x.application_id
             WHERE a.applicant_id = ? AND x.document_type = ? AND x.is_current = 1 AND x.file_path <> ''$sideSql"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) $found[] = ['documents', (int)$id];
        $stmt = $pdo->prepare(
            "SELECT x.id FROM term_documents x
             JOIN scholar_terms t ON t.id = x.term_id JOIN scholars s ON s.id = t.scholar_id
             WHERE s.applicant_id = ? AND x.type = ? AND x.is_current = 1$sideSql"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) $found[] = ['term_documents', (int)$id];

        $what = $type . ($side ? ' (' . strtolower($side) . ')' : '');
        $count = 0;
        foreach ($found as [$table, $id]) {
            if ($table === $keepTable && $id === $keepId) continue;
            $pdo->prepare("UPDATE `$table` SET is_current = 0, superseded_at = ? WHERE id = ? AND is_current = 1")->execute([$now, $id]);
            AuditLog::write('document_superseded', $table, $id,
                "$what superseded by a newer upload ($keepTable #$keepId)", ['is_current' => 1], ['is_current' => 0, 'superseded_at' => $now]);
            $count++;
        }
        return $count;
    }

    /**
     * Every version of one document type for an applicant, from the application(s) and from claims/renewals,
     * newest first. Each row: source_table, id, type, side (null = one file), original_filename, mime_type, uploaded_at,
     * status, is_current, superseded_at, uploaded_during (Application | Claim | Renewal), context (application code or
     * semester), academic_year (the A.Y. it was uploaded for: the application's program, the semester claimed, or the
     * A.Y. renewed into), term_id + purpose (claim/renewal uploads), admin_url and applicant_url (where each opens it).
     */
    public static function history(int $applicantId, string $type): array
    {
        return self::versions($applicantId, $type, false);
    }

    /**
     * The applicant's current version of a document type across both tables (the is_current rows): side key ('' = one
     * file, 'Front', 'Back') => row, the newest per side. Empty when there is none.
     */
    public static function current(int $applicantId, string $type): array
    {
        $set = [];
        foreach (self::versions($applicantId, $type, true) as $r) $set[self::sideKey($r['side'])] ??= $r;   // newest first
        return self::ordered($set);
    }

    /**
     * The School ID "on file" for an academic year (decision D4: the School ID is asked once per A.Y.): the applicant's
     * current School ID when it is complete (Front and Back, or one file with both), no part of it is Invalid or waiting
     * for a re-upload, and every part was uploaded for that A.Y. (application, renewal or another claim of that year).
     * Uploads made for the claim of $exceptTermId don't count — that claim asks for the School ID itself, so it can be
     * re-uploaded there. Returns side key => row (as current()), or null.
     */
    public static function schoolIdOnFile(int $applicantId, string $academicYear, ?int $exceptTermId = null): ?array
    {
        $set = self::current($applicantId, 'School ID');
        foreach ($set as $r) {
            if ($r['academic_year'] !== $academicYear) return null;
            if ($exceptTermId !== null && $r['purpose'] === 'claim' && (int)$r['term_id'] === $exceptTermId) return null;
        }
        return $set && self::covers($set, self::sidesFor('School ID'), ['Invalid', 'Resubmit']) ? $set : null;
    }

    private static function versions(int $applicantId, string $type, bool $currentOnly): array
    {
        // The A.Y. a renewal upload is for: the renewal's target, or (before it is submitted) the semester after its own.
        $nextYear = "CONCAT(CAST(SUBSTRING_INDEX(t.academic_year, '-', 1) AS UNSIGNED) + 1, '-', CAST(SUBSTRING_INDEX(t.academic_year, '-', -1) AS UNSIGNED) + 1)";
        $stmt = Database::conn()->prepare(
            "SELECT * FROM (
                SELECT 'documents' AS source_table, d.id, CAST(d.document_type AS CHAR(40)) AS type, CAST(d.side AS CHAR(5)) AS side,
                       d.original_filename, d.file_path,
                       d.mime_type, d.uploaded_at, CAST(d.status AS CHAR(20)) AS status, d.is_current, d.superseded_at, d.invalid_reason,
                       'Application' AS uploaded_during, a.application_code AS context,
                       CAST(sp.academic_year AS CHAR(20)) AS academic_year, NULL AS term_id, NULL AS purpose
                FROM documents d JOIN applications a ON a.id = d.application_id
                JOIN scholarship_programs sp ON sp.id = a.program_id
                WHERE a.applicant_id = :applicant_doc AND d.document_type = :type_doc AND d.file_path <> ''
                UNION ALL
                SELECT 'term_documents', td.id, CAST(td.type AS CHAR(40)), CAST(td.side AS CHAR(5)), td.original_filename, td.file_path,
                       td.mime_type, td.uploaded_at, CAST(td.status AS CHAR(20)), td.is_current, td.superseded_at, td.invalid_reason,
                       IF(td.purpose = 'claim', 'Claim', 'Renewal'),
                       IF(td.purpose = 'claim', CONCAT(t.academic_year, ' ', t.semester),
                          CONCAT('for ', COALESCE(CONCAT(rr.academic_year, ' ', rr.semester), 'the next semester'))),
                       CAST(IF(td.purpose = 'claim', t.academic_year,
                               COALESCE(rr.academic_year, IF(t.semester = '2nd Semester', $nextYear, t.academic_year))) AS CHAR(20)),
                       td.term_id, CAST(td.purpose AS CHAR(10))
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
            $r['applicant_url'] = url(($r['source_table'] === 'documents' ? 'applicant/documents/view&id=' : 'applicant/term-documents/view&id=') . (int)$r['id']);
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
