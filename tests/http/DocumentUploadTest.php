<?php
declare(strict_types=1);

/**
 * Upload validation (type, size, real content) through the real upload endpoint — DocumentStorage::store() only accepts
 * genuine HTTP uploads, so these go through the built-in server. Files land in the temporary STORAGE_PATH, never in
 * storage/.
 */
final class DocumentUploadTest extends DatabaseTestCase
{
    private static TestServer $server;
    private static HttpClient $draft;
    private static string $dir;

    public static function setUpBeforeClass(): void
    {
        self::$server = new TestServer();
        self::$server->start();
        self::$dir = sys_get_temp_dir() . '/sk_upload_fixtures';
        if (!is_dir(self::$dir)) mkdir(self::$dir);
        self::$draft = new HttpClient(self::$server->baseUrl);
        if (!self::$draft->login('draft@test.local', 'Test12345!')) throw new RuntimeException('draft@test.local could not sign in');
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
        self::rebuild();
        foreach (glob(self::$dir . '/*') ?: [] as $f) unlink($f);
        $stored = self::$server->storage . '/documents/1';                 // the Draft is application 1
        foreach (glob($stored . '/*') ?: [] as $f) unlink($f);
        if (is_dir($stored)) rmdir($stored);
    }

    private static function fixture(string $name, string $bytes): string
    {
        $path = self::$dir . '/' . $name;
        file_put_contents($path, $bytes);
        return $path;
    }

    private static function png(): string
    {
        $im = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($im);
        return (string)ob_get_clean();
    }

    /** Upload as the wizard does (AJAX → JSON answer). */
    private function upload(string $type, string $file, string $mime, string $name): array
    {
        self::$draft->post('/applicant/documents/upload', ['document_type' => $type], ['file' => [$file, $mime, $name]],
            ['X-Requested-With: XMLHttpRequest'], '/applicant/application');
        return self::$draft->json() ?? ['raw' => self::$draft->body];
    }

    public function testATextFileNamedPdfIsRefused(): void
    {
        $r = $this->upload('School ID', self::fixture('notes.pdf', "just some text\n"), 'application/pdf', 'notes.pdf');
        $this->assertSame(['success' => false, 'message' => 'Only PDF, JPG, or PNG allowed.'], $r);
    }

    public function testAFileOverFiveMegabytesIsRefused(): void
    {
        $big = self::fixture('big.pdf', '%PDF-1.4' . str_repeat('0', MAX_FILE_SIZE));
        $r = $this->upload('School ID', $big, 'application/pdf', 'big.pdf');
        $this->assertSame(['success' => false, 'message' => 'File too large (max 5MB).'], $r);
    }

    public function testAFakeJpegIsRefused(): void
    {
        $r = $this->upload('2x2 Photo', self::fixture('fake.jpg', "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 64)), 'image/jpeg', 'fake.jpg');
        $this->assertSame(['success' => false, 'message' => 'The file is not a valid PDF, JPG, or PNG.'], $r);
    }

    public function testAnEmptyFileIsRefused(): void
    {
        $r = $this->upload('School ID', self::fixture('empty.pdf', ''), 'application/pdf', 'empty.pdf');
        $this->assertFalse($r['success']);
    }

    public function testAnUnknownDocumentTypeIsRefused(): void
    {
        $r = $this->upload('Birth Certificate', self::fixture('ok1.pdf', "%PDF-1.4\n%%EOF\n"), 'application/pdf', 'ok1.pdf');
        $this->assertSame(['success' => false, 'message' => 'Invalid document type.'], $r);
    }

    public function testAValidPdfIsStoredAndBecomesTheCurrentVersion(): void
    {
        $r = $this->upload('School ID', self::fixture('school-id.pdf', "%PDF-1.4\n%%EOF\n"), 'application/pdf', 'school-id.pdf');
        $this->assertTrue($r['success'], json_encode($r));
        $this->assertSame('Uploaded', $r['document']['status_label']);
        $current = Document::current(1, 'School ID');
        $this->assertSame('school-id.pdf', $current['original_filename']);
        $path = (string)self::value('SELECT file_path FROM documents WHERE id = ?', [$r['document']['id']]);
        $this->assertStringStartsWith('storage/documents/1/', $path);
        $this->assertFileExists(self::$server->storage . '/documents/1/' . basename($path), 'stored in the temporary storage');
        $this->assertFileDoesNotExist(BASE_PATH . '/' . $path, 'never in the real storage/');
    }

    public function testAValidPngIsStored(): void
    {
        $r = $this->upload('2x2 Photo', self::fixture('photo.png', self::png()), 'image/png', 'photo.png');
        $this->assertTrue($r['success'], json_encode($r));
    }

    public function testTheServerLoggedNoPhpErrors(): void
    {
        $this->assertSame([], self::$server->phpErrors());
    }
}
