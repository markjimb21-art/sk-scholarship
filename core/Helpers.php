<?php
declare(strict_types=1);

function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

function redirect(string $path): void {
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }

function asset(string $path): string { return BASE_URL . '/assets/' . ltrim($path, '/'); }

function old(string $key, $default = '') { return $_SESSION['_old'][$key] ?? $default; }

function flash(string $type, string $message): void {
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function csrf_token(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrf_field(): string {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    $expected = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    $token    = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    // Both sides must be NON-EMPTY strings; an empty session token must never match an empty submitted token.
    if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '' || !hash_equals($expected, $token)) {
        http_response_code(419);
        die('CSRF token mismatch.');
    }
}

/**
 * JSON for embedding in <script> blocks AND in HTML attributes (onclick='f(<?= js_json($x) ?>)').
 * <, >, &, ' and " are all \u-escaped, so HTML entities such as &quot; can never be re-assembled by the browser.
 */
function js_json($value): string {
    $json = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
    return $json === false ? 'null' : $json;
}

function calculate_age(string $dob): int {
    try {
        $d = new DateTime($dob);
        return $d->diff(new DateTime('today'))->y;
    } catch (Exception $e) { return 0; }
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function generate_code(string $prefix): string {
    return strtoupper($prefix) . '-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
}