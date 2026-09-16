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

Auth::start();

if (isset($_GET['debug_models'])) {
    header('Content-Type: text/plain');
    echo "Applicant::findByUserId: "; var_dump(Applicant::findByUserId(1));
    echo "Application::stats: "; print_r(Application::stats());
    echo "Document::pendingCount: " . Document::pendingCount() . "\n";
    echo "Interview::stats: "; print_r(Interview::stats());
    echo "Scholar::stats: "; print_r(Scholar::stats());
    exit;
}

$route = $_GET['r'] ?? 'home';
$route = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $route);

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