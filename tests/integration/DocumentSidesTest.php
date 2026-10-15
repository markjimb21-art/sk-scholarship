<?php
declare(strict_types=1);

/**
 * Front and back pictures (documents.side / term_documents.side): which documents need both sides, which version of each
 * side counts, and how a new side supersedes older versions. Seed: every upload is one file (side NULL), as before sides.
 */
final class DocumentSidesTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        self::rebuild();
    }

    private static function doc(?string $side = null, string $status = 'Submitted', int $id = 0, string $path = 'x.pdf'): array
    {
        return ['id' => $id, 'side' => $side, 'status' => $status, 'file_path' => $path];
    }

    public function testWhichDocumentsHaveAFrontAndABack(): void
    {
        $this->assertSame(['Front', 'Back'], Document::sidesFor('School ID'));
        $this->assertSame(['Front', 'Back'], Document::sidesFor('Form 138 / Grades'), 'the application\'s Form 138, any year level');
        $this->assertSame([null], Document::sidesFor('Grades'), 'claim grades: one file');
        $this->assertSame([null], Document::sidesFor('Enrollment Form'));
    }

    public function testTheNewestVersionOfEachSideCounts(): void
    {
        $keys = static fn(array $set) => array_map(static fn($d) => $d['id'], $set);
        $this->assertSame(['Front' => 2], $keys(Document::latestSides([self::doc(null, id: 1), self::doc('Front', id: 2)])),
            'a front replaces the one-file version');
        $this->assertSame(['Front' => 3, 'Back' => 2], $keys(Document::latestSides([self::doc('Front', id: 1), self::doc('Back', id: 2), self::doc('Front', id: 3)])));
        $this->assertSame(['' => 3], $keys(Document::latestSides([self::doc('Front', id: 1), self::doc('Back', id: 2), self::doc(null, id: 3)])),
            'one file replaces both sides');
    }

    public function testCompleteness(): void
    {
        $sides = ['Front', 'Back'];
        $this->assertTrue(Document::covers(['' => self::doc()], $sides), 'one file from before sides counts as both');
        $this->assertFalse(Document::covers(['Front' => self::doc('Front')], $sides));
        $this->assertTrue(Document::covers(['Front' => self::doc('Front'), 'Back' => self::doc('Back')], $sides));
        $this->assertFalse(Document::covers(['Front' => self::doc('Front'), 'Back' => self::doc('Back', 'Invalid')], $sides));
        $this->assertFalse(Document::covers(['Front' => self::doc('Front'), 'Back' => self::doc('Back', 'Resubmit')], $sides, ['Resubmit', 'Invalid']));
        $this->assertTrue(Document::covers(['Front' => self::doc('Front')], [null]), 'a one-file document takes any file');
        $this->assertFalse(Document::covers(['' => self::doc(path: '')], [null]), 'an empty placeholder is not a file');
    }

    public function testOldOneFileUploadsStillCountAsComplete(): void
    {
        $this->assertSame([], Document::missingTypes(2), 'seeded one-file School ID and Form 138');
        $this->assertTrue(TermDocument::ready(2, 'claim'));
    }

    public function testANewForm138FrontNeedsItsBackWhateverTheYearLevel(): void
    {
        // Application 2 (applicant 2, 2nd Year): a newer Form 138 front replaces the one-file version.
        Database::conn()->exec("INSERT INTO documents (application_id, document_type, side, file_path, original_filename, status, is_current)
                                VALUES (2, 'Form 138 / Grades', 'Front', 'storage/documents/2/f.pdf', 'f.pdf', 'Submitted', 1)");
        $this->assertSame(['Form 138 / Grades'], Document::missingTypes(2), 'the back of the Form 138 is missing');
    }

    public function testANewFrontSupersedesOnlyTheFrontAndTheOneFileVersion(): void
    {
        // Scott (applicant 8): current School ID = one file from claim 2 (term_documents). Add a Back, then a Front.
        $pdo = Database::conn();
        $add = static function (string $side) use ($pdo): int {
            $pdo->exec("INSERT INTO term_documents (term_id, purpose, type, side, file_path, original_filename, is_current)
                        VALUES (2, 'claim', 'School ID', '$side', 'storage/term-documents/2/$side.pdf', '$side.pdf', 1)");
            return (int)$pdo->lastInsertId();
        };
        $back = $add('Back');
        Document::supersede(8, 'School ID', 'term_documents', $back, 'Back');
        $this->assertSame(['Back'], array_keys(Document::current(8, 'School ID')), 'the back replaced the one-file version');

        $front = $add('Front');
        $this->assertSame(0, Document::supersede(8, 'School ID', 'term_documents', $front, 'Front'), 'nothing else on the front side');
        $current = Document::current(8, 'School ID');
        $this->assertSame([$front, $back], [(int)$current['Front']['id'], (int)$current['Back']['id']], 'the back stays current');
        $this->assertSame(['Front', 'Back'], array_values(array_filter(array_column(Document::history(8, 'School ID'), 'side'))));
    }
}
