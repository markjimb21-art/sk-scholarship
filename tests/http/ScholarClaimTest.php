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
        $this->assertSame('Upload both your School ID and Grades first.', self::flashAfter($c));

        $c->post('/applicant/claim/upload', ['term_id' => 2, 'type' => 'Birth Certificate'], ['file' => [self::pdf('x.pdf'), 'application/pdf', 'x.pdf']], [], '/applicant/scholarship');
        $this->assertSame('Invalid document type.', self::flashAfter($c));

        foreach (['School ID', 'Grades'] as $type) {
            $c->post('/applicant/claim/upload', ['term_id' => 2, 'type' => $type], ['file' => [self::pdf("$type.pdf"), 'application/pdf', "$type.pdf"]], [], '/applicant/scholarship');
            $this->assertSame("$type uploaded.", self::flashAfter($c));
        }
        $this->assertEqualsCanonicalizing(['School ID', 'Grades'], array_keys(TermDocument::forTerm(2, 'claim')));
        $this->assertTrue(TermDocument::ready(2, 'claim'));
        $this->assertSame('School ID.pdf', Document::current(8, 'School ID')['original_filename'], 'the new upload is the current School ID');

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
