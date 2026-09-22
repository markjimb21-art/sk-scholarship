<?php
declare(strict_types=1);

// Paths
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');        // LEGACY location of old documents (read-only fallback)
define('STORAGE_PATH', BASE_PATH . '/storage');
define('DOCUMENT_PATH', STORAGE_PATH . '/documents');   // PRIVATE document store, outside the web root
define('VIEW_PATH', BASE_PATH . '/app/views');

// Optional .env file (KEY=VALUE). Real environment variables always win.
(static function (): void {
    $file = BASE_PATH . '/.env';
    if (!is_file($file) || !is_readable($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $k)) continue;
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
            $v = substr($v, 1, -1);
        }
        if (getenv($k) === false) {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
})();

// Environment. Fails CLOSED: anything other than an explicit "development" is treated as production.
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_DEBUG', APP_ENV === 'development');

// URLs
define('BASE_URL', rtrim(getenv('BASE_URL') ?: 'http://localhost/sk-scholarship/public', '/'));

// Security
define('SESSION_LIFETIME', 3600 * 2);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);
define('CSRF_TOKEN_NAME', '_csrf');
define('PASSWORD_MIN_LENGTH', 8);
define('RESET_TOKEN_TTL_MINUTES', 60);

// File uploads
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_MIME', ['application/pdf','image/jpeg','image/png']);

// Error reporting: always report + log everything; only DISPLAY it in development.
error_reporting(E_ALL);
ini_set('log_errors', '1');
if (APP_DEBUG) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    $logDir = STORAGE_PATH . '/logs';
    if (is_dir($logDir) && is_writable($logDir)) {
        ini_set('error_log', $logDir . '/app.log');
    }
}

// Required PHP extensions (documented in README.md). Fail loudly instead of with an obscure fatal later.
$missingExt = array_values(array_filter(['pdo_mysql', 'mbstring', 'fileinfo', 'openssl'], static fn(string $e): bool => !extension_loaded($e)));
if ($missingExt) {
    error_log('Missing required PHP extension(s): ' . implode(', ', $missingExt));
    http_response_code(500);
    exit(APP_DEBUG ? 'Missing required PHP extension(s): ' . implode(', ', $missingExt) : 'Server configuration error.');
}

// Timezone
date_default_timezone_set('Asia/Manila');
