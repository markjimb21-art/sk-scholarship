<?php
declare(strict_types=1);

function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

/** UTF-8-safe uppercase + trim, for free-text fields that must be normalized server-side (never for emails/passwords/tokens). */
function up(?string $v): string { return mb_strtoupper(trim($v ?? ''), 'UTF-8'); }

/** Keeps digits only, capped at $max length — used to sanitize phone numbers before storage. */
function digits_only(?string $v, int $max = 11): string { return substr(preg_replace('/\D+/', '', $v ?? ''), 0, $max); }

function redirect(string $path): void {
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }

/** URL of a file in public/assets/, with ?v=<last modified> so browsers fetch it again after every change. */
function asset(string $path): string {
    $path = ltrim($path, '/');
    $file = PUBLIC_PATH . '/assets/' . $path;
    return BASE_URL . '/assets/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

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

/** Render a page inside the layout. $vars become local variables of the view. */
function render(string $view, array $vars = [], string $pageTitle = 'Dashboard'): void {
    extract($vars, EXTR_SKIP);
    require VIEW_PATH . '/layouts/header.php';
    require VIEW_PATH . '/' . $view . '.php';
    require VIEW_PATH . '/layouts/footer.php';
}

/** Every state-changing action: POST only (anything else goes back to $back) + CSRF check. */
function require_post(string $back): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect($back);
    verify_csrf();
}

/** One system_settings value (or $default when the row is missing). Read fresh each call: settings are few and small. */
function setting(string $key, ?string $default = null): ?string {
    $stmt = Database::conn()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false || $v === null ? $default : (string)$v;
}

/** Current admin's user id. */
function admin_id(): int {
    return (int)(Auth::user()['id'] ?? 0);
}

/* ---------- Password rule (docs/CHANGE_PLAN_UI.md §3): used wherever a password is CREATED or CHANGED, never at login ---------- */

/** What a new password is missing, as plain phrases; [] means it is fine. */
function password_problems(string $password): array {
    $problems = [];
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) $problems[] = 'at least ' . PASSWORD_MIN_LENGTH . ' characters';
    if (strpbrk($password, PASSWORD_SPECIAL_CHARS) === false) $problems[] = 'at least 1 special character (e.g. ! @ # $ %)';
    return $problems;
}

/** One friendly sentence naming exactly which parts failed, e.g. "Your password needs at least 1 special character (e.g. ! @ # $ %)." */
function password_rule_message(array $problems): string {
    return 'Your password needs ' . implode(' and ', $problems) . '.';
}

/** Live checklist under a new-password field (ui.js ticks it as the user types; the words carry the meaning, not color). */
function password_checklist(string $inputId): string {
    $item = static fn(string $rule, string $label): string =>
        '<li data-rule="' . $rule . '"><i aria-hidden="true"></i><span>' . e($label) . '</span><span class="visually-hidden" data-state> — not yet</span></li>';
    return '<ul class="pw-checklist" id="' . e($inputId) . '-rules" aria-live="polite">'
        . $item('length', 'At least ' . PASSWORD_MIN_LENGTH . ' characters')
        . $item('special', 'At least 1 special character (e.g. ! @ # $ %)')
        . '</ul>';
}

/** "Passwords match" line under a confirm-password field. */
function password_match_check(string $confirmId): string {
    return '<ul class="pw-checklist" id="' . e($confirmId) . '-match" aria-live="polite"><li data-rule="match"><i aria-hidden="true"></i>'
        . '<span>Passwords match</span><span class="visually-hidden" data-state> — not yet</span></li></ul>';
}

/** Attributes for a new-password input: the rule for ui.js + described by its checklist. */
function password_input_attrs(string $inputId): string {
    return 'id="' . e($inputId) . '" data-pw-rules data-pw-min="' . PASSWORD_MIN_LENGTH . '" data-pw-special="' . e(PASSWORD_SPECIAL_CHARS)
        . '" aria-describedby="' . e($inputId) . '-rules ' . e($inputId) . '-error" autocomplete="new-password"';
}

/* ---------- Inline field errors: red text + icon right under the field, linked by aria-describedby, announced ---------- */

/** Field errors from the last POST (set by a controller in $_SESSION['_field_errors']); read once per request. */
function field_errors(): array {
    static $errors = null;
    if ($errors === null) { $errors = $_SESSION['_field_errors'] ?? []; unset($_SESSION['_field_errors']); }
    return $errors;
}

/** ' is-invalid' when the field has an error (append to the input's class list). */
function field_invalid(string $field): string { return isset(field_errors()[$field]) ? ' is-invalid' : ''; }

/** The error line for a field, with id "<id>-error". Always rendered (hidden when empty) so ui.js can fill it in too. */
function field_error(string $field, string $id): string {
    $msg = field_errors()[$field] ?? '';
    return '<div class="field-error" id="' . e($id) . '-error" role="alert"' . ($msg === '' ? ' hidden' : '') . '>' . e($msg) . '</div>';
}
