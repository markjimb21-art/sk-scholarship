<?php
declare(strict_types=1);

/** A8 through the real pages: the scholar uploads School ID + Grades while the claim window is open, then submits. */
final class ScholarClaimTest extends HttpTestCase
{
    private static function pdf(string $name): string
    {
        $path = sys_get_temp_dir() . "/sk_claim_$name";
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");
        return $path;
    }

    public function testUploadBothDocumentsThenSubmitTheClaim(): void
    {
        // Seed: Scott's 2nd-semester claim already submitted; reopen it, and give the release window a future deadline.
        Database::conn()->exec("UPDATE scholar_terms SET claim_status = 'Waiting for Requirements', submitted_at = NULL WHERE id = 2");
        Database::conn()->exec("DELETE FROM term_documents WHERE term_id = 2");
        Database::conn()->exec("UPDATE term_schedules SET submission_deadline = NOW() + INTERVAL 3 DAY WHERE purpose = 'release' AND semester = '2nd Semester'");
        $c = self::signedIn('scholar@test.local');

        $c->post('/applicant/claim/submit', ['term_id' => 2], [], [], '/applicant/scholarship');
        $this->assertSame('Upload your School ID (front and back) and your college grades first.', self::flashAfter($c));

        $c->post('/applicant/claim/upload', ['term_id' => 2, 'type' => 'Birth Certificate'], ['file' => [self::pdf('x.pdf'), 'application/pdf', 'x.pdf']], [], '/applicant/scholarship');
        $this->assertSame('Invalid document type.', self::flashAfter($c));

        $c->post('/applicant/claim/upload', ['term_id' => 2, 'type' => 'School ID'], ['file' => [self::pdf('one.pdf'), 'application/pdf', 'one.pdf']], [], '/applicant/scholarship');
        $this->assertSame('Upload the front and the back of your School ID separately.', self::flashAfter($c));

        foreach ([['School ID', 'Front', 'School ID (front) uploaded.'], ['Grades', '', 'College grades (Certificate of Grades) uploaded.']] as [$type, $side, $flash]) {
            $c->post('/applicant/claim/upload', ['term_id' => 2, 'type' => $type, 'side' => $side], ['file' => [self::pdf("$type$side.pdf"), 'application/pdf', "$type$side.pdf"]], [], '/applicant/scholarship');
            $this->assertSame($flash, self::flashAfter($c));
        }
        $this->assertFalse(TermDocument::ready(2, 'claim'), 'the back of the School ID is still missing');

        // The page saves a file as soon as it is chosen (AJAX): the answer says whether the claim can now be submitted.
        $c->post('/applicant/claim/upload', ['term_id' => 2, 'type' => 'School ID', 'side' => 'Back'],
            ['file' => [self::pdf('School IDBack.pdf'), 'application/pdf', 'School IDBack.pdf']], ['X-Requested-With: XMLHttpRequest'], '/applicant/scholarship');
        $r = $c->json();
        $this->assertTrue($r['success'], $c->body);
        $this->assertTrue($r['ready']);
        $this->assertSame('Back', $r['document']['side']);

        $this->assertEqualsCanonicalizing(['School ID', 'Grades'], array_keys(TermDocument::forTerm(2, 'claim')));
        $this->assertSame(['Front', 'Back'], array_keys(TermDocument::forTerm(2, 'claim')['School ID']));
        $this->assertTrue(TermDocument::ready(2, 'claim'));
        $current = Document::current(8, 'School ID');
        $this->assertSame(['Front', 'Back'], array_keys($current), 'the new front and back replace the one-file School ID');
        $this->assertSame('School IDFront.pdf', $current['Front']['original_filename']);

        $c->post('/applicant/claim/submit', ['term_id' => 2], [], [], '/applicant/scholarship');
        $this->assertSame('Claim submitted. We will e-mail you when your documents are verified.', self::flashAfter($c));
        $this->assertSame('Submitted', self::value('SELECT claim_status FROM scholar_terms WHERE id = 2'));
    }

    public function testSomeoneElsesSemesterIsRefused(): void
    {
        $c = self::signedIn('draft@test.local');
        $c->post('/applicant/claim/submit', ['term_id' => 2], [], [], '/applicant/profile');   // a page with a form, for the CSRF token
        $this->assertSame('Semester not found.', self::flashAfter($c));
    }
}
