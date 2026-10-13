<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * peso() and fmt_date()/fmt_datetime()/fmt_long_date() replaced the copies written out in views and models. Each must
 * give exactly what the code it replaced gave, for the same input.
 */
final class FormatHelpersTest extends TestCase
{
    public static function amounts(): array
    {
        return [[null], [''], [0], [5000], [5000.5], ['5000.00'], ['1234567.891'], [-250.255], [0.005], ['0']];
    }

    #[DataProvider('amounts')]
    public function testPesoMatchesTheOldCode(float|int|string|null $v): void
    {
        $plain = static fn($v) => '₱' . number_format((float)$v, 2);                                       // budget, releases, inline
        $dash = static fn($v) => $v === null || $v === '' ? '—' : '₱' . number_format((float)$v, 2);     // application view, review
        $noBudget = static fn($v) => $v === null ? 'No budget' : '₱' . number_format((float)$v, 2);      // program summary report

        $this->assertSame($plain($v), peso($v));
        $this->assertSame($dash($v), peso($v, '—'));
        if ($v !== '') $this->assertSame($noBudget($v), peso($v, 'No budget'));   // '' never reaches the report (DECIMAL/SUM)
    }

    public function testPesoExamples(): void
    {
        $this->assertSame('₱1,234.50', peso(1234.5));
        $this->assertSame('₱0.00', peso(null));
        $this->assertSame('—', peso(null, '—'));
    }

    public static function dates(): array
    {
        return [['2026-10-05'], ['2026-10-05 15:30:00'], ['2026-01-31 00:00:00'], ['2026-12-31T23:59'], ['2027-02-28 12:00:00']];
    }

    #[DataProvider('dates')]
    public function testDateFormatsMatchTheOldCode(string $v): void
    {
        $this->assertSame(date('M d, Y', strtotime($v)), fmt_date($v));
        $this->assertSame(date('M d, Y g:i A', strtotime($v)), fmt_datetime($v));
        $this->assertSame(date('F j, Y', strtotime($v)), fmt_long_date($v));
    }

    public function testDateExamples(): void
    {
        $this->assertSame('Oct 05, 2026', fmt_date('2026-10-05'));
        $this->assertSame('Oct 05, 2026 3:30 PM', fmt_datetime('2026-10-05 15:30:00'));
        $this->assertSame('October 5, 2026', fmt_long_date('2026-10-05'));
    }
}
