<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/AuditLog.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Notification.php';
require_once __DIR__ . '/../core/Otp.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Baseline security headers (also set in public/.htaccess; repeated here so they apply without mod_headers).
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Every page is built in this buffer and sent when the request ends, so an error half-way through can still throw the
// half-built page away and answer with a real 500 (otherwise the status is already sent as 200).
ob_start();

/** Discard what was printed, answer 500, show the error page. Never leak stack traces / SQL / paths outside development. */
$errorResponse = static function (string $details): void {
    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) http_response_code(500);
    if (APP_DEBUG) {
        echo '<pre>' . htmlspecialchars($details, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }
    require VIEW_PATH . '/errors/500.php';
};
set_exception_handler(static function (Throwable $e) use ($errorResponse): void {
    error_log(sprintf('Unhandled %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    $errorResponse((string)$e);
});
// Fatal errors (out of memory, time limit, …) never reach the exception handler; PHP has already logged them.
register_shutdown_function(static function () use ($errorResponse): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $errorResponse("{$error['message']} in {$error['file']}:{$error['line']}");
    }
});

Auth::start();

$route = $_GET['r'] ?? 'home';
$route = is_string($route) ? preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $route) : 'home';

// Public routes
$publicRoutes = ['home','login','register','logout','forgot-password','reset-password',
    'verify-otp','resend-otp','verify-reset-otp'];

if (!in_array($route, $publicRoutes, true) && !Auth::check()) {
    redirect('login');
}

switch ($route) {
    case 'home':
        if (Auth::check()) redirect(Auth::role() === 'applicant' ? 'applicant/dashboard' : 'admin/dashboard');
        require VIEW_PATH . '/auth/landing.php';
        break;

    case 'login':
        require __DIR__ . '/../app/controllers/AuthController.php';
        (new AuthController())->login();
        break;

    case 'register':
        require __DIR__ . '/../app/controllers/AuthController.php';
        (new AuthController())->register();
        break;

    case 'logout':
        Auth::logout();
        redirect('login');
        break;

    case 'forgot-password':
        require __DIR__ . '/../app/controllers/AuthController.php';
        (new AuthController())->forgotPassword();
        break;

    case 'verify-otp':
        require __DIR__ . '/../app/controllers/AuthController.php';
        (new AuthController())->verifyOtp();
        break;

    case 'resend-otp':
        require __DIR__ . '/../app/controllers/AuthController.php';
        (new AuthController())->resendOtp();
        break;

    case 'verify-reset-otp':
        require __DIR__ . '/../app/controllers/AuthController.php';
        (new AuthController())->verifyResetOtp();
        break;

    case 'reset-password':
        require __DIR__ . '/../app/controllers/AuthController.php';
        (new AuthController())->resetPassword();
        break;

    default:
        // Role-based area routing
        if (Auth::role() === 'applicant' && str_starts_with($route, 'applicant/')) {
            require __DIR__ . '/../app/controllers/ApplicantController.php';
            (new ApplicantController())->handle(substr($route, 10));
        } elseif (Auth::hasRole(...STAFF_ROLES) && str_starts_with($route, 'admin/')) {
            // Staff area; AdminController::ROUTES decides which page each staff role may open.
            require __DIR__ . '/../app/controllers/AdminController.php';
            (new AdminController())->handle(substr($route, 6));
        } else {
            not_found();
        }
}
