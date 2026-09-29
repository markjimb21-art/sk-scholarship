<?php
declare(strict_types=1);

/**
 * Application documents (A2) plus the private file storage shared with semester documents (A8/A9):
 * validated uploads with random names, path resolution restricted to the private stores, and streaming.
 */
final class Document
{
    public static function byApplication(int $applicationId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM documents WHERE application_id = ?
             ORDER BY FIELD(document_type, 'Certificate of Residency','Form 138 / Grades','Enrollment Form','School ID','2x2 Photo')"
        );
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll();
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
            if ($d['file_path'] !== '') $uploaded[] = $d['document_type'];
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
                       d.mime_type, d.uploaded_at, CAST(d.status AS CHAR(20)) AS status, d.is_current, d.superseded_at,
                       'Application' AS uploaded_during, a.application_code AS context
                FROM documents d JOIN applications a ON a.id = d.application_id
                WHERE a.applicant_id = :applicant_doc AND d.document_type = :type_doc AND d.file_path <> ''
                UNION ALL
                SELECT 'term_documents', td.id, CAST(td.type AS CHAR(40)), td.original_filename,
                       td.mime_type, td.uploaded_at, CAST(td.status AS CHAR(20)), td.is_current, td.superseded_at,
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
        Database::conn()->prepare("UPDATE documents SET status = 'Verified' WHERE application_id = ? AND file_path <> ''")
            ->execute([$applicationId]);
    }

    /**
     * Validate and store one uploaded file ($_FILES entry) under $baseDir/$subDir with a random name.
     * Type is detected from the file CONTENT; the client-supplied name/extension/type are never trusted.
     * Returns ['ok'=>true,'rel'=>stored path,'original'=>clean name,'size'=>int,'mime'=>string] or ['ok'=>false,'error'=>string].
     */
    public static function storeUpload(array $file, string $baseDir, string $relPrefix, string $subDir): array
    {
        $fail = static fn(string $m): array => ['ok' => false, 'error' => $m];
        if (empty($file['name']) || is_array($file['name'])) return $fail('Please choose a file.');
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) return $fail('Upload error.');

        $size = filesize($file['tmp_name']);
        if ($size === false || $size <= 0) return $fail('The uploaded file is empty.');
        if ($size > MAX_FILE_SIZE) return $fail('File too large (max 5MB).');

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ALLOWED_MIME, true)) return $fail('Only PDF, JPG, or PNG allowed.');

        // Structural sanity check so the bytes really are the claimed format.
        if ($mime === 'application/pdf') {
            $valid = file_get_contents($file['tmp_name'], false, null, 0, 5) === '%PDF-';
        } else {
            $info = getimagesize($file['tmp_name']);
            $valid = $info !== false && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true);
        }
        if (!$valid) return $fail('The file is not a valid PDF, JPG, or PNG.');

        $ext = match ($mime) { 'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png' };
        $dir = $baseDir . '/' . $subDir;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            error_log('Cannot create document directory: ' . $dir);
            return $fail('Cannot save file.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) return $fail('Cannot save file.');
        chmod($dir . '/' . $filename, 0640);

        $original = mb_substr((string)preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', (string)$file['name']))), 0, 200);
        return ['ok' => true, 'rel' => $relPrefix . $subDir . '/' . $filename, 'original' => $original, 'size' => $size, 'mime' => $mime];
    }

    /** Delete a stored file (after it was replaced). Silently ignores anything outside the private stores. */
    public static function deleteFile(?string $rel): void
    {
        $path = self::absolutePath($rel);
        if ($path !== null && !unlink($path)) error_log('Could not delete replaced document: ' . $rel);
    }

    /**
     * Resolve a stored relative path to an absolute path inside one of the private stores:
     *   storage/documents/...       -> DOCUMENT_PATH       (application documents)
     *   storage/term-documents/...  -> TERM_DOCUMENT_PATH  (claim and renewal documents)
     * Returns null for anything else (traversal, symlink escape, missing file, unknown prefix).
     */
    public static function absolutePath(?string $rel): ?string
    {
        if ($rel === null || $rel === '') return null;
        $rel = str_replace('\\', '/', $rel);
        if (str_contains($rel, "\0") || preg_match('#(^|/)\.\.(/|$)#', $rel)) return null;

        $stores = ['storage/documents/' => DOCUMENT_PATH, 'storage/term-documents/' => TERM_DOCUMENT_PATH];
        $base = null;
        $sub = '';
        foreach ($stores as $prefix => $dir) {
            if (str_starts_with($rel, $prefix)) { $base = $dir; $sub = substr($rel, strlen($prefix)); break; }
        }
        if ($base === null) return null;

        $baseReal = realpath($base);
        if ($baseReal === false) return null;
        $real = realpath($baseReal . '/' . $sub);
        if ($real === false || !is_file($real)) return null;
        if (!str_starts_with($real, $baseReal . DIRECTORY_SEPARATOR)) return null;
        return $real;
    }

    /**
     * Stream a stored file to the browser. The caller MUST have authorized access already.
     * $label is used for the audit entry (e.g. "School ID"), $entityType/$entityId identify the row.
     */
    public static function send(string $filePath, ?string $originalName, string $label, string $entityType, int $entityId, bool $download = false): void
    {
        $path = self::absolutePath($filePath);
        if ($path === null) { http_response_code(404); die('Document not found.'); }

        // Trust the bytes on disk, not the DB value or the client-supplied name.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? finfo_file($finfo, $path) : false;
        if ($finfo) finfo_close($finfo);
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        if ($ext === null || !in_array($mime, ALLOWED_MIME, true)) {
            http_response_code(415);
            die('Unsupported document type.');
        }

        $name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', basename(str_replace('\\', '/', (string)$originalName)));
        $name = trim((string)$name, '._ ');
        if ($name === '') $name = 'document';
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $ext) $name .= '.' . $ext;

        AuditLog::write($download ? 'document_downloaded' : 'document_viewed', $entityType, $entityId,
            ($download ? 'Downloaded ' : 'Viewed ') . $label);

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    /** Stream an application document (row from `documents`). */
    public static function sendApplicationDocument(array $doc, bool $download = false): void
    {
        self::send((string)$doc['file_path'], $doc['original_filename'] ?? null, (string)$doc['document_type'],
            'documents', (int)$doc['id'], $download);
    }
}
