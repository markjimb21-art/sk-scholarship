<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/Applicant.php';
require_once BASE_PATH . '/app/models/Application.php';
require_once BASE_PATH . '/app/models/Document.php';
require_once BASE_PATH . '/app/models/Interview.php';
require_once BASE_PATH . '/app/models/Scholar.php';

final class ApplicantController
{
    public function handle(string $action): void
    {
        $action = trim($action, '/');
        switch ($action) {
            case 'dashboard':           $this->dashboard(); break;
            case 'application':         $this->application(); break;
            case 'application/save':    $this->saveApplication(); break;
            case 'application/submit':  $this->submitApplication(); break;
            case 'documents':           $this->documents(); break;
            case 'documents/upload':    $this->uploadDocument(); break;
            case 'documents/view':      $this->viewDocument(); break;
            case 'interview':           $this->interview(); break;
            case 'scholarship':         $this->scholarship(); break;
            case 'notifications':       $this->notifications(); break;
            case 'profile':             $this->profile(); break;
            default:
                http_response_code(404);
                require VIEW_PATH . '/errors/404.php';
        }
    }

    private function applicantRecord(): array
    {
        $userId = (int)Auth::user()['id'];
        $stmt = Database::conn()->prepare("SELECT * FROM applicants WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $rec = $stmt->fetch();
        if (!$rec) {
            // auto-heal
            $code = generate_code('APP');
            Database::conn()->prepare("INSERT INTO applicants (user_id, application_code) VALUES (?,?)")
                ->execute([$userId, $code]);
            return ['id' => (int)Database::conn()->lastInsertId(), 'application_code' => $code, 'user_id' => $userId];
        }
        return $rec;
    }

    private function activeApplication(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM applications WHERE applicant_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    /** Applicants may edit/submit only while the application is a Draft or was sent back as Incomplete. */
    private function isEditable(?array $app): bool
    {
        return $app === null || in_array($app['status'], ['Draft', 'Incomplete'], true);
    }

    /** Honors the admin "application_open" system setting (defaults to open when unset). */
    private function applicationsOpen(): bool
    {
        $v = Database::conn()->query("SELECT setting_value FROM system_settings WHERE setting_key = 'application_open'")->fetchColumn();
        return $v === false || $v === null || $v === '' || (string)$v !== '0';
    }

    private function dashboard(): void
    {
        $applicant = $this->applicantRecord();
        $app = $this->activeApplication((int)$applicant['id']);

        $docCount = 0; $docVerified = 0;
        if ($app) {
            $stmt = Database::conn()->prepare(
                "SELECT COUNT(*) total, SUM(status='Verified') ver FROM documents WHERE application_id = ?"
            );
            $stmt->execute([$app['id']]);
            $row = $stmt->fetch() ?: ['total' => 0, 'ver' => 0];
            $docCount = (int)$row['total'];
            $docVerified = (int)$row['ver'];
        }

        $interview = null;
        if ($app) {
            $stmt = Database::conn()->prepare(
                "SELECT ia.*, s.interview_date, s.start_time, s.end_time, s.venue, s.interviewer
                 FROM interview_assignments ia
                 JOIN interview_schedules s ON s.id = ia.schedule_id
                 WHERE ia.application_id = ? LIMIT 1"
            );
            $stmt->execute([$app['id']]);
            $interview = $stmt->fetch() ?: null;
        }

        $pageTitle = 'Applicant Dashboard';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/dashboard.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function application(): void
    {
        $applicant = $this->applicantRecord();
        $pdo = Database::conn();

        $stmt = $pdo->prepare("SELECT * FROM applicants_personal_information WHERE applicant_id = ?");
        $stmt->execute([$applicant['id']]);
        $personal = $stmt->fetch() ?: [];

        $stmt = $pdo->prepare("SELECT * FROM education_records WHERE applicant_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$applicant['id']]);
        $education = $stmt->fetch() ?: [];

        $stmt = $pdo->prepare("SELECT * FROM family_background WHERE applicant_id = ?");
        $stmt->execute([$applicant['id']]);
        $family = $stmt->fetch() ?: [];

        $app = $this->activeApplication((int)$applicant['id']);

        $puroks = $pdo->query("SELECT id, purok_name FROM puroks ORDER BY purok_name")->fetchAll();
        $schools = $pdo->query("SELECT id, school_name FROM schools ORDER BY school_name")->fetchAll();

        $pageTitle = 'My Scholarship Application';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/application_form.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function saveApplication(): void
    {
        verify_csrf();
        $applicant = $this->applicantRecord();
        $pdo = Database::conn();
        $u = Auth::user();
        $step = $_POST['step'] ?? '';

        $current = $this->activeApplication((int)$applicant['id']);
        if (!$this->isEditable($current)) {
            flash('warning', 'Your application has already been submitted and can no longer be edited.');
            redirect('applicant/application');
        }
        if (!$current && !$this->applicationsOpen()) {
            flash('warning', 'Applications are currently closed.');
            redirect('applicant/application');
        }

        try {
            $pdo->beginTransaction();
            // Ensure an application row exists (Draft) so documents can be uploaded
            $existing = $this->activeApplication((int)$applicant['id']);
            if (!$existing) {
                $program = $pdo->query("SELECT id FROM scholarship_programs WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetch();
                if ($program) {
                    $appCode = generate_code('APP');
                    $pdo->prepare("INSERT INTO applications (application_code, applicant_id, program_id, status)
                                VALUES (?, ?, ?, 'Draft')")
                        ->execute([$appCode, $applicant['id'], $program['id']]);
                    $appId = (int)$pdo->lastInsertId();

                    // Pre-create empty document rows
                    foreach (DOCUMENT_TYPES as $type) {
                        $pdo->prepare("INSERT INTO documents (application_id, document_type, file_path, original_filename, status)
                                    VALUES (?, ?, '', '', 'Not Submitted')")
                            ->execute([$appId, $type]);
                    }
                }
            }

            if ($step === 'personal') {
                $age = calculate_age($_POST['date_of_birth'] ?? '');
                $data = [
                    'first_name' => trim($_POST['first_name'] ?? ''),
                    'middle_name' => trim($_POST['middle_name'] ?? ''),
                    'last_name' => trim($_POST['last_name'] ?? ''),
                    'suffix' => trim($_POST['suffix'] ?? ''),
                    'date_of_birth' => $_POST['date_of_birth'] ?? null,
                    'age' => $age,
                    'civil_status' => $_POST['civil_status'] ?? 'Single',
                    'complete_address' => trim($_POST['complete_address'] ?? ''),
                    'purok_id' => $_POST['purok_id'] ?: null,
                    'contact_number' => trim($_POST['contact_number'] ?? ''),
                    'email' => $u['email'], // enforce registered email
                    'height_cm' => $_POST['height_cm'] ?: null,
                    'weight_kg' => $_POST['weight_kg'] ?: null,
                    'blood_type' => trim($_POST['blood_type'] ?? ''),
                    'religion' => trim($_POST['religion'] ?? ''),
                    'citizenship' => trim($_POST['citizenship'] ?? 'Filipino'),
                ];
                $sql = "INSERT INTO applicants_personal_information
                        (applicant_id, first_name, middle_name, last_name, suffix, date_of_birth, age,
                         civil_status, complete_address, purok_id, contact_number, email,
                         height_cm, weight_kg, blood_type, religion, citizenship)
                        VALUES (:applicant_id, :first_name, :middle_name, :last_name, :suffix, :date_of_birth, :age,
                                :civil_status, :complete_address, :purok_id, :contact_number, :email,
                                :height_cm, :weight_kg, :blood_type, :religion, :citizenship)
                        ON DUPLICATE KEY UPDATE
                            first_name=VALUES(first_name), middle_name=VALUES(middle_name), last_name=VALUES(last_name),
                            suffix=VALUES(suffix), date_of_birth=VALUES(date_of_birth), age=VALUES(age),
                            civil_status=VALUES(civil_status), complete_address=VALUES(complete_address),
                            purok_id=VALUES(purok_id), contact_number=VALUES(contact_number), email=VALUES(email),
                            height_cm=VALUES(height_cm), weight_kg=VALUES(weight_kg), blood_type=VALUES(blood_type),
                            religion=VALUES(religion), citizenship=VALUES(citizenship)";
                $data['applicant_id'] = $applicant['id'];
                $pdo->prepare($sql)->execute($data);
            }

            if ($step === 'education') {
                $data = [
                    'applicant_id' => $applicant['id'],
                    'school_name' => trim($_POST['school_name'] ?? ''),
                    'year_level' => $_POST['year_level'] ?? '1st Year',
                    'year_level_other' => trim($_POST['year_level_other'] ?? ''),
                    'course_name' => trim($_POST['course_name'] ?? ''),
                    'previous_scholarship' => isset($_POST['previous_scholarship']) ? 1 : 0,
                    'previous_scholarship_details' => trim($_POST['previous_scholarship_details'] ?? ''),
                    'current_scholarship' => isset($_POST['current_scholarship']) ? 1 : 0,
                    'current_scholarship_details' => trim($_POST['current_scholarship_details'] ?? ''),
                ];
                $pdo->prepare("DELETE FROM education_records WHERE applicant_id = ?")->execute([$applicant['id']]);
                $pdo->prepare("INSERT INTO education_records
                    (applicant_id, school_name, year_level, year_level_other, course_name,
                     previous_scholarship, previous_scholarship_details,
                     current_scholarship, current_scholarship_details)
                    VALUES (:applicant_id, :school_name, :year_level, :year_level_other, :course_name,
                            :previous_scholarship, :previous_scholarship_details,
                            :current_scholarship, :current_scholarship_details)")->execute($data);
            }

            if ($step === 'family') {
                $fi = (float)($_POST['father_income'] ?? 0);
                $mi = (float)($_POST['mother_income'] ?? 0);
                $data = [
                    'applicant_id' => $applicant['id'],
                    'father_name' => trim($_POST['father_name'] ?? ''),
                    'father_age' => $_POST['father_age'] ?: null,
                    'father_occupation' => trim($_POST['father_occupation'] ?? ''),
                    'father_address' => trim($_POST['father_address'] ?? ''),
                    'father_contact' => trim($_POST['father_contact'] ?? ''),
                    'father_income' => $fi,
                    'father_status' => $_POST['father_status'] ?? 'Available',
                    'mother_name' => trim($_POST['mother_name'] ?? ''),
                    'mother_age' => $_POST['mother_age'] ?: null,
                    'mother_occupation' => trim($_POST['mother_occupation'] ?? ''),
                    'mother_address' => trim($_POST['mother_address'] ?? ''),
                    'mother_contact' => trim($_POST['mother_contact'] ?? ''),
                    'mother_income' => $mi,
                    'mother_status' => $_POST['mother_status'] ?? 'Available',
                    'combined_income' => $fi + $mi,
                    'total_family_income' => $fi + $mi,
                    'family_size' => $_POST['family_size'] ?: null,
                ];
                $sql = "INSERT INTO family_background
                        (applicant_id, father_name, father_age, father_occupation, father_address, father_contact,
                         father_income, father_status, mother_name, mother_age, mother_occupation, mother_address,
                         mother_contact, mother_income, mother_status, combined_income, total_family_income, family_size)
                        VALUES (:applicant_id, :father_name, :father_age, :father_occupation, :father_address, :father_contact,
                                :father_income, :father_status, :mother_name, :mother_age, :mother_occupation, :mother_address,
                                :mother_contact, :mother_income, :mother_status, :combined_income, :total_family_income, :family_size)
                        ON DUPLICATE KEY UPDATE
                            father_name=VALUES(father_name), father_age=VALUES(father_age), father_occupation=VALUES(father_occupation),
                            father_address=VALUES(father_address), father_contact=VALUES(father_contact),
                            father_income=VALUES(father_income), father_status=VALUES(father_status),
                            mother_name=VALUES(mother_name), mother_age=VALUES(mother_age), mother_occupation=VALUES(mother_occupation),
                            mother_address=VALUES(mother_address), mother_contact=VALUES(mother_contact),
                            mother_income=VALUES(mother_income), mother_status=VALUES(mother_status),
                            combined_income=VALUES(combined_income), total_family_income=VALUES(total_family_income),
                            family_size=VALUES(family_size)";
                $pdo->prepare($sql)->execute($data);
            }

            $pdo->commit();
            AuditLog::write('application_step_saved', 'applicants', (int)$applicant['id'], "Saved step: $step");
            flash('success', 'Progress saved.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('danger', APP_DEBUG ? $e->getMessage() : 'Failed to save.');
        }
        redirect('applicant/application');
    }

    private function submitApplication(): void
    {
        verify_csrf();
        $applicant = $this->applicantRecord();
        $pdo = Database::conn();

        // Validate completeness
        $personal = $pdo->prepare("SELECT 1 FROM applicants_personal_information WHERE applicant_id = ?");
        $personal->execute([$applicant['id']]);
        if (!$personal->fetch()) { flash('danger', 'Please complete Personal Information.'); redirect('applicant/application'); }

        $edu = $pdo->prepare("SELECT 1 FROM education_records WHERE applicant_id = ? LIMIT 1");
        $edu->execute([$applicant['id']]);
        if (!$edu->fetch()) { flash('danger', 'Please complete Education.'); redirect('applicant/application'); }

        $fam = $pdo->prepare("SELECT 1 FROM family_background WHERE applicant_id = ?");
        $fam->execute([$applicant['id']]);
        if (!$fam->fetch()) { flash('danger', 'Please complete Family Background.'); redirect('applicant/application'); }

        // Find the (Draft) application
        $app = $this->activeApplication((int)$applicant['id']);
        if (!$app) {
            flash('danger', 'Please save at least Step 1 before submitting.');
            redirect('applicant/application');
        }

        if (!$this->isEditable($app)) {
            flash('warning', 'This application has already been submitted.');
            redirect('applicant/dashboard');
        }
        if (!$this->applicationsOpen()) {
            flash('warning', 'Applications are currently closed.');
            redirect('applicant/application');
        }

        // Verify at least 4 out of 5 documents uploaded (or allow submit and let staff verify)
        $docStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM documents WHERE application_id = ? AND status != 'Not Submitted' AND file_path != ''"
        );
        $docStmt->execute([$app['id']]);
        $uploadedDocs = (int)$docStmt->fetchColumn();

        // Uncomment the next block if you want to REQUIRE all 5 docs before submission:
        // if ($uploadedDocs < 5) {
        //     flash('danger', 'Please upload all required documents before submitting.');
        //     redirect('applicant/documents');
        // }

        // Update status
        $pdo->prepare("UPDATE applications SET status='Submitted', submitted_at=NOW() WHERE id=?")
            ->execute([$app['id']]);

        AuditLog::write('application_submitted', 'applications', (int)$app['id'], 'Application submitted');
        Notification::send((int)Auth::user()['id'], 'Application Submitted',
            'Your scholarship application has been submitted and is now under initial review.',
            'success', url('applicant/dashboard'));

        flash('success', 'Application submitted successfully! Please wait for the initial review.');
        redirect('applicant/dashboard');
    }

    private function documents(): void
    {
        $applicant = $this->applicantRecord();
        $app = $this->activeApplication((int)$applicant['id']);
        $documents = [];
        if ($app) {
            $stmt = Database::conn()->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY FIELD(document_type, 'Profile Picture','Certificate of Residency','Form 138','Enrollment Form','School ID')");
            $stmt->execute([$app['id']]);
            $documents = $stmt->fetchAll();
        }
        $pageTitle = 'My Documents';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/documents.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function uploadDocument(): void
    {
        verify_csrf();
        $applicant = $this->applicantRecord();
        $app = $this->activeApplication((int)$applicant['id']);
        if (!$app) { flash('danger', 'Submit your application first.'); redirect('applicant/documents'); }
        if (in_array($app['status'], ['Approved', 'Rejected'], true)) {
            flash('warning', 'This application has been decided; documents can no longer be changed.');
            redirect('applicant/documents');
        }

        $type = $_POST['document_type'] ?? '';
        if (!in_array($type, DOCUMENT_TYPES, true)) { flash('danger', 'Invalid document type.'); redirect('applicant/documents'); }
        if (empty($_FILES['file']['name']) || is_array($_FILES['file']['name'])) { flash('danger', 'Please choose a file.'); redirect('applicant/documents'); }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) { flash('danger', 'Upload error.'); redirect('applicant/documents'); }
        if (!is_uploaded_file($file['tmp_name'])) { flash('danger', 'Upload error.'); redirect('applicant/documents'); }

        // Size: measured on the server, not trusted from the client.
        $size = filesize($file['tmp_name']);
        if ($size === false || $size <= 0) { flash('danger', 'The uploaded file is empty.'); redirect('applicant/documents'); }
        if ($size > MAX_FILE_SIZE) { flash('danger', 'File too large (max 5MB).'); redirect('applicant/documents'); }

        // Type: detected from the file CONTENT; the client-supplied name/extension/type are never trusted.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ALLOWED_MIME, true)) { flash('danger', 'Only PDF, JPG, or PNG allowed.'); redirect('applicant/documents'); }

        // Structural sanity check so the bytes really are the claimed format.
        if ($mime === 'application/pdf') {
            $head = file_get_contents($file['tmp_name'], false, null, 0, 5);
            $valid = ($head === '%PDF-');
        } else {
            $info = getimagesize($file['tmp_name']);
            $valid = $info !== false && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true);
        }
        if (!$valid) { flash('danger', 'The file is not a valid PDF, JPG, or PNG.'); redirect('applicant/documents'); }

        // Extension comes ONLY from the detected type; filename is random and unguessable.
        $ext = match($mime) { 'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png' };
        $dir = DOCUMENT_PATH . '/' . (int)$app['id'];
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            error_log('Cannot create document directory: ' . $dir);
            flash('danger', 'Cannot save file.');
            redirect('applicant/documents');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $target = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) { flash('danger', 'Cannot save file.'); redirect('applicant/documents'); }
        chmod($target, 0640);

        $rel = 'storage/documents/' . (int)$app['id'] . '/' . $filename;
        $origName = mb_substr((string)preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', (string)$file['name']))), 0, 200);

        // Upsert document row
        $existing = Database::conn()->prepare("SELECT id, file_path FROM documents WHERE application_id = ? AND document_type = ?");
        $existing->execute([$app['id'], $type]);
        $row = $existing->fetch();

        if ($row) {
            // remove the previous file (resolver refuses anything outside the approved document roots)
            $oldPath = Document::absolutePath($row['file_path']);
            if ($oldPath !== null && !unlink($oldPath)) {
                error_log('Could not delete replaced document: ' . $row['file_path']);
            }
            Database::conn()->prepare("UPDATE documents SET file_path=?, original_filename=?, file_size=?, mime_type=?,
                                       status='Submitted', uploaded_at=NOW(), reviewed_at=NULL, reviewed_by=NULL, remarks=NULL
                                       WHERE id=?")
                ->execute([$rel, $origName, $size, $mime, $row['id']]);
        } else {
            Database::conn()->prepare("INSERT INTO documents (application_id, document_type, file_path, original_filename, file_size, mime_type, status)
                                       VALUES (?,?,?,?,?,?, 'Submitted')")
                ->execute([$app['id'], $type, $rel, $origName, $size, $mime]);
        }

        AuditLog::write('document_uploaded', 'documents', $row ? (int)$row['id'] : null, "Uploaded $type for application #{$app['application_code']}");
        flash('success', "$type uploaded successfully.");
        redirect('applicant/documents');
    }

    /** An applicant may only open documents belonging to their OWN application. */
    private function viewDocument(): void
    {
        $applicant = $this->applicantRecord();
        $doc = Document::find((int)($_GET['id'] ?? 0));
        $owned = false;
        if ($doc) {
            $stmt = Database::conn()->prepare("SELECT 1 FROM applications WHERE id = ? AND applicant_id = ?");
            $stmt->execute([$doc['application_id'], $applicant['id']]);
            $owned = (bool)$stmt->fetchColumn();
        }
        if (!$doc || !$owned) {
            // 404 (not 403) so document ids cannot be probed
            http_response_code(404);
            die('Document not found.');
        }
        Document::send($doc, isset($_GET['download']));
    }

    private function interview(): void
    {
        $applicant = $this->applicantRecord();
        $app = $this->activeApplication((int)$applicant['id']);
        $interview = null;
        $evalResult = null;
        if ($app) {
            $stmt = Database::conn()->prepare(
                "SELECT ia.*, s.interview_date, s.start_time, s.end_time, s.venue, s.interviewer, s.remarks as sched_remarks,
                        e.result, e.rating, e.recommendation
                 FROM interview_assignments ia
                 JOIN interview_schedules s ON s.id = ia.schedule_id
                 LEFT JOIN interview_evaluations e ON e.assignment_id = ia.id
                 WHERE ia.application_id = ? LIMIT 1"
            );
            $stmt->execute([$app['id']]);
            $interview = $stmt->fetch() ?: null;
        }
        $pageTitle = 'My Interview';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/interview.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function scholarship(): void
    {
        $applicant = $this->applicantRecord();
        $stmt = Database::conn()->prepare("SELECT * FROM scholars WHERE applicant_id = ? LIMIT 1");
        $stmt->execute([$applicant['id']]);
        $scholar = $stmt->fetch() ?: null;

        $releases = $academics = $renewals = [];
        if ($scholar) {
            $s = Database::conn()->prepare("SELECT * FROM scholarship_releases WHERE scholar_id = ? ORDER BY release_date DESC");
            $s->execute([$scholar['id']]);
            $releases = $s->fetchAll();

            $a = Database::conn()->prepare("SELECT * FROM academic_records WHERE scholar_id = ? ORDER BY academic_year ASC, FIELD(semester,'1st Semester','2nd Semester','Summer') ASC");
            $a->execute([$scholar['id']]);
            $academics = $a->fetchAll();

            $r = Database::conn()->prepare("SELECT * FROM scholarship_renewals WHERE scholar_id = ? ORDER BY created_at DESC");
            $r->execute([$scholar['id']]);
            $renewals = $r->fetchAll();
        }
        $pageTitle = 'My Scholarship';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/scholarship.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function notifications(): void
    {
        $userId = (int)Auth::user()['id'];
        if (!empty($_GET['mark_read'])) {
            Database::conn()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
            json_response(['ok' => true]);
        }
        $stmt = Database::conn()->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll();
        $pageTitle = 'Notifications';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/notifications.php';
        require VIEW_PATH . '/layouts/footer.php';
    }

    private function profile(): void
    {
        $user = Auth::user();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $pdo = Database::conn();
            $name = trim($_POST['full_name'] ?? '');
            $contact = trim($_POST['contact_number'] ?? '');
            if ($name === '') { flash('danger', 'Full name required.'); redirect('applicant/profile'); }
            $pdo->prepare("UPDATE users SET full_name=?, contact_number=? WHERE id=?")
                ->execute([$name, $contact, $user['id']]);
            if (!empty($_POST['new_password'])) {
                if (strlen($_POST['new_password']) < PASSWORD_MIN_LENGTH) { flash('danger', 'Password too short.'); redirect('applicant/profile'); }
                if (!password_verify($_POST['current_password'] ?? '', $user['password_hash'])) { flash('danger', 'Current password incorrect.'); redirect('applicant/profile'); }
                $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                    ->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user['id']]);
            }
            AuditLog::write('profile_updated', 'users', (int)$user['id'], 'Profile updated');
            flash('success', 'Profile updated.');
            redirect('applicant/profile');
        }
        $pageTitle = 'My Profile';
        require VIEW_PATH . '/layouts/header.php';
        require VIEW_PATH . '/applicant/Profile.php';   // file on disk is Profile.php (case-sensitive on Linux)
        require VIEW_PATH . '/layouts/footer.php';
    }
}