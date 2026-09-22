<?php
declare(strict_types=1);

final class AuthController
{
    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            $result = Auth::login($email, $password);
            if ($result['ok']) {
                $role = $result['user']['role_name'];
                redirect($role === 'applicant' ? 'applicant/dashboard' : 'admin/dashboard');
            }
            flash('danger', $result['error']);
            $_SESSION['_old'] = ['email' => $email];
            redirect('login');
        }
        require VIEW_PATH . '/auth/login.php';
        unset($_SESSION['_old']);
    }

    public function register(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();

            // Never keep passwords in the session, even for re-filling the form after a validation error.
            $keep = array_diff_key($_POST, ['password' => 1, 'confirm_password' => 1, CSRF_TOKEN_NAME => 1]);

            $v = new Validator($_POST);
            $v->required('full_name')->required('email')->email('email')
              ->required('password')->min('password', PASSWORD_MIN_LENGTH)
              ->required('confirm_password')->matches('password', 'confirm_password', 'Password confirmation');

            if ($v->fails()) {
                flash('danger', $v->firstError());
                $_SESSION['_old'] = $keep;
                redirect('register');
            }
            if (mb_strlen(trim((string)$_POST['full_name'])) > 150) {
                flash('danger', 'Full name is too long (max 150 characters).');
                $_SESSION['_old'] = $keep;
                redirect('register');
            }

            $result = Auth::register(
                trim((string)$_POST['full_name']),
                strtolower(trim((string)$_POST['email'])),
                (string)$_POST['password']
            );

            if (!$result['ok']) {
                flash('danger', $result['error']);
                $_SESSION['_old'] = $keep;
                redirect('register');
            }

            flash('success', 'Registration successful. Please log in.');
            redirect('login');
        }
        require VIEW_PATH . '/auth/register.php';
        unset($_SESSION['_old']);
    }

    public function forgotPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $pdo = Database::conn();
            $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Always respond the same (don't leak whether email exists)
            if ($user) {
                $ttl = (int)RESET_TOKEN_TTL_MINUTES;
                // Throttle: if a token was issued during the last minute, do not issue/send another one.
                $recent = $pdo->prepare(
                    "SELECT 1 FROM users WHERE id = ? AND reset_expires IS NOT NULL
                     AND reset_expires > DATE_ADD(NOW(), INTERVAL " . ($ttl - 1) . " MINUTE)"
                );
                $recent->execute([$user['id']]);

                if (!$recent->fetchColumn()) {
                    // 256-bit random token. Only its SHA-256 hash is stored, so a DB leak cannot be used to reset passwords.
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare(
                        "UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL $ttl MINUTE) WHERE id = ?"
                    )->execute([hash('sha256', $token), $user['id']]);

                    $link = url('reset-password?token=' . $token);
                    $text = "Hello {$user['full_name']},\n\nUse this link to reset your SK Scholarship password "
                          . "(valid for $ttl minutes and usable once):\n$link\n\n"
                          . "If you did not request this, you can ignore this email.";
                    $html = '<p>Hello ' . htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') . ',</p>'
                          . '<p>Use the link below to reset your SK Scholarship password (valid for ' . $ttl . ' minutes and usable once):</p>'
                          . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Reset my password</a></p>'
                          . '<p>If you did not request this, you can ignore this email.</p>';
                    Notification::sendMail($user['email'], $user['full_name'], 'Reset your SK Scholarship password', $html, $text);

                    // In-app notice WITHOUT the token/link (the token must not be persisted anywhere but the e-mail).
                    Notification::send((int)$user['id'], 'Password Reset Requested',
                        'A password reset link was sent to your e-mail address. If this was not you, contact the SK office.',
                        'warning');

                    AuditLog::write('password_reset_requested', 'users', (int)$user['id'], 'Reset link generated');
                }
            }

            flash('success', 'If your email is registered, you will receive a reset link shortly.');
            redirect('login');
        }

        require VIEW_PATH . '/auth/forgot_password.php';
    }

    public function resetPassword(): void
    {
        $token = $_GET['token'] ?? '';
        $pdo = Database::conn();
        $user = null;
        if (is_string($token) && preg_match('/^[0-9a-f]{64}$/', $token) === 1) {
            $stmt = $pdo->prepare(
                "SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() AND is_active = 1 LIMIT 1"
            );
            $stmt->execute([hash('sha256', $token)]);
            $user = $stmt->fetch();
        }

        if (!$user) {
            flash('danger', 'Reset link is invalid or expired.');
            redirect('login');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $pw = (string)($_POST['password'] ?? '');
            $cpw = (string)($_POST['confirm_password'] ?? '');

            if (mb_strlen($pw) < PASSWORD_MIN_LENGTH) {
                flash('danger', 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
                redirect('reset-password?token=' . urlencode($token));
            }
            if ($pw !== $cpw) {
                flash('danger', 'Passwords do not match.');
                redirect('reset-password?token=' . urlencode($token));
            }

            // Atomic one-time use: the WHERE clause re-checks the token, so two concurrent requests cannot both succeed.
            $upd = $pdo->prepare(
                "UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL, failed_attempts = 0, locked_until = NULL
                 WHERE id = ? AND reset_token = ? AND reset_expires > NOW()"
            );
            $upd->execute([password_hash($pw, PASSWORD_DEFAULT), $user['id'], hash('sha256', $token)]);
            if ($upd->rowCount() !== 1) {
                flash('danger', 'Reset link is invalid or expired.');
                redirect('login');
            }

            AuditLog::write('password_reset_completed', 'users', (int)$user['id'], 'Password reset via token');
            flash('success', 'Password reset successful. Please log in.');
            redirect('login');
        }

        require VIEW_PATH . '/auth/reset_password.php';
    }
}
