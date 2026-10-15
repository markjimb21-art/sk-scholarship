<?php
declare(strict_types=1);

/**
 * B1: opening the Notifications page marks the user's notifications as read, through a POST with the CSRF token to a
 * URL built with url() — never a GET, never a hard-coded path — so the bell's count goes down.
 * Seed: scholar@test.local (user 11) and admin@test.local (user 1) each have 1 unread notification.
 */
final class NotificationsReadTest extends HttpTestCase
{
    private static function unread(int $userId): int
    {
        return (int)self::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    /** The page's mark-read hook: [url, csrf token] from its data-* attributes. */
    private static function hook(string $html): array
    {
        preg_match('/data-notifications[^>]*data-mark-read-url="([^"]*)"[^>]*data-csrf="([a-f0-9]{64})"/', $html, $m);
        return [$m[1] ?? '', $m[2] ?? ''];
    }

    public function testThePageCarriesTheHookAndTheBellShowsTheCount(): void
    {
        $page = self::signedIn('scholar@test.local')->get('/applicant/notifications')->body;
        [$url, $token] = self::hook($page);
        $this->assertSame(static::$server->baseUrl . '/applicant/notifications/mark-read', $url, 'built with url(), no fixed /sk-scholarship/public path');
        $this->assertNotSame('', $token);
        $this->assertMatchesRegularExpression('~<script src="[^"]*/js/pages/notifications\.js\?v=\d+"~', $page);
        $this->assertStringContainsString('aria-label="Notifications — 1 unread"', $page);

        $staff = self::signedIn('admin@test.local')->get('/admin/notifications')->body;
        $this->assertSame(static::$server->baseUrl . '/admin/notifications/mark-read', self::hook($staff)[0]);
    }

    public function testAGetRequestChangesNothing(): void
    {
        self::signedIn('scholar@test.local')->get('/applicant/notifications&mark_read=1');
        self::signedIn('admin@test.local')->get('/admin/notifications&mark_read=1');
        $this->assertSame([1, 1], [self::unread(11), self::unread(1)]);
    }

    public function testAPostWithoutTheTokenIsRefused(): void
    {
        $c = self::signedIn('scholar@test.local');
        $c->get('/applicant/notifications');
        // As the page's script sends it: a JSON refusal (403) the script can show.
        $c->post('/applicant/notifications/mark-read', ['_csrf' => str_repeat('0', 64)], [], ['X-Requested-With: XMLHttpRequest']);
        $this->assertSame(403, $c->status);
        $this->assertFalse($c->json()['success']);
        $this->assertSame(1, self::unread(11));
    }

    public function testAPostWithTheTokenMarksOnlyTheUsersOwnNotifications(): void
    {
        $c = self::signedIn('scholar@test.local');
        [$url, $token] = self::hook($c->get('/applicant/notifications')->body);
        $c->post(substr($url, strlen(static::$server->baseUrl)), ['_csrf' => $token]);
        $this->assertSame(200, $c->status);
        $this->assertSame(['ok' => true], $c->json());
        $this->assertSame(0, self::unread(11));
        $this->assertSame(1, self::unread(1), "someone else's notifications stay unread");
        $this->assertStringContainsString('aria-label="Notifications"', $c->get('/applicant/dashboard')->body, 'the bell count is gone');
    }

    public function testStaffMarkTheirsReadTheSameWay(): void
    {
        $c = self::signedIn('admin@test.local');
        [$url, $token] = self::hook($c->get('/admin/notifications')->body);
        $c->post(substr($url, strlen(static::$server->baseUrl)), ['_csrf' => $token]);
        $this->assertSame(['ok' => true], $c->json());
        $this->assertSame(0, self::unread(1));
    }
}
