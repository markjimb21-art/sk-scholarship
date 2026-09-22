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

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Baseline security headers (also set in public/.htaccess; repeated here so they apply without mod_headers).
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Never leak stack traces / SQL / paths to the browser outside development.
set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf('Unhandled %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (APP_DEBUG) {
        echo '<pre>' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }
    require VIEW_PATH . '/errors/500.php';
});

Auth::start();

$route = $_GET['r'] ?? 'home';
$route = is_string($route) ? preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $route) : 'home';

// Public routes
$publicRoutes = ['home','login','register','logout','forgot-password','reset-password'];

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

    case 'reset-password':
        require __DIR__ . '/../app/controllers/AuthController.php';
        (new AuthController())->resetPassword();
        break;

    default:
        // Role-based area routing
        if (Auth::role() === 'applicant' && str_starts_with($route, 'applicant/')) {
            require __DIR__ . '/../app/controllers/ApplicantController.php';
            (new ApplicantController())->handle(substr($route, 10));
        } elseif (in_array(Auth::role(), ['admin','staff','official'], true) && str_starts_with($route, 'admin/')) {
            require __DIR__ . '/../app/controllers/AdminController.php';
            (new AdminController())->handle(substr($route, 6));
        } else {
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
        }
}
