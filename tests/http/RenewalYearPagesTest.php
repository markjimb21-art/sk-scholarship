<?php
declare(strict_types=1);

/**
 * Plan 5 through the real pages: a claim that needs only the Grades because this A.Y.'s School ID is on file (D4), and
 * "End all as Did not renew" on the Renewals page (D7). Seed: Scott (scholar 1) in 2026-2027 2nd Semester of program 1;
 * program 2 is 2027-2028.
 */
final class RenewalYearPagesTest extends HttpTestCase
{
    protected function tearDown(): void
    {
        self::rebuild();
    }

    private static function pdf(string $name): string
    {
        $path = sys_get_temp_dir() . "/sk_renew_$name";
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");
        return $path;
    }

    /** Scott renewed into 2027-2028 (program 2) with a new School ID; the claim window of that semester is open. */
    private static function scottRenewed(): void
    {
        $pdo = Database::conn();
        $pdo->exec("UPDATE scholar_terms SET claim_status = 'Released' WHERE id = 2");
        $pdo->exec("INSERT INTO renewal_requests (scholar_id, program_id, from_term_id, academic_year, semester, status, submitted_at)
                    VALUES (1, 2, 2, '2027-2028', '1st Semester', 'Approved', NOW() - INTERVAL 3 DAY)");
        $pdo->exec("INSERT INTO scholar_terms (id, scholar_id, program_id, academic_year, semester, opened_via, claim_status)
                    VALUES (3, 1, 2, '2027-2028', '1st Semester', 'renewal', 'Waiting for Requirements')");
        $pdo->exec("UPDATE term_documents SET is_current = 0 WHERE type = 'School ID'");
        $pdo->exec("INSERT INTO term_documents (term_id, purpose, type, side, file_path, original_filename, file_size, mime_type, status, uploaded_at, is_current) VALUES
                    (2, 'renewal', 'School ID', 'Front', 'storage/term-documents/2/f.pdf', 'id-front-2027.pdf', 2048, 'application/pdf', 'Verified', NOW() - INTERVAL 4 DAY, 1),
                    (2, 'renewal', 'School ID', 'Back',  'storage/term-documents/2/b.pdf', 'id-back-2027.pdf',  2048, 'application/pdf', 'Verified', NOW() - INTERVAL 4 DAY, 1)");
        $pdo->exec("INSERT INTO term_schedules (program_id, academic_year, semester, purpose, opens_at, submission_deadline, status, created_by)
                    VALUES (2, '2027-2028', '1st Semester', 'release', NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 5 DAY, 'Open', 1)");
    }

    public function testTheClaimAsksOnlyForTheGradesWhenThisYearsSchoolIdIsOnFile(): void
    {
        self::scottRenewed();
        $c = self::signedIn('scholar@test.local');
        $html = $c->get('/applicant/scholarship')->body;
        $this->assertStringContainsString('On file for 2027-2028', $html);
        $this->assertStringContainsString('id-front-2027.pdf', $html);
        $this->assertStringContainsString('Upload your college grades for 2027-2028 1st Semester', $html);
        $this->assertStringNotContainsString('3::claim::School ID::', $html, 'no School ID upload box on this claim');
        $this->assertStringContainsString('3::claim::Grades::', $html);

        $c->post('/applicant/claim/upload', ['term_id' => 3, 'type' => 'School ID', 'side' => 'Front'],
            ['file' => [self::pdf('id.pdf'), 'application/pdf', 'id.pdf']], [], '/applicant/scholarship');
        $this->assertSame('Your School ID for 2027-2028 is already on file — this claim needs only your college grades.', self::flashAfter($c));

        $c->post('/applicant/claim/upload', ['term_id' => 3, 'type' => 'Grades', 'side' => ''],
            ['file' => [self::pdf('grades.pdf'), 'application/pdf', 'grades.pdf']], ['X-Requested-With: XMLHttpRequest'], '/applicant/scholarship');
        $r = $c->json();
        $this->assertTrue($r['success'], $c->body);
        $this->assertTrue($r['ready'], 'the Grades are all this claim needs');

        $c->post('/applicant/claim/submit', ['term_id' => 3], [], [], '/applicant/scholarship');
        $this->assertSame('Claim submitted. We will e-mail you when your documents are verified.', self::flashAfter($c));

        $html = self::signedIn('treasurer@test.local')->get('/admin/claims/view&id=3')->body;
        $this->assertStringContainsString('School ID on file for 2027-2028', $html);
        $this->assertStringContainsString('id-back-2027.pdf', $html);
    }

    public function testEndAllAsDidNotRenew(): void
    {
        $pdo = Database::conn();
        $pdo->exec("UPDATE scholar_terms SET claim_status = 'Released' WHERE id = 2");
        $pdo->exec("INSERT INTO term_schedules (program_id, academic_year, semester, purpose, opens_at, submission_deadline, status, created_by)
                    VALUES (2, '2027-2028', '1st Semester', 'renewal', NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 1 DAY, 'Open', 1)");
        $c = self::signedIn('treasurer@test.local');
        $html = $c->get('/admin/renewals')->body;
        $this->assertStringContainsString('End all as Did not renew', $html);
        $this->assertMatchesRegularExpression('~name="scholar_ids\[\]" value="1"~', $html);

        $c->post('/admin/renewals/end-did-not-renew', ['scholar_ids[0]' => '1'], [], [], '/admin/renewals');
        $this->assertSame('1 scholarship(s) ended as Did not renew; each scholar was notified.', self::flashAfter($c));
        $this->assertSame(['status' => 'Ended', 'end_reason' => 'Did not renew'], self::row('SELECT status, end_reason FROM scholars WHERE id = 1'));

        // A stale page posting again (the token comes from another page: the list no longer has the button).
        $c->post('/admin/renewals/end-did-not-renew', ['scholar_ids[0]' => '1'], [], [], '/admin/renewal-schedule');
        $this->assertSame('No scholarship was ended. 1 skipped: they submitted a renewal or changed since the list was shown.',
            self::flashAfter($c), 'a second click ends nobody');
    }
}
