<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The one allowed-transition map (docs/PROCESS.md §1). The expected table is written out here on purpose, NOT read
 * from Application::TRANSITIONS, so an accidental edit to the map in code makes this test fail.
 */
final class ApplicationTransitionTest extends TestCase
{
    private const EXPECTED = [
        'submit'        => [['Draft', 'Returned'],                     'Submitted'],
        'qualify'       => [['Submitted'],                             'Qualified'],
        'return'        => [['Submitted'],                             'Returned'],
        'reject'        => [['Submitted', 'Interviewed', 'Waitlisted'], 'Rejected'],
        'assign'        => [['Qualified'],                             'Interview Scheduled'],
        'unassign'      => [['Interview Scheduled'],                   'Qualified'],
        'score'         => [['Interview Scheduled'],                   'Interviewed'],
        'no_show'       => [['Interview Scheduled'],                   'Qualified'],
        'no_show_final' => [['Interview Scheduled'],                   'Rejected'],
        'approve'       => [['Interviewed', 'Waitlisted'],             'Approved'],
        'waitlist'      => [['Interviewed'],                           'Waitlisted'],
    ];

    public function testTheMapHasExactlyTheDocumentedActions(): void
    {
        $this->assertSame(array_keys(self::EXPECTED), array_keys(Application::TRANSITIONS));
    }

    public static function everyActionAndStatus(): array
    {
        $cases = [];
        foreach (self::EXPECTED as $action => [$from]) {
            foreach (APP_STATUS as $status) $cases["$action from $status"] = [$action, $status, in_array($status, $from, true)];
        }
        return $cases;
    }

    #[DataProvider('everyActionAndStatus')]
    public function testCanAllowsOnlyTheDocumentedMoves(string $action, string $status, bool $allowed): void
    {
        $this->assertSame($allowed, Application::can($action, $status));
    }

    public function testEachActionLeadsToTheDocumentedStatus(): void
    {
        foreach (self::EXPECTED as $action => [, $to]) {
            $this->assertSame($to, Application::TRANSITIONS[$action]['to'], $action);
        }
    }

    public function testFinalStatusesAllowNothing(): void
    {
        foreach (['Approved', 'Rejected'] as $final) {
            foreach (array_keys(self::EXPECTED) as $action) $this->assertFalse(Application::can($action, $final), "$action from $final");
        }
    }

    public function testUnknownActionIsRefused(): void
    {
        $this->assertFalse(Application::can('approve_everyone', 'Submitted'));
    }
}
