<?php
declare(strict_types=1);
// Router for PHP's built-in server (tests only): serves files from public/ as they are, and turns every other path into
// public/index.php?r=<path> — the same thing public/.htaccess does under Apache.
$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$public = dirname(__DIR__, 2) . '/public';
if ($path !== '/' && is_file($public . $path)) return false;
if (!isset($_GET['r']) && $path !== '/' && $path !== '/index.php') {
    parse_str('r=' . ltrim($path, '/'), $query);                       // "admin/application&id=3" → r=admin/application, id=3
    $_GET = $query + $_GET;
}
chdir($public);
require $public . '/index.php';
// ErrorAfterOutputTest starts its server with SK_TEST_FAULTS=1: fail here, after the page has been printed, on request.
if (getenv('SK_TEST_FAULTS') === '1') require __DIR__ . '/fault_after_output.php';
