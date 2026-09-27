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
