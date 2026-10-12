<?php
declare(strict_types=1);

/** Base class for tests that drive the real pages: one test server per test class; data is rebuilt afterwards. */
abstract class HttpTestCase extends DatabaseTestCase
{
    protected static TestServer $server;

    public static function setUpBeforeClass(): void
    {
        self::rebuild();
        static::$server = new TestServer();
        static::$server->start();
    }

    public static function tearDownAfterClass(): void
    {
        static::$server->stop();
        self::rebuild();
    }

    protected static function signedIn(string $email, string $password = 'Test12345!'): HttpClient
    {
        $c = new HttpClient(static::$server->baseUrl);
        if (!$c->login($email, $password)) throw new RuntimeException("$email could not sign in");
        return $c;
    }

    /** The first flash message on the page the last request redirected to. */
    protected static function flashAfter(HttpClient $c): string
    {
        $path = (string)parse_url($c->location, PHP_URL_PATH) . (parse_url($c->location, PHP_URL_QUERY) ? '?' . parse_url($c->location, PHP_URL_QUERY) : '');
        $html = $c->get($path)->body;
        return preg_match('~<div class="alert[^"]*"[^>]*>.*?<div>(.*?)</div>~s', $html, $m) ? html_entity_decode(trim($m[1]), ENT_QUOTES) : '';
    }

    public function testTheServerLoggedNoPhpErrors(): void
    {
        $this->assertSame([], static::$server->phpErrors());
    }
}
