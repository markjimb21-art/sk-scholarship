<?php
declare(strict_types=1);

// Environment
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', APP_ENV === 'development');

// Paths
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');
define('VIEW_PATH', BASE_PATH . '/app/views');

// URLs
define('BASE_URL', rtrim(getenv('BASE_URL') ?: 'http://localhost/sk-scholarship/public', '/'));

// Security
define('SESSION_LIFETIME', 3600 * 2);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);
define('CSRF_TOKEN_NAME', '_csrf');
define('PASSWORD_MIN_LENGTH', 8);

// File uploads
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_MIME', ['application/pdf','image/jpeg','image/png']);

// Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Timezone
date_default_timezone_set('Asia/Manila');