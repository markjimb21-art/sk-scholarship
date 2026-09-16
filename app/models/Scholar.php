<?php
declare(strict_types=1);

final class Scholar
{
    /* ================= SCHOLARS ================= */

    public static function findByApplicant(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT s.*, sp.program_name, sp.academic_year
             FROM scholars s
             JOIN scholarship_programs sp ON sp.id = s.program_id
             WHERE s.applicant_id = ? LIMIT 1"
        );
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT s.*, sp.program_name, sp.academic_year, sp.min_gpa,
                    u.full_name, u.email, u.contact_number,
                    a.application_code,
                    ap.id AS applicant_id
             FROM scholars s
             JOIN scholarship_programs sp ON sp.id = s.program_id
             JOIN applicants ap ON ap.id = s.applicant_id
             JOIN applications a ON a.id = s.application_id
             JOIN users u ON u.id = ap.user_id
             WHERE s.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function createFromApplication(int $applicationId, int $actorId): array
    {
        $app = Application::find($applicationId);
        if (!$app) return ['ok' => false, 'error' => 'Application not found.'];

        if (self::findByApplicant((int)$app['applicant_id'])) {
            return ['ok' => false, 'error' => 'Scholar record already exists.'];
        }

        $code = generate_code('SCH');
        $pdo = Database::conn();
        $pdo->prepare(
            "INSERT INTO scholars (scholar_code, applicant_id, application_id, program_id, approval_date, status)
             VALUES (?, ?, ?, ?, CURDATE(), 'Active')"
        )->execute([$code, $app['applicant_id'], $applicationId, $app['program_id']]);

        $scholarId = (int)$pdo->lastInsertId();

        AuditLog::write('scholar_created', 'scholars', $scholarId,
            "Scholar created from application #{$app['application_code']}");

        return ['ok' => true, 'scholar_id' => $scholarId, 'scholar_code' => $code];
    }

    public static function search(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $sql = "SELECT s.id, s.scholar_code, s.status, s.approval_date,
                       u.full_name, u.email,
                       e.school_name, e.course_name, e.year_level,
                       sp.program_name,
                       (SELECT COALESCE(SUM(amount),0) FROM scholarship_releases r WHERE r.scholar_id = s.id) AS total_released,
                       (SELECT gpa FROM academic_records ar WHERE ar.scholar_id = s.id ORDER BY id DESC LIMIT 1) AS latest_gpa,
                       (SELECT academic_standing FROM academic_records ar WHERE ar.scholar_id = s.id ORDER BY id DESC LIMIT 1) AS latest_standing
                FROM scholars s
                JOIN applicants a ON a.id = s.applicant_id
                JOIN users u ON u.id = a.user_id
                JOIN scholarship_programs sp ON sp.id = s.program_id
                LEFT JOIN education_records e ON e.applicant_id = a.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= " AND (u.full_name LIKE :q OR s.scholar_code LIKE :q OR u.email LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['status'])) {
            $sql .= " AND s.status = :st";
            $params['st'] = $filters['status'];
        }
        if (!empty($filters['school'])) {
            $sql .= " AND e.school_name = :school";
            $params['school'] = $filters['school'];
        }
        if (!empty($filters['course'])) {
            $sql .= " AND e.course_name LIKE :course";
            $params['course'] = '%' . $filters['course'] . '%';
        }
        if (!empty($filters['year_level'])) {
            $sql .= " AND e.year_level = :yl";
            $params['yl'] = $filters['year_level'];
        }
        if (!empty($filters['program_id'])) {
            $sql .= " AND s.program_id = :pid";
            $params['pid'] = (int)$filters['program_id'];
        }

