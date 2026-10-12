<?php
declare(strict_types=1);

/**
 * Smoke test: rebuilds the TEST database, runs the application on PHP's built-in server, signs in as every kind of
 * user and requests every route in the route tables (AdminController::ROUTES, ApplicantController::ROUTES). Fails on any HTTP 500, PHP warning/notice/fatal (in the page or
 * the server log), and on any staff route that the access map says should be hidden but isn't (or the reverse).
 *
 *   php bin/smoke_test.php
 *
 * CLI only. It sets DB_NAME=sk_scholarship_test itself and refuses to run if DB_NAME is already set to anything else,
 * so it can never touch the real database. Uploads go to a temporary folder, never into storage/.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
require $root . '/tests/support/TestDatabase.php';
if (getenv('DB_NAME') !== false && getenv('DB_NAME') !== TestDatabase::NAME) {
    fwrite(STDERR, "Refusing to run: DB_NAME is '" . getenv('DB_NAME') . "', not '" . TestDatabase::NAME . "'.\n");
    exit(1);
}
$storage = sys_get_temp_dir() . '/sk_scholarship_test_storage';
foreach (['DB_NAME' => TestDatabase::NAME, 'APP_ENV' => 'testing', 'STORAGE_PATH' => $storage,
          'MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => '9', 'MAIL_USER' => ''] as $k => $v) {
    putenv("$k=$v");
    $_ENV[$k] = $v;
}

require $root . '/config/config.php';
require $root . '/config/database.php';
require $root . '/config/constants.php';
require $root . '/core/Helpers.php';
require $root . '/core/Auth.php';
require $root . '/core/AuditLog.php';
require $root . '/app/controllers/AdminController.php';
require $root . '/app/controllers/ApplicantController.php';
require $root . '/tests/support/TestServer.php';
require $root . '/tests/support/HttpClient.php';

TestDatabase::rebuild();
$pdo = Database::conn();

// The seeded document rows point at files; create them (tiny valid PDFs) in the temporary storage so the
// "view document" routes really serve a file.
foreach (["SELECT file_path FROM documents WHERE file_path <> ''", 'SELECT file_path FROM term_documents'] as $sql) {
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $rel) {
        $abs = $storage . '/' . substr((string)$rel, strlen('storage/'));
        if (!is_dir(dirname($abs))) mkdir(dirname($abs), 0700, true);
        file_put_contents($abs, "%PDF-1.4\n%%EOF\n");
    }
}

$id = static fn(string $sql): int => (int)$pdo->query($sql)->fetchColumn();
$ids = [
    'application'   => $id("SELECT id FROM applications WHERE status = 'Approved' LIMIT 1"),
    'document'      => $id("SELECT id FROM documents WHERE file_path <> '' ORDER BY id LIMIT 1"),
    'term'          => $id("SELECT id FROM scholar_terms WHERE claim_status = 'Submitted' LIMIT 1"),
    'term_document' => $id('SELECT id FROM term_documents ORDER BY id LIMIT 1'),
    'renewal'       => $id('SELECT id FROM renewal_requests LIMIT 1'),
    'scholar'       => $id('SELECT id FROM scholars LIMIT 1'),
    'schedule'      => $id("SELECT id FROM interview_schedules WHERE status = 'Open' ORDER BY interview_date DESC LIMIT 1"),
    'scored'        => $id("SELECT assignment_id FROM interview_evaluations LIMIT 1"),
    'qualified'     => $id("SELECT id FROM applications WHERE status = 'Qualified' LIMIT 1"),
];
$from = date('Y-m-01');
$to = date('Y-m-d', strtotime("$from +41 days"));

/** Query strings for the pages that need one (everything else is requested bare). */
$adminParams = [
    'application'          => 'id=' . $ids['application'],
    'documents/view'       => 'id=' . $ids['document'],
    'claims/view'          => 'id=' . $ids['term'],
    'term-documents/view'  => 'id=' . $ids['term_document'],
    'renewals/view'        => 'id=' . $ids['renewal'],
    'scholar'              => 'id=' . $ids['scholar'],
    'interview-schedule'   => 'id=' . $ids['schedule'],
    'interview-score'      => 'id=' . $ids['scored'],
    'interview-reschedule' => 'application_id=' . $ids['qualified'],
    'calendar-feed'        => "start=$from&end=$to",
];
$applicantParams = [
    'documents/view'      => ['scholar@test.local' => 'id=' . $id("SELECT id FROM documents WHERE application_id = {$ids['application']} AND file_path <> '' LIMIT 1")],
    'term-documents/view' => ['scholar@test.local' => 'id=' . $ids['term_document']],
];

