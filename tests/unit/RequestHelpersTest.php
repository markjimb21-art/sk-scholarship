<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** get_int()/post_str()/get_id()/post_bool()… and paginate() replaced expressions written out in the controllers; same results. */
final class RequestHelpersTest extends TestCase
{
    protected function tearDown(): void { $_GET = $_POST = []; }

    public static function values(): array
    {
        return [['missing'], [''], ['0'], ['12'], ['abc'], ['-3'], [' 7 '], ['2.9']];
    }

    #[DataProvider('values')]
    public function testInputHelpersMatchTheOldExpressions(string $v): void
    {
        $_GET = $_POST = $v === 'missing' ? [] : ['k' => $v];
        $this->assertSame((int)($_GET['k'] ?? 0), get_int('k'));
        $this->assertSame((int)($_POST['k'] ?? 0), post_int('k'));
        $this->assertSame((string)($_GET['k'] ?? ''), get_str('k'));
        $this->assertSame((string)($_POST['k'] ?? ''), post_str('k'));
        $this->assertSame(!empty($_GET['k']) ? (int)$_GET['k'] : null, get_id('k'));
        $this->assertSame(!empty($_POST['k']) ? (int)$_POST['k'] : null, post_id('k'));
        $this->assertSame(!empty($_POST['k']), post_bool('k'));
    }

    public static function pages(): array
    {
        return [[0, 'missing', 25], [24, '1', 25], [25, '2', 25], [26, '2', 25], [101, '9', 50], [10, '-4', 50], [10, 'x', 50]];
    }

    #[DataProvider('pages')]
    public function testPaginateMatchesTheOldArithmetic(int $total, string $pageParam, int $perPage): void
    {
        $_GET = $pageParam === 'missing' ? [] : ['page' => $pageParam];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $this->assertSame(['page' => $page, 'offset' => ($page - 1) * $perPage, 'pages' => max(1, (int)ceil($total / $perPage))],
            paginate($total, $perPage));
    }
}