        $sql .= " ORDER BY s.approval_date DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM scholars s
                JOIN applicants a ON a.id = s.applicant_id
                JOIN users u ON u.id = a.user_id
                LEFT JOIN education_records e ON e.applicant_id = a.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= " AND (u.full_name LIKE :q OR s.scholar_code LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['status'])) { $sql .= " AND s.status = :st"; $params['st'] = $filters['status']; }
        if (!empty($filters['school'])) { $sql .= " AND e.school_name = :school"; $params['school'] = $filters['school']; }
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function updateStatus(int $scholarId, string $status, int $actorId, ?string $remarks = null): void
    {
        if (!in_array($status, SCHOLAR_STATUS, true)) return;

        $old = self::find($scholarId);
        Database::conn()->prepare("UPDATE scholars SET status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$status, $scholarId]);

        AuditLog::write('scholar_status_changed', 'scholars', $scholarId,
            "Status: {$old['status']} → $status",
            ['status' => $old['status']], ['status' => $status, 'remarks' => $remarks]);

        // Notify
        if (!empty($old['applicant_id'])) {
            $stmt = Database::conn()->prepare("SELECT user_id FROM applicants WHERE id = ?");
            $stmt->execute([$old['applicant_id']]);
            $uid = (int)$stmt->fetchColumn();
            if ($uid) {
                Notification::send($uid,
                    "Scholarship Status: $status",
                    $remarks ?: "Your scholar status has been updated to: $status",
                    in_array($status, ['Active','Graduated'], true) ? 'success' : 'warning',
                    url('applicant/scholarship'));
            }
        }
    }

    public static function stats(): array
    {
        $pdo = Database::conn();
        return $pdo->query(
            "SELECT
                COUNT(*) AS total_scholars,
                SUM(status = 'Active') AS active,
                SUM(status = 'On Probation') AS on_probation,
                SUM(status = 'Graduated') AS graduated,
                SUM(status = 'Withdrawn') AS withdrawn,
                SUM(status = 'Suspended') AS suspended,
                SUM(status = 'Disqualified') AS disqualified
             FROM scholars"
        )->fetch() ?: [];
    }

    /* ================= ACADEMIC RECORDS ================= */

    public static function academicRecords(int $scholarId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM academic_records WHERE scholar_id = ?
             ORDER BY academic_year DESC, FIELD(semester, '2nd Semester','1st Semester','Summer'), id DESC"
        );
        $stmt->execute([$scholarId]);
        return $stmt->fetchAll();
    }

    public static function academicRecord(int $id): ?array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM academic_records WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function saveAcademicRecord(array $data, int $actorId): array
    {
        $gpa = (float)$data['gpa'];
        if ($gpa < 1.00 || $gpa > 5.00) {
            return ['ok' => false, 'error' => 'GPA must be between 1.00 and 5.00 (Philippine grading).'];
        }

        // Determine academic standing
        $standing = self::standingFromGpa($gpa);

        $pdo = Database::conn();
        if (!empty($data['id'])) {
            $old = self::academicRecord((int)$data['id']);
            $pdo->prepare(
                "UPDATE academic_records
                 SET academic_year=?, semester=?, year_level=?, course=?, school=?,
                     gpa=?, academic_standing=?, remarks=?, recorded_by=?, recorded_at=NOW()
                 WHERE id=?"
            )->execute([
                $data['academic_year'], $data['semester'], $data['year_level'],
                $data['course'] ?? null, $data['school'] ?? null,
                $gpa, $standing, $data['remarks'] ?? null,
                $actorId, (int)$data['id']
            ]);
            $recordId = (int)$data['id'];
            AuditLog::write('academic_record_updated', 'academic_records', $recordId,
                "Updated academic record for scholar #{$data['scholar_id']}",
                $old, $data);
        } else {
            $pdo->prepare(
                "INSERT INTO academic_records
                 (scholar_id, academic_year, semester, year_level, course, school,
                  gpa, academic_standing, remarks, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $data['scholar_id'], $data['academic_year'], $data['semester'],
                $data['year_level'], $data['course'] ?? null, $data['school'] ?? null,
                $gpa, $standing, $data['remarks'] ?? null, $actorId
            ]);
            $recordId = (int)$pdo->lastInsertId();
            AuditLog::write('academic_record_created', 'academic_records', $recordId,
                "Added academic record for scholar #{$data['scholar_id']}");
        }

        // Update scholar status if failing/probation
        $scholar = self::find((int)$data['scholar_id']);
        if ($scholar && $scholar['status'] === 'Active') {
            if ($standing === 'Failing') {
                self::updateStatus((int)$data['scholar_id'], 'On Probation', $actorId,
                    "Academic performance dropped to Failing (GPA $gpa).");
            }
        }

        return ['ok' => true, 'id' => $recordId, 'standing' => $standing];
    }

    public static function deleteAcademicRecord(int $id, int $actorId): void
    {
        $old = self::academicRecord($id);
        if (!$old) return;
        Database::conn()->prepare("DELETE FROM academic_records WHERE id = ?")->execute([$id]);
        AuditLog::write('academic_record_deleted', 'academic_records', $id,
            "Deleted academic record", $old);
    }

    private static function standingFromGpa(float $gpa): string
    {
        // Philippine college GPA scale (1.00 best, 5.00 failing)
        if ($gpa <= 1.25) return 'Excellent';
        if ($gpa <= 1.75) return 'Very Good';
        if ($gpa <= 2.25) return 'Good';
        if ($gpa <= 2.75) return 'Satisfactory';
        if ($gpa <= 3.00) return 'Passing';
        if ($gpa <= 4.00) return 'Probationary';
        return 'Failing';
    }

    /* ================= SCHOLARSHIP RELEASES ================= */

