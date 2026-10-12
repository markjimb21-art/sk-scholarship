<?php
declare(strict_types=1);

/** Autosave of the 3 form steps, the Returned-with-flags lock, profile, document ownership and notifications. */
final class ApplicantWritePathsTest extends HttpTestCase
{
    private static function autosave(HttpClient $c, array $fields): array
    {
        $c->post('/applicant/application/autosave', $fields, [], ['X-Requested-With: XMLHttpRequest'], '/applicant/application');
        return $c->json() ?? ['raw' => $c->body];
    }

    public function testFirstAutosaveCreatesTheDraftWithFiveDocumentPlaceholders(): void
    {
        $pdo = Database::conn();
        $pdo->exec("INSERT INTO users (id, role_id, full_name, email, password_hash, is_active, email_verified)
                    SELECT 20, id, 'Nova Newcomer', 'nova@test.local', (SELECT password_hash FROM users WHERE id = 4), 1, 1 FROM roles WHERE role_name = 'applicant'");
        $pdo->exec('INSERT INTO applicants (id, user_id) VALUES (20, 20)');
        $c = self::signedIn('nova@test.local');

        $r = self::autosave($c, ['step' => 'personal', 'first_name' => 'nova', 'last_name' => 'newcomer', 'date_of_birth' => '2005-05-05',
            'address_unit' => 'blk 2', 'purok_id' => '3', 'contact_number' => '0917-123-4567', 'height_cm' => '155.55', 'weight_kg' => '999',
            'blood_type' => 'Z+', 'sex' => 'Female', 'religion' => 'catholic', 'citizenship' => '']);
        $this->assertTrue($r['success'], json_encode($r));
        $this->assertSame('Draft', $r['status']);
        $app = self::row('SELECT id, program_id, status FROM applications WHERE applicant_id = 20');
        $this->assertSame(['id' => $r['application_id'], 'program_id' => 1, 'status' => 'Draft'], $app);
        $this->assertSame(5, (int)self::value("SELECT COUNT(*) FROM documents WHERE application_id = ? AND status = 'Not Submitted' AND file_path = ''", [$app['id']]));
        $this->assertSame([
            'first_name' => 'NOVA', 'last_name' => 'NEWCOMER', 'complete_address' => 'BLK 2 / ARABAY 2 / BARANGAY ESTEFANIA / BACOLOD CITY',
            'contact_number' => '09171234567', 'height_cm' => '155.60', 'weight_kg' => null, 'blood_type' => null, 'religion' => 'CATHOLIC',
            'citizenship' => 'FILIPINO', 'sex' => 'Female',
        ], self::row('SELECT first_name, last_name, complete_address, contact_number, height_cm, weight_kg, blood_type, religion, citizenship, sex
                      FROM applicants_personal_information WHERE applicant_id = 20'));
    }

    public function testEducationAndFamilyAutosaveKeepHistory(): void
    {
        $c = self::signedIn('submitted@test.local', 'oldpass12');
        // Submitted applications aren't editable: nothing is saved.
        $this->assertSame(['success' => false, 'message' => 'This application can no longer be edited.'],
            self::autosave($c, ['step' => 'education', 'school_name' => 'X']));

        $d = self::signedIn('draft@test.local');
        $r = self::autosave($d, ['step' => 'education', 'school_name' => ' Riverside College ', 'year_level' => 'Bogus', 'course_name' => 'BSN',
            'general_average' => '1.234', 'previous_scholarship' => '1', 'previous_scholarship_details' => 'DOST']);
        $this->assertTrue($r['success']);
        $this->assertSame(['school_name' => 'Riverside College', 'year_level' => '1st Year', 'general_average' => '1.23', 'previous_scholarship' => 1],
            self::row('SELECT school_name, year_level, general_average, previous_scholarship FROM education_records WHERE applicant_id = 1'));
        $this->assertSame('Riverside College', self::value("SELECT new_value FROM record_history WHERE applicant_id = 1 AND field = 'school_name'"));

        $r = self::autosave($d, ['step' => 'family', 'father_income' => '9000', 'mother_income' => '-50', 'father_status' => 'Bogus', 'family_size' => '5']);
        $this->assertTrue($r['success']);
        $this->assertSame(['father_income' => '9000.00', 'mother_income' => '0.00', 'total_family_income' => '9000.00', 'father_status' => 'Available', 'family_size' => 5],
            self::row('SELECT father_income, mother_income, total_family_income, father_status, family_size FROM family_background WHERE applicant_id = 1'));
        $this->assertSame('9000.00', self::value("SELECT new_value FROM record_history WHERE applicant_id = 1 AND field = 'total_family_income'"));
    }

    public function testReturnedWithFlagsOnlyTheFlaggedFieldChanges(): void
    {
        $c = self::signedIn('returned@test.local');
        $r = self::autosave($c, ['step' => 'personal', 'first_name' => 'HACKED', 'last_name' => 'RETURNED', 'date_of_birth' => '2005-09-03',
            'address_unit' => '3', 'purok_id' => '3', 'contact_number' => '09998887777']);
        $this->assertTrue($r['success']);
        $this->assertSame(['first_name' => 'RITA', 'contact_number' => '09998887777'],
            self::row('SELECT first_name, contact_number FROM applicants_personal_information WHERE applicant_id = 3'));
        // Education has no flagged field: nothing is saved at all.
        self::autosave($c, ['step' => 'education', 'school_name' => 'Changed', 'course_name' => 'Changed']);
        $this->assertSame('Bacolod City College', self::value('SELECT school_name FROM education_records WHERE applicant_id = 3'));
    }

    public function testProfileUpdateAndPasswordChange(): void
    {
        $c = self::signedIn('waitlisted@test.local');
        $c->post('/applicant/profile', ['full_name' => 'Wes W. Waitlisted', 'contact_number' => '0917 000 1111'], [], [], '/applicant/profile');
        $this->assertSame('Profile updated.', self::flashAfter($c));
        $this->assertSame(['full_name' => 'Wes W. Waitlisted', 'contact_number' => '09170001111'],
            self::row('SELECT full_name, contact_number FROM users WHERE id = 10'));

        $c->post('/applicant/profile', ['full_name' => 'Wes W. Waitlisted', 'current_password' => 'wrong', 'new_password' => 'Brand#new1',
            'new_password_confirm' => 'Brand#new1'], [], [], '/applicant/profile');
        $this->assertSame('Current password incorrect.', self::flashAfter($c));
        $c->post('/applicant/profile', ['full_name' => 'Wes W. Waitlisted', 'current_password' => 'Test12345!', 'new_password' => 'Brand#new1',
            'new_password_confirm' => 'Brand#new1'], [], [], '/applicant/profile');
        $this->assertSame('Profile updated.', self::flashAfter($c));
        $this->assertTrue(password_verify('Brand#new1', (string)self::value('SELECT password_hash FROM users WHERE id = 10')));
    }

    public function testApplicantsOnlyOpenTheirOwnDocuments(): void
    {
        $theirs = (int)self::value("SELECT id FROM documents WHERE application_id = 2 AND file_path <> '' LIMIT 1");
        $own = (int)self::value("SELECT id FROM documents WHERE application_id = 8 AND file_path <> '' LIMIT 1");
        $c = self::signedIn('scholar@test.local');
        $this->assertSame(404, $c->get("/applicant/documents/view&id=$theirs")->status);
        $this->assertSame('Document not found.', $c->body);
        $path = self::$server->storage . '/' . substr((string)self::value('SELECT file_path FROM documents WHERE id = ?', [$own]), strlen('storage/'));
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");
        $this->assertSame(200, $c->get("/applicant/documents/view&id=$own")->status, 'their own document is served');
        $this->assertStringStartsWith('%PDF-', $c->body);
        $theirTerm = 999;
        $this->assertSame(404, $c->get("/applicant/term-documents/view&id=$theirTerm")->status);
    }

    public function testNotificationsListAndMarkRead(): void
    {
        $c = self::signedIn('scholar@test.local');
        $this->assertStringContainsString('Your 2nd Semester claim was received.', $c->get('/applicant/notifications')->body);
        $c->get('/applicant/notifications&mark_read=1');
        $this->assertSame(['ok' => true], $c->json());
        $this->assertSame(0, (int)self::value('SELECT COUNT(*) FROM notifications WHERE user_id = 11 AND is_read = 0'));
    }
}
