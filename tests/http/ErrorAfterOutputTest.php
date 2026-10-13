<?php
declare(strict_types=1);

/**
 * B7: an error that happens after the page has started printing must still end as HTTP 500 with only the error
 * page (or, in development, the trace) — not half a page with status 200. A test server started with SK_TEST_FAULTS=1
 * runs tests/support/fault_after_output.php after each page, which fails there when the request carries
 * "X-Test-Fault: exception" or "X-Test-Fault: fatal".
 */
final class ErrorAfterOutputTest extends HttpTestCase
{
    private const PAGE_LAYOUT = 'class="app-wrapper"';   // the signed-in layout; the error page uses the sign-in one

    public static function setUpBeforeClass(): void
    {
        self::rebuild();
        static::$server = new TestServer(['SK_TEST_FAULTS' => '1']);
        static::$server->start();
    }

    private static function dashboard(HttpClient $c, string $fault): HttpClient
    {
        return $c->get('/admin/dashboard', $fault === '' ? [] : ["X-Test-Fault: $fault"]);
    }

    public function testWithoutAFaultThePageIsServedAsUsual(): void
    {
        $r = self::dashboard(self::signedIn('admin@test.local'), '');
        $this->assertSame(200, $r->status);
        $this->assertStringContainsString(self::PAGE_LAYOUT, $r->body);
        $this->assertStringContainsString('</html>', $r->body);
    }

    public function testAnExceptionAfterThePageWasPrintedEndsAsA500(): void
    {
        $r = self::dashboard(self::signedIn('admin@test.local'), 'exception');
        $this->assertSame(500, $r->status);
        $this->assertStringContainsString('Injected test fault after the page was printed', $r->body, 'development shows the trace');
        $this->assertStringNotContainsString(self::PAGE_LAYOUT, $r->body, 'the half-printed page is thrown away');
    }

    public function testAFatalErrorAfterThePageWasPrintedEndsAsA500(): void
    {
        $r = self::dashboard(self::signedIn('admin@test.local'), 'fatal');
        $this->assertSame(500, $r->status);
        $this->assertStringContainsString('Injected fatal test fault', $r->body);
        $this->assertStringNotContainsString(self::PAGE_LAYOUT, $r->body);
    }

    public function testInProductionTheVisitorGetsOnlyTheErrorPage(): void
    {
        $server = new TestServer(['SK_TEST_FAULTS' => '1', 'APP_ENV' => 'production']);
        $server->start();
        try {
            $c = new HttpClient($server->baseUrl);
            $this->assertTrue($c->login('admin@test.local', 'Test12345!'));
            foreach (['exception', 'fatal'] as $fault) {
                $r = self::dashboard($c, $fault);
                $this->assertSame(500, $r->status, $fault);
                $this->assertStringContainsString('Something went wrong', $r->body, $fault);
                $this->assertStringNotContainsString(self::PAGE_LAYOUT, $r->body, $fault);
                $this->assertStringNotContainsString('Injected', $r->body, "$fault: no error details for visitors");
            }
        } finally {
            $server->stop();
        }
    }

    /** The injected fatal error is logged on purpose; anything else would be a real problem. */
    public function testTheServerLoggedNoPhpErrors(): void
    {
        $this->assertSame([], array_values(array_filter(static::$server->phpErrors(), static fn(string $l): bool => !str_contains($l, 'Injected'))));
    }
}