    public static function releases(int $scholarId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT r.*, u.full_name AS staff_name
             FROM scholarship_releases r
             LEFT JOIN users u ON u.id = r.staff_id
             WHERE r.scholar_id = ?
             ORDER BY r.release_date DESC, r.id DESC"
        );
        $stmt->execute([$scholarId]);
        return $stmt->fetchAll();
    }

    public static function release(int $id): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT r.*, u.full_name AS staff_name,
                    s.scholar_code, sp.program_name,
                    us.full_name AS scholar_name
             FROM scholarship_releases r
             JOIN scholars s ON s.id = r.scholar_id
             JOIN scholarship_programs sp ON sp.id = s.program_id
             JOIN applicants a ON a.id = s.applicant_id
             JOIN users us ON us.id = a.user_id
             LEFT JOIN users u ON u.id = r.staff_id
             WHERE r.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function recordRelease(array $data, int $actorId): array
    {
        if ((float)$data['amount'] <= 0) {
            return ['ok' => false, 'error' => 'Amount must be greater than zero.'];
        }
        if (empty($data['academic_year']) || empty($data['semester'])) {
            return ['ok' => false, 'error' => 'Academic year and semester are required.'];
        }

        $pdo = Database::conn();
        $code = generate_code('REL');

        $pdo->prepare(
            "INSERT INTO scholarship_releases
             (release_code, scholar_id, academic_year, semester, amount, release_date,
              payment_method, reference_number, recipient_confirmed, staff_id, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $code, (int)$data['scholar_id'], $data['academic_year'], $data['semester'],
            (float)$data['amount'], $data['release_date'],
            $data['payment_method'], $data['reference_number'] ?? null,
            !empty($data['recipient_confirmed']) ? 1 : 0,
            $actorId, $data['remarks'] ?? null
        ]);
        $releaseId = (int)$pdo->lastInsertId();

        AuditLog::write('scholarship_released', 'scholarship_releases', $releaseId,
            "Released ₱" . number_format((float)$data['amount'], 2) . " to scholar #{$data['scholar_id']}");

        // Notify scholar
        $stmt = $pdo->prepare(
            "SELECT ap.user_id FROM scholars s JOIN applicants ap ON ap.id = s.applicant_id WHERE s.id = ?"
        );
        $stmt->execute([(int)$data['scholar_id']]);
        $uid = (int)$stmt->fetchColumn();
        if ($uid) {
            Notification::send($uid, 'Scholarship Released 💵',
                "₱" . number_format((float)$data['amount'], 2) . " was released to you for {$data['academic_year']} {$data['semester']}.",
                'success', url('applicant/scholarship'));
        }

        return ['ok' => true, 'id' => $releaseId, 'release_code' => $code];
    }

    public static function deleteRelease(int $id, int $actorId): void
    {
        $old = self::release($id);
        if (!$old) return;
        Database::conn()->prepare("DELETE FROM scholarship_releases WHERE id = ?")->execute([$id]);
        AuditLog::write('scholarship_release_deleted', 'scholarship_releases', $id,
            "Deleted release {$old['release_code']}", $old);
    }

    public static function totalReleased(?int $scholarId = null): float
    {
        if ($scholarId) {
            $stmt = Database::conn()->prepare("SELECT COALESCE(SUM(amount),0) FROM scholarship_releases WHERE scholar_id = ?");
            $stmt->execute([$scholarId]);
            return (float)$stmt->fetchColumn();
        }
        return (float)Database::conn()->query("SELECT COALESCE(SUM(amount),0) FROM scholarship_releases")->fetchColumn();
    }

    /* ================= RENEWALS ================= */

    public static function renewals(int $scholarId): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT r.*, u.full_name AS evaluated_by_name
             FROM scholarship_renewals r
             LEFT JOIN users u ON u.id = r.evaluated_by
             WHERE r.scholar_id = ?
             ORDER BY r.created_at DESC"
        );
        $stmt->execute([$scholarId]);
        return $stmt->fetchAll();
    }

    public static function renewal(int $id): ?array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM scholarship_renewals WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function createRenewal(array $data, int $actorId): array
    {
        $pdo = Database::conn();
        // Prevent duplicate (scholar, year, semester)
        $chk = $pdo->prepare(
            "SELECT id FROM scholarship_renewals WHERE scholar_id=? AND academic_year=? AND semester <=> ?"
        );
        $chk->execute([(int)$data['scholar_id'], $data['academic_year'], $data['semester'] ?? null]);
        if ($chk->fetch()) {
            return ['ok' => false, 'error' => 'A renewal for this academic year + semester already exists.'];
        }

        $pdo->prepare(
            "INSERT INTO scholarship_renewals
             (scholar_id, academic_year, semester, gpa, residency_verified, enrollment_verified,
              documents_complete, status, remarks, evaluated_by, evaluated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        )->execute([
            (int)$data['scholar_id'], $data['academic_year'], $data['semester'] ?? null,
            $data['gpa'] ?? null,
            !empty($data['residency_verified']) ? 1 : 0,
            !empty($data['enrollment_verified']) ? 1 : 0,
            !empty($data['documents_complete']) ? 1 : 0,
            $data['status'] ?? 'For Review',
            $data['remarks'] ?? null,
            $actorId,
        ]);
        $id = (int)$pdo->lastInsertId();
        AuditLog::write('renewal_created', 'scholarship_renewals', $id,
            "Renewal record for scholar #{$data['scholar_id']} {$data['academic_year']}");
        return ['ok' => true, 'id' => $id];
    }

    public static function updateRenewal(int $id, array $data, int $actorId): array
    {
        $old = self::renewal($id);
        if (!$old) return ['ok' => false, 'error' => 'Renewal not found.'];

        Database::conn()->prepare(
            "UPDATE scholarship_renewals
             SET status=?, gpa=?, residency_verified=?, enrollment_verified=?, documents_complete=?,
                 remarks=?, evaluated_by=?, evaluated_at=NOW()
             WHERE id=?"
        )->execute([
            $data['status'], $data['gpa'] ?? null,
            !empty($data['residency_verified']) ? 1 : 0,
            !empty($data['enrollment_verified']) ? 1 : 0,
            !empty($data['documents_complete']) ? 1 : 0,
            $data['remarks'] ?? null, $actorId, $id
        ]);

        AuditLog::write('renewal_updated', 'scholarship_renewals', $id,
            "Renewal updated: {$old['status']} → {$data['status']}",
            $old, $data);

        // Notify scholar
        $scholar = self::find((int)$old['scholar_id']);
        if ($scholar) {
            $stmt = Database::conn()->prepare("SELECT user_id FROM applicants WHERE id = ?");
            $stmt->execute([$scholar['applicant_id']]);
            $uid = (int)$stmt->fetchColumn();
            if ($uid) {
                Notification::send($uid, "Renewal: {$data['status']}",
                    $data['remarks'] ?: "Your scholarship renewal status is now: {$data['status']}",
                    in_array($data['status'], ['Renewed','Eligible for Renewal'], true) ? 'success' : 'warning',
                    url('applicant/scholarship'));
            }
        }

        return ['ok' => true];
    }

    public static function deleteRenewal(int $id, int $actorId): void
    {
        $old = self::renewal($id);
        if (!$old) return;
        Database::conn()->prepare("DELETE FROM scholarship_renewals WHERE id = ?")->execute([$id]);
        AuditLog::write('renewal_deleted', 'scholarship_renewals', $id, 'Deleted renewal', $old);
    }

    /* ================= ANALYTICS ================= */

    public static function scholarsBySchool(): array
    {
        return Database::conn()->query(
            "SELECT e.school_name, COUNT(*) AS c
             FROM scholars s
             JOIN applicants a ON a.id = s.applicant_id
             LEFT JOIN education_records e ON e.applicant_id = a.id
             WHERE s.status = 'Active'
             GROUP BY e.school_name
             ORDER BY c DESC LIMIT 15"
        )->fetchAll();
    }

    public static function scholarsByCourse(): array
    {
        return Database::conn()->query(
            "SELECT e.course_name, COUNT(*) AS c
             FROM scholars s
             JOIN applicants a ON a.id = s.applicant_id
             LEFT JOIN education_records e ON e.applicant_id = a.id
             WHERE s.status = 'Active'
             GROUP BY e.course_name
             ORDER BY c DESC LIMIT 15"
        )->fetchAll();
    }

    public static function scholarsByYearLevel(): array
    {
        return Database::conn()->query(
            "SELECT e.year_level, COUNT(*) AS c
             FROM scholars s
             JOIN applicants a ON a.id = s.applicant_id
             LEFT JOIN education_records e ON e.applicant_id = a.id
             WHERE s.status = 'Active'
             GROUP BY e.year_level
             ORDER BY e.year_level"
        )->fetchAll();
    }

    public static function atRiskScholars(float $minGpa = 2.50): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT s.id, s.scholar_code, u.full_name, e.school_name, e.course_name,
                    ar.gpa, ar.academic_standing, ar.academic_year, ar.semester
             FROM scholars s
             JOIN applicants a ON a.id = s.applicant_id
             JOIN users u ON u.id = a.user_id
             LEFT JOIN education_records e ON e.applicant_id = a.id
             JOIN academic_records ar ON ar.id = (
                SELECT MAX(id) FROM academic_records WHERE scholar_id = s.id
             )
             WHERE s.status IN ('Active','On Probation') AND ar.gpa > ?
             ORDER BY ar.gpa DESC"
        );
        $stmt->execute([$minGpa]);
        return $stmt->fetchAll();
    }
}