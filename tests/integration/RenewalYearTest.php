<?php
declare(strict_types=1);

/**
 * Renewal once per academic year (docs/CHANGE_PLAN_5.md §5): the 1st Semester release opens the 2nd Semester (D3),
 * a renewal is allowed once the 2nd Semester claim is Verified or Released (D2), the School ID is asked once per A.Y.
 * (D4, D14), and "End all as Did not renew" ends only scholars who could have renewed (D7).
 * Seed: Scott (scholar 1, applicant 8, user 11) is in his 2026-2027 2nd Semester of program 1 (claim Submitted);
 * program 2 is the 2027-2028 program he renews into. Applicants 6, 7 and 9 (users 9, 10, 12) own applications 6, 7, 9.
 */
final class RenewalYearTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        self::rebuild();
    }

    private static function dates(): array
    {
        return ['opens_at' => date('Y-m-d\TH:i', strtotime('-1 day')), 'submission_deadline' => date('Y-m-d\TH:i', strtotime('+5 days'))];
    }

    /** A claim/renewal document as TermDocument::upload saves it (the file itself isn't needed here). */
    private static function upload(int $termId, string $purpose, string $type, ?string $side = null): void
    {
        $pdo = Database::conn();
        $pdo->prepare("INSERT INTO term_documents (term_id, purpose, type, side, file_path, original_filename, file_size, mime_type, uploaded_at, is_current)
                       VALUES (?, ?, ?, ?, 'storage/term-documents/test.pdf', ?, 2048, 'application/pdf', NOW(), 1)")
            ->execute([$termId, $purpose, $type, $side, strtolower(str_replace(' ', '-', $type . ($side ? " $side" : ''))) . '.pdf']);
        $id = (int)$pdo->lastInsertId();   // before the next query resets it
        $applicantId = (int)self::value('SELECT s.applicant_id FROM scholar_terms t JOIN scholars s ON s.id = t.scholar_id WHERE t.id = ?', [$termId]);
        Document::supersede($applicantId, $type, 'term_documents', $id, $side);
    }

    /** Scott's 2nd Semester claim Verified, renewal window of program 2 open, renewal submitted and approved. Returns the new semester. */
    private static function renewScott(): array
    {
        Database::conn()->exec("UPDATE scholar_terms SET claim_status = 'Verified' WHERE id = 2");
        TermSchedule::save(['purpose' => 'renewal', 'program_id' => 2] + self::dates(), null, 1);
        self::upload(2, 'renewal', 'Enrollment Form');
        self::upload(2, 'renewal', 'School ID', 'Front');
        self::upload(2, 'renewal', 'School ID', 'Back');
        $r = Renewal::submit(Term::find(2), 11, '4th Year', '');
        if (!$r['ok']) throw new RuntimeException(json_encode($r));
        $r = Renewal::approve((int)Renewal::forTerm(2)['id'], 3);
        if (!$r['ok']) throw new RuntimeException(json_encode($r));
        return Term::current(1);
    }

    /* ---------------- D3: the 2nd Semester opens by itself ---------------- */

    public function testReleasingThe1stSemesterOpensThe2nd(): void
    {
        $this->assertTrue(Application::approve(6, 1)['ok']);   // Ivy: 2026-2027 1st Semester in program 1
        $sid = (int)self::value('SELECT id FROM scholars WHERE application_id = 6');
        $first = (int)self::value('SELECT id FROM scholar_terms WHERE scholar_id = ?', [$sid]);
        Database::conn()->exec("UPDATE scholar_terms SET claim_status = 'Verified' WHERE id = $first");
        $this->assertFalse(Renewal::appliesTo(Term::find($first)), 'no renewal between the two semesters');

        $r = Term::release($first, ['amount' => '5000', 'release_date' => date('Y-m-d'), 'payment_method' => 'Cash'], 3);
        $this->assertSame(['ok' => true, 'second_semester_opened' => true], $r);
        $second = self::row("SELECT id, program_id, academic_year, semester, opened_via, claim_status FROM scholar_terms WHERE scholar_id = ? AND id <> ?", [$sid, $first]);
        $this->assertSame(['program_id' => 1, 'academic_year' => '2026-2027', 'semester' => '2nd Semester',
                           'opened_via' => 'continuation', 'claim_status' => 'Waiting for Requirements'], array_slice($second, 1));
        $this->assertSame('Cash released — 2nd Semester open', self::value('SELECT title FROM notifications WHERE user_id = 9 ORDER BY id DESC LIMIT 1'));
        $this->assertSame('term_opened', self::value("SELECT action FROM audit_logs WHERE entity_type = 'scholar_terms' AND entity_id = ?", [$second['id']]));
        $this->assertFalse(Renewal::appliesTo(Term::find($first)), 'still nothing to renew from the 1st Semester');
    }

    public function testReleasingThe2ndSemesterOpensNothing(): void
    {
        Database::conn()->exec("UPDATE scholar_terms SET claim_status = 'Verified' WHERE id = 2");
        $r = Term::release(2, ['amount' => '5000', 'release_date' => date('Y-m-d'), 'payment_method' => 'Cash'], 3);
        $this->assertSame(['ok' => true, 'second_semester_opened' => false], $r);
        $this->assertSame(2, (int)self::value('SELECT COUNT(*) FROM scholar_terms WHERE scholar_id = 1'));
        $this->assertSame('Cash released', self::value('SELECT title FROM notifications WHERE user_id = 11 ORDER BY id DESC LIMIT 1'));
    }

    /* ---------------- D2: renew once the 2nd Semester claim is Verified ---------------- */

    public function testARenewalIsAllowedOnceThe2ndSemesterClaimIsVerified(): void
    {
        TermSchedule::save(['purpose' => 'renewal', 'program_id' => 2] + self::dates(), null, 1);
        self::upload(2, 'renewal', 'Enrollment Form');
        self::upload(2, 'renewal', 'School ID', 'Front');
        self::upload(2, 'renewal', 'School ID', 'Back');
        $this->assertFalse(Renewal::editable(Term::find(2)), 'the claim is only Submitted');
        $this->assertFalse(Renewal::submit(Term::find(2), 11, '4th Year', '')['ok']);

        Database::conn()->exec("UPDATE scholar_terms SET claim_status = 'Verified' WHERE id = 2");
        $term = Term::find(2);
        $this->assertTrue(Renewal::appliesTo($term));
        $this->assertTrue(Renewal::editable($term), 'Verified is enough — no need to wait for the release');
        $r = Renewal::submit($term, 11, '4th Year', '');
        $this->assertTrue($r['ok'], json_encode($r));
        $req = Renewal::forTerm(2);
        $this->assertSame([2, '2027-2028', '1st Semester'], [(int)$req['program_id'], $req['academic_year'], $req['semester']],
            'into the 1st Semester of the next A.Y., in that year\'s program');
    }

    public function testRenewingFrom2026_2027Into2027_2028(): void
    {
        $new = self::renewScott();
        $this->assertSame([2, '2027-2028', '1st Semester', 'renewal', 'Waiting for Requirements'],
            [(int)$new['program_id'], $new['academic_year'], $new['semester'], $new['opened_via'], $new['claim_status']]);
        $this->assertSame([2, 1], [(int)Scholar::find(1)['current_program_id'], (int)Scholar::find(1)['program_id']], 'current program 2, origin 1');
        $this->assertSame(['SCH-TEST-0001'], array_column(
            TermSchedule::affectedScholars(['purpose' => 'release', 'program_id' => 2, 'academic_year' => '2027-2028', 'semester' => '1st Semester']),
            'scholar_code'), 'program 2\'s claim window is his');
    }

    /* ---------------- D4: the School ID once per academic year ---------------- */

    public function testAClaimNeedsOnlyTheGradesWithASchoolIdOfTheSameYearOnFile(): void
    {
        $term = self::renewScott();   // the renewal's School ID was for 2027-2028
        $this->assertSame(['Grades'], Term::claimDocumentTypes($term));
        $this->assertSame(['Front', 'Back'], array_keys(Document::schoolIdOnFile(8, '2027-2028', (int)$term['id'])));

        TermSchedule::save(['purpose' => 'release', 'program_id' => 2, 'academic_year' => '2027-2028', 'semester' => '1st Semester'] + self::dates(), null, 1);
        $up = TermDocument::upload($term, 'claim', 'School ID', [], 'Front');
        $this->assertStringContainsString('already on file', $up['error']);
        $this->assertSame('Upload your college grades first.', Term::submitClaim($term, 11)['error']);

        self::upload((int)$term['id'], 'claim', 'Grades');
        $r = Term::submitClaim($term, 11);
        $this->assertTrue($r['ok'], json_encode($r));
        $term = Term::find((int)$term['id']);
        $this->assertSame('Submitted', $term['claim_status']);
        $this->assertSame(['Grades'], Term::claimDocumentsToShow($term, TermDocument::forTerm((int)$term['id'], 'claim')));
    }

    public function testAnApplicationSchoolIdCountsForTheProgramsYear(): void
    {
        $this->assertTrue(Application::approve(6, 1)['ok']);   // Ivy's application School ID is for program 1 = 2026-2027
        $term = Term::current((int)self::value('SELECT id FROM scholars WHERE application_id = 6'));
        $this->assertSame(['Grades'], Term::claimDocumentTypes($term));
    }

    public function testWithoutASchoolIdOfTheSameYearTheClaimNeedsIt(): void
    {
        // Scott's 2026-2027 2nd Semester: the current School ID is this claim's own upload, which doesn't count.
        $this->assertSame(['School ID', 'Grades'], Term::claimDocumentTypes(Term::find(2)));

        $term = self::renewScott();
        $pdo = Database::conn();
        // The School ID on file is from 2026-2027: not for this year.
        $pdo->exec("UPDATE term_documents SET is_current = 0 WHERE term_id = 2 AND purpose = 'renewal' AND type = 'School ID'");
        $pdo->exec("UPDATE term_documents SET is_current = 1 WHERE term_id = 2 AND purpose = 'claim' AND type = 'School ID'");
        $this->assertSame(['School ID', 'Grades'], Term::claimDocumentTypes($term), 'an ID of another A.Y. doesn\'t count');

        // This year's School ID, but the office asked for it again.
        $pdo->exec("UPDATE term_documents SET is_current = 0 WHERE term_id = 2 AND purpose = 'claim' AND type = 'School ID'");
        $pdo->exec("UPDATE term_documents SET is_current = 1, status = 'Resubmit' WHERE term_id = 2 AND purpose = 'renewal' AND type = 'School ID' AND side = 'Back'");
        $pdo->exec("UPDATE term_documents SET is_current = 1 WHERE term_id = 2 AND purpose = 'renewal' AND type = 'School ID'");
        $this->assertSame(['School ID', 'Grades'], Term::claimDocumentTypes($term), 'a School ID waiting for a re-upload doesn\'t count');
    }

    /* ---------------- D7: End all as Did not renew ---------------- */

    public function testEndAllAsDidNotRenewEndsOnlyScholarsWhoCouldHaveRenewed(): void
    {
        $pdo = Database::conn();
        $pdo->exec("UPDATE scholar_terms SET claim_status = 'Released' WHERE id = 2");   // Scott could have renewed
        $pdo->exec("INSERT INTO scholars (id, scholar_code, applicant_id, application_id, program_id, approval_date, status) VALUES
                    (2, 'SCH-DNR-IVY', 6, 6, 1, CURDATE(), 'Active'),
                    (3, 'SCH-DNR-WES', 7, 7, 1, CURDATE(), 'Active'),
                    (4, 'SCH-DNR-REMY', 9, 9, 1, CURDATE(), 'Active')");
        $pdo->exec("INSERT INTO scholar_terms (id, scholar_id, program_id, academic_year, semester, opened_via, claim_status) VALUES
                    (21, 2, 1, '2026-2027', '1st Semester', 'approval', 'Released'),
                    (22, 2, 1, '2026-2027', '2nd Semester', 'continuation', 'Verified'),
                    (31, 3, 1, '2026-2027', '1st Semester', 'approval', 'Verified'),
                    (41, 4, 1, '2026-2027', '1st Semester', 'approval', 'Released'),
                    (42, 4, 1, '2026-2027', '2nd Semester', 'continuation', 'Released')");
        // Remy renewed; the window into 2027-2028 (program 2) closed yesterday.
        $pdo->exec("INSERT INTO renewal_requests (scholar_id, program_id, from_term_id, academic_year, semester, status, submitted_at)
                    VALUES (4, 2, 42, '2027-2028', '1st Semester', 'Submitted', NOW() - INTERVAL 2 DAY)");
        $pdo->exec("INSERT INTO term_schedules (program_id, academic_year, semester, purpose, opens_at, submission_deadline, status, created_by)
                    VALUES (2, '2027-2028', '1st Semester', 'renewal', NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 1 DAY, 'Open', 1)");

        $list = Renewal::didNotRenew();
        $this->assertSame(['SCH-DNR-IVY', 'SCH-TEST-0001'], array_column($list['end'], 'scholar_code'), 'Ivy (Verified) and Scott (Released)');
        $this->assertSame(['SCH-DNR-WES'], array_column($list['wait'], 'scholar_code'), 'Wes is still in his 1st Semester');
        $this->assertSame([], array_values(array_filter(TermSchedule::missedDeadline('renewal'), static fn($m) => $m['schedule']['semester'] === '2nd Semester')),
            'the seeded window into 2026-2027 2nd Semester: nobody missed it — the 2nd Semester opens by itself now');

        // Ivy submits after the page was shown (a late renewal), before the admin clicks.
        $pdo->exec("INSERT INTO renewal_requests (scholar_id, program_id, from_term_id, academic_year, semester, status, submitted_at)
                    VALUES (2, 2, 22, '2027-2028', '1st Semester', 'Submitted', NOW())");
        $r = Renewal::endDidNotRenew([1, 2, 3], 1);
        $this->assertSame(['ended' => 1, 'skipped' => 2], $r);

        $status = static fn(int $id) => self::row('SELECT status, end_reason FROM scholars WHERE id = ?', [$id]);
        $this->assertSame(['status' => 'Ended', 'end_reason' => 'Did not renew'], $status(1));
        foreach ([2, 3, 4] as $id) $this->assertSame('Active', $status($id)['status'], "scholar $id");
        $this->assertSame('renewal_did_not_renew', self::value("SELECT action FROM audit_logs WHERE entity_type = 'scholars' ORDER BY id DESC LIMIT 1"));
        $this->assertSame(['end' => [], 'wait' => ['SCH-DNR-WES']],
            array_map(static fn($rows) => array_column($rows, 'scholar_code'), Renewal::didNotRenew()));
    }
}
