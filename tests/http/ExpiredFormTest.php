<?php
declare(strict_types=1);

/**
 * A form whose token no longer matches the session (the page was open too long, or the user signed in/out in another
 * tab): nothing is saved, and the user lands back on the same form with a message and their fields kept — never a
 * bare "CSRF token mismatch." page. The session uses its own cookie name and folder, so other apps on the host can't
 * replace or clean it up.
 */
final class ExpiredFormTest extends HttpTestCase
{
    public function testAnExpiredRegisterFormGoesBackFilledIn(): void
    {
        $c = new HttpClient(static::$server->baseUrl);
        $c->get('/register');
        $c->post('/register', ['_csrf' => str_repeat('0', 64), 'full_name' => 'Mark Jim', 'email' => 'mark@example.com',
            'password' => 'Secret123!', 'confirm_password' => 'Secret123!'], [], ['Referer: ' . static::$server->baseUrl . '/register']);

        $this->assertSame(303, $c->status);
        $this->assertSame(static::$server->baseUrl . '/register', $c->location);
        $this->assertStringStartsWith('This page had expired', self::flashAfter($c));
        $page = $c->body;   // the page flashAfter() opened: the form shown once, filled in
        $this->assertStringContainsString('value="Mark Jim"', $page, 'the fields are kept');
        $this->assertStringNotContainsString('Secret123!', $page, 'passwords are never kept');
        $this->assertSame(0, (int)self::value("SELECT COUNT(*) FROM users WHERE email = 'mark@example.com'"), 'nothing was saved');
    }

    public function testAFormFromElsewhereGoesToTheHomePage(): void
    {
        $c = new HttpClient(static::$server->baseUrl);
        $c->get('/register');
        $c->post('/register', ['_csrf' => str_repeat('0', 64)], [], ['Referer: https://evil.example/register']);
        $this->assertSame(303, $c->status);
        $this->assertSame(static::$server->baseUrl . '/', $c->location);
    }

    public function testTheSessionHasItsOwnCookieAndFolder(): void
    {
        $c = new HttpClient(static::$server->baseUrl);
        $c->get('/register');
        $this->assertNotEmpty(glob(static::$server->storage . '/sessions/sess_*'), 'session files in the app\'s own folder');
        $this->assertStringContainsString(SESSION_NAME, (string)file_get_contents((fn() => $this->cookieFile)->call($c)));
    }
}
