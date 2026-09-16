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

    public static function pendingCount(): int
    {
        return (int)Database::conn()
            ->query("SELECT COUNT(*) FROM documents WHERE status IN ('Submitted','Under Review')")
            ->fetchColumn();
    }
}