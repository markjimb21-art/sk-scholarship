<?php
declare(strict_types=1);

/**
 * "Current document" lookup across application documents and claim/renewal documents (seed: Scott, applicant 8).
 * School ID: application (90 days ago) → claim 1 (55) → renewal (40) → claim 2 (5 days ago, current).
 */
final class DocumentVersionsTest extends DatabaseTestCase
{
    public function testCurrentIsTheNewestCurrentVersionAcrossBothTables(): void
    {
        $sid = Document::current(8, 'School ID');
        $this->assertSame('term_documents', $sid['source_table']);
        $this->assertSame('school-id-3.pdf', $sid['original_filename']);

        $this->assertSame('enrollment.pdf', Document::current(8, 'Enrollment Form')['original_filename']);
        $residency = Document::current(8, 'Certificate of Residency');
        $this->assertSame('documents', $residency['source_table']);
        $this->assertSame('1.pdf', $residency['original_filename']);
    }

    public function testHistoryListsEveryVersionNewestFirst(): void
    {
        $names = array_column(Document::history(8, 'School ID'), 'original_filename');
        $this->assertSame(['school-id-3.pdf', 'school-id-2.pdf', 'school-id-1.pdf', '4.pdf'], $names);
    }

    public function testEmptyPlaceholdersAreNotDocuments(): void
    {
        $this->assertNull(Document::current(1, 'School ID'), 'the Draft only has empty placeholder rows');
        $this->assertSame(DOCUMENT_TYPES, Document::missingTypes(1));
        $this->assertSame([], Document::missingTypes(2));
    }
}