$staffRoutes = array_keys((new ReflectionClassConstant(AdminController::class, 'ROUTES'))->getValue());
$applicantRoutes = array_keys(ApplicantController::ROUTES);

$server = new TestServer();
$server->start();
$failures = [];
$requests = 0;

$check = static function (HttpClient $c, string $who, string $path, ?array $expectStatus = null) use (&$failures, &$requests): void {
    $requests++;
    $c->get($path);
    $problems = [];
    if ($c->status >= 500) $problems[] = "HTTP {$c->status}";
    if ($expectStatus !== null && !in_array($c->status, $expectStatus, true)) $problems[] = "HTTP {$c->status}, expected " . implode('/', $expectStatus);
    if (preg_match('/\b(Warning|Notice|Deprecated|Fatal error|Parse error): .{0,300}? on line \d+/s', $c->body, $e)) $problems[] = trim($e[0]);
    if (preg_match('/Uncaught [A-Za-z\\\\]+/', $c->body, $e)) $problems[] = $e[0];
    // An exception thrown after the page has started is printed by the development error handler with HTTP 200
    // (the status can no longer change), so look for its stack trace too.
    if (preg_match('/([A-Za-z\\\\]*(Exception|Error)): (.{0,200}?) in \S+\.php:\d+\s+Stack trace:/s', $c->body, $e)) $problems[] = "{$e[1]}: {$e[3]}";
    if ($problems) $failures[] = sprintf('%-22s GET /%s → %s', $who, $path, implode('; ', $problems));
};

// Signed out: public pages; private pages redirect to Sign In.
$guest = new HttpClient($server->baseUrl);
foreach (['home', 'login', 'register', 'forgot-password', 'verify-otp', 'verify-reset-otp', 'reset-password'] as $r) $check($guest, 'signed out', $r);
$check($guest, 'signed out', 'admin/dashboard', [302]);
$check($guest, 'signed out', 'applicant/dashboard', [302]);

// Staff: every route in AdminController::ROUTES. Allowed → must not be 404; not allowed → must be exactly 404.
foreach (['admin' => 'admin@test.local', 'reviewer' => 'reviewer@test.local', 'treasurer' => 'treasurer@test.local'] as $role => $email) {
    $c = new HttpClient($server->baseUrl);
    if (!$c->login($email, 'Test12345!')) { $failures[] = "$role: could not sign in"; continue; }
    foreach ($staffRoutes as $route) {
        $path = 'admin/' . $route . (isset($adminParams[$route]) ? '&' . $adminParams[$route] : '');
        $allowed = AdminController::canAccess($route, $role);
        $check($c, $role, $path, $allowed ? [200, 302, 405, 419] : [404]);
    }
    $check($c, $role, 'applicant/dashboard', [404]);                 // staff can't open the applicant area
}

// Applicants: every route in ApplicantController::handle, as a scholar, a Draft applicant and a Returned one.
foreach (['scholar@test.local', 'draft@test.local', 'returned@test.local'] as $email) {
    $c = new HttpClient($server->baseUrl);
    if (!$c->login($email, 'Test12345!')) { $failures[] = "$email: could not sign in"; continue; }
    foreach ($applicantRoutes as $route) {
        $q = $applicantParams[$route][$email] ?? null;
        $check($c, $email, 'applicant/' . $route . ($q ? "&$q" : ''));
    }
    $check($c, $email, 'admin/dashboard', [404]);                    // applicants can't open the staff area
}

$server->stop();
foreach ($server->phpErrors() as $line) $failures[] = 'server log: ' . $line;

printf("Smoke test: %d requests (%d staff routes × 3 roles, %d applicant routes × 3 applicants, public pages).\n",
    $requests, count($staffRoutes), count($applicantRoutes));
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n  " . implode("\n  ", $failures) . "\n";
    exit(1);
}
echo "OK: no HTTP 500, no PHP warnings, access map as expected.\n";
exit(0);
