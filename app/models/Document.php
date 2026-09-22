<?php
declare(strict_types=1);

final class Document
{
    public static function byApplication(int $applicationId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT d.*, u.full_name AS reviewer_name
             FROM documents d
             LEFT JOIN users u ON u.id = d.reviewed_by
             WHERE d.application_id = ?
             ORDER BY FIELD(d.document_type,
                'Profile Picture','Certificate of Residency','Form 138','Enrollment Form','School ID')"
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

    public static function verifiedCount(int $applicationId): int
    {
        $stmt = Database::conn()->prepare(
            "SELECT COUNT(*) FROM documents WHERE application_id = ? AND status = 'Verified'"
        );
        $stmt->execute([$applicationId]);
        return (int)$stmt->fetchColumn();
    }

    public static function allVerified(int $applicationId): bool
    {
        return self::verifiedCount($applicationId) >= count(DOCUMENT_TYPES);
    }

    public static function updateStatus(int $docId, string $status, int $reviewerId, ?string $remarks = null): void
    {
        $old = self::find($docId);
        Database::conn()->prepare(
            "UPDATE documents
             SET status = ?, reviewed_at = NOW(), reviewed_by = ?, remarks = ?
             WHERE id = ?"
        )->execute([$status, $reviewerId, $remarks, $docId]);

        AuditLog::write('document_' . strtolower(str_replace(' ', '_', $status)),
            'documents', $docId,
            "Document {$old['document_type']} set to $status",
            ['status' => $old['status'] ?? null],
            ['status' => $status, 'remarks' => $remarks]
        );
    }

    /**
     * Resolve a stored relative path to a real file, ONLY if it lies inside an approved document root:
     *   storage/documents/...  -> DOCUMENT_PATH   (private store, outside the web root)
     *   uploads/documents/...  -> UPLOAD_PATH/documents   (legacy files uploaded before the private store existed)
     * Returns null for anything else (traversal, symlink escape, missing file, unknown prefix).
     */
    public static function absolutePath(?string $rel): ?string
    {
        if ($rel === null || $rel === '') return null;
        $rel = str_replace('\\', '/', $rel);
        if (str_contains($rel, "\0") || preg_match('#(^|/)\.\.(/|$)#', $rel)) return null;

        if (str_starts_with($rel, 'storage/documents/')) {
            $base = DOCUMENT_PATH;
            $sub  = substr($rel, strlen('storage/documents/'));
        } elseif (str_starts_with($rel, 'uploads/documents/')) {
            $base = UPLOAD_PATH . '/documents';
            $sub  = substr($rel, strlen('uploads/documents/'));
        } else {
            return null;
        }

        $baseReal = realpath($base);
        if ($baseReal === false) return null;
        $real = realpath($baseReal . '/' . $sub);
        if ($real === false || !is_file($real)) return null;
        if (!str_starts_with($real, $baseReal . DIRECTORY_SEPARATOR)) return null;
        return $real;
    }

    /** Stream a document to the browser. The caller MUST have authorized access to $doc already. */
    public static function send(array $doc, bool $download = false): void
    {
        $path = self::absolutePath($doc['file_path'] ?? '');
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

        $name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', basename(str_replace('\\', '/', (string)($doc['original_filename'] ?? ''))));
        $name = trim((string)$name, '._ ');
        if ($name === '') $name = 'document';
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $ext) $name .= '.' . $ext;

        AuditLog::write($download ? 'document_downloaded' : 'document_viewed', 'documents', (int)$doc['id'],
            ($download ? 'Downloaded ' : 'Viewed ') . ($doc['document_type'] ?? 'document'));

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public static function pendingCount(): int
    {
        return (int)Database::conn()
            ->query("SELECT COUNT(*) FROM documents WHERE status IN ('Submitted','Under Review')")
            ->fetchColumn();
    }
}