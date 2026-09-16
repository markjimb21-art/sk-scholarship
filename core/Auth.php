<?php
declare(strict_types=1);

final class Auth
{
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => isset($_SERVER['HTTPS']),
            ]);
            session_start();
        }
    }

    public static function user(): ?array {
        if (empty($_SESSION['user_id'])) return null;
        static $user = null;
        if ($user === null) {
            $stmt = Database::conn()->prepare(
                "SELECT u.*, r.role_name FROM users u
                 JOIN roles r ON r.id = u.role_id
                 WHERE u.id = ? AND u.is_active = 1 LIMIT 1"
            );
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
        return $user ?: null;
    }

    public static function check(): bool { return self::user() !== null; }

    public static function role(): ?string { return self::user()['role_name'] ?? null; }

    public static function hasRole(string ...$roles): bool {
        return in_array(self::role(), $roles, true);
    }

    public static function requireLogin(): void {
        if (!self::check()) redirect('login');
    }

    public static function requireRole(string ...$roles): void {
        self::requireLogin();
        if (!self::hasRole(...$roles)) {
            http_response_code(403);
            die('Access denied.');
        }
    }

    public static function login(string $email, string $password): array {
        $pdo = Database::conn();
        $stmt = $pdo->prepare(
            "SELECT u.*, r.role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) return ['ok' => false, 'error' => 'Invalid credentials.'];

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return ['ok' => false, 'error' => 'Account locked. Try again later.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $attempts = (int)$user['failed_attempts'] + 1;
            $lockUntil = null;
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
                $attempts = 0;
            }
            $pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?")
                ->execute([$attempts, $lockUntil, $user['id']]);
            return ['ok' => false, 'error' => 'Invalid credentials.'];
        }

        if (!$user['is_active']) return ['ok' => false, 'error' => 'Account deactivated.'];

        // success
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?")
            ->execute([$user['id']]);

        AuditLog::write('login', 'users', (int)$user['id'], 'User logged in');
        return ['ok' => true, 'user' => $user];
    }

    public static function logout(): void {
        if ($id = $_SESSION['user_id'] ?? null) {
            AuditLog::write('logout', 'users', (int)$id, 'User logged out');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function register(string $fullName, string $email, string $password, string $roleName = 'applicant'): array {
        $pdo = Database::conn();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) return ['ok' => false, 'error' => 'Email already registered.'];

        $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = ?");
        $roleStmt->execute([$roleName]);
        $role = $roleStmt->fetch();
        if (!$role) return ['ok' => false, 'error' => 'Invalid role.'];

        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare(
                "INSERT INTO users (role_id, full_name, email, password_hash, is_active)
                 VALUES (?, ?, ?, ?, 1)"
            );
            $ins->execute([$role['id'], $fullName, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int)$pdo->lastInsertId();

            if ($roleName === 'applicant') {
                $appCode = generate_code('APP');
                $pdo->prepare("INSERT INTO applicants (user_id, application_code) VALUES (?, ?)")
                    ->execute([$userId, $appCode]);
            }
            $pdo->commit();
            AuditLog::write('register', 'users', $userId, "New $roleName registered: $email");
            return ['ok' => true, 'user_id' => $userId];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => APP_DEBUG ? $e->getMessage() : 'Registration failed.'];
        }
    }
}