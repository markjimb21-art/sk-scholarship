<?php
declare(strict_types=1);

/**
 * Runs the real application on PHP's built-in web server, against the TEST database and a temporary storage folder,
 * with every PHP warning displayed and logged — so HTTP tests and the smoke script exercise the actual pages.
 */
final class TestServer
{
    /** @var resource|null */
    private $process = null;
    public readonly int $port;
    public readonly string $baseUrl;
    public readonly string $errorLog;
    public readonly string $storage;

    /** $env: environment variables to add or override (e.g. APP_ENV, SK_TEST_FAULTS). */
    public function __construct(private readonly array $env = [])
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');           // let the OS pick a free port
        $this->port = (int)substr(strrchr((string)stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        $this->baseUrl = "http://127.0.0.1:{$this->port}";
        $tmp = sys_get_temp_dir();
        $this->errorLog = "$tmp/sk_test_server_{$this->port}.log";
        $this->storage = "$tmp/sk_scholarship_test_storage";
    }

    public function start(): void
    {
        $root = dirname(__DIR__, 2);
        if (!is_dir($this->storage)) mkdir($this->storage, 0700, true);
        @unlink($this->errorLog);
        $env = array_merge(getenv(), [
            'DB_NAME' => TestDatabase::NAME,
            'APP_ENV' => 'development',            // show errors in the page so the smoke test can see them
            'BASE_URL' => $this->baseUrl,
            'STORAGE_PATH' => $this->storage,
            'MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => '9', 'MAIL_ENCRYPTION' => 'none', 'MAIL_USER' => '', 'MAIL_FROM' => 'noreply@test.local',
        ], $this->env);
        $cmd = [PHP_BINARY, '-d', 'display_errors=1', '-d', 'html_errors=0', '-d', 'error_reporting=-1',
                '-d', 'log_errors=1', '-d', 'error_log=' . $this->errorLog,
                '-S', "127.0.0.1:{$this->port}", '-t', "$root/public", "$root/tests/support/router.php"];
        $null = stripos(PHP_OS_FAMILY, 'Windows') === 0 ? 'NUL' : '/dev/null';
        $this->process = proc_open($cmd, [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes, $root, $env);
        if (!is_resource($this->process)) throw new RuntimeException('Could not start the test server.');
        for ($i = 0; $i < 100; $i++) {                                     // wait up to 10 s for it to listen
            $s = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.1);
            if ($s) { fclose($s); return; }
            usleep(100000);
        }
        $this->stop();
        throw new RuntimeException('The test server did not start on port ' . $this->port);
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        $this->process = null;
    }

    /** PHP warnings/notices/fatals the server logged (lines starting "PHP Warning" etc.). */
    public function phpErrors(): array
    {
        if (!is_file($this->errorLog)) return [];
        $lines = file($this->errorLog, FILE_IGNORE_NEW_LINES) ?: [];
        return array_values(array_filter($lines, static fn(string $l): bool =>
            (bool)preg_match('/PHP (Warning|Notice|Deprecated|Fatal error|Parse error|Recoverable fatal error)/', $l)));
    }

    public function __destruct() { $this->stop(); }
}
