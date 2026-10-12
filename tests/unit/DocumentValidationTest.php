<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** DocumentStorage::validate — the file's CONTENT decides: PDF, JPG or PNG only, not empty, at most MAX_FILE_SIZE (5 MB). */
final class DocumentValidationTest extends TestCase
{
    private static string $dir;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/sk_validate_fixtures';
        if (!is_dir(self::$dir)) mkdir(self::$dir);
    }

    public static function tearDownAfterClass(): void
    {
        foreach (glob(self::$dir . '/*') ?: [] as $f) unlink($f);
        rmdir(self::$dir);
    }

    private static function file(string $name, string $bytes): string
    {
        $path = self::$dir . '/' . $name;
        file_put_contents($path, $bytes);
        return $path;
    }

    private static function image(string $type): string
    {
        $im = imagecreatetruecolor(4, 4);
        ob_start();
        $type === 'png' ? imagepng($im) : imagejpeg($im);
        return (string)ob_get_clean();
    }

    public function testAcceptsRealPdfJpgAndPng(): void
    {
        $this->assertSame(['ok' => true, 'size' => 15, 'mime' => 'application/pdf'], DocumentStorage::validate(self::file('a.pdf', "%PDF-1.4\n%%EOF\n")));
        $this->assertSame('image/png', DocumentStorage::validate(self::file('a.png', self::image('png')))['mime']);
        $this->assertSame('image/jpeg', DocumentStorage::validate(self::file('a.jpg', self::image('jpg')))['mime']);
    }

    public function testTheNameDoesNotMatterOnlyTheContent(): void
    {
        $this->assertSame('application/pdf', DocumentStorage::validate(self::file('photo.png', "%PDF-1.4\n%%EOF\n"))['mime']);
        $this->assertSame(['ok' => false, 'error' => 'Only PDF, JPG, or PNG allowed.'], DocumentStorage::validate(self::file('notes.pdf', "just text\n")));
    }

    public function testRejectsEmptyTooLargeAndFakeFiles(): void
    {
        $this->assertSame(['ok' => false, 'error' => 'The uploaded file is empty.'], DocumentStorage::validate(self::file('empty.pdf', '')));
        $this->assertSame(['ok' => false, 'error' => 'File too large (max 5MB).'],
            DocumentStorage::validate(self::file('big.pdf', '%PDF-1.4' . str_repeat('0', MAX_FILE_SIZE))));
        $this->assertSame(['ok' => false, 'error' => 'The file is not a valid PDF, JPG, or PNG.'],
            DocumentStorage::validate(self::file('fake.jpg', "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 64))));
    }

    public function testExactlyTheLimitIsAllowed(): void
    {
        $this->assertTrue(DocumentStorage::validate(self::file('limit.pdf', '%PDF-' . str_repeat('0', MAX_FILE_SIZE - 5)))['ok']);
    }
}
