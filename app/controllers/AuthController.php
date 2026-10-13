<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/User.php';

final class AuthController
{
    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $email = trim(post_str('email'));
            $password = post_str('password');

            // Empty fields: a friendly message (same words as the sign-in pop-up) instead of "Invalid credentials".
            // Only emptiness is checked here — the new-password rule never applies at login, so old passwords still work.
            if ($email === '' || $password === '') {
                $_SESSION['_field_errors'] = array_filter([
                    'email' => $email === '' ? 'Enter your email address.' : null,
                    'password' => $password === '' ? 'Enter your password.' : null,
                ]);
                flash('danger', 'Please enter your ' . implode(' and ', array_filter([
                    $email === '' ? 'email address' : null, $password === '' ? 'password' : null])) . '.');
                $_SESSION['_old'] = ['email' => $email];
                redirect('login');
            }

            $result = Auth::login($email, $password);

            if (!$result['ok'] && ($result['error'] ?? '') === 'unverified') {
                // Correct password, but the applicant never finished OTP verification. Re-issue a
                // code and send them straight to the verification screen rather than a dead end.
                $otp = $this->startOtp((int)$result['user_id'], 'registration');
                if (($otp['ok'] ?? false) && !empty($otp['email_sent'])) {
                    flash('warning', 'Please verify your e-mail first. We just sent you a new code.');
                } elseif (!($otp['ok'] ?? false) && isset($otp['wait'])) {
                    flash('warning', 'Please verify your e-mail first. A code was already sent — check your inbox, or wait ' . (int)$otp['wait'] . ' seconds to request another.');
                } else {
                    flash('danger', 'Please verify your e-mail first. We generated a new code, but could not send it — use "Resend code" on the next screen to try again.');
                }
                redirect('verify-otp');
            }

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
              ->required('password')->strongPassword('password')
              ->required('confirm_password', 'Confirm password')->matches('confirm_password', 'password', 'Password confirmation');

            if ($v->fails()) {
                $_SESSION['_field_errors'] = $v->errors();
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

            $userId = (int)$result['user_id'];
            Notification::send($userId, 'Account Created', 'Your ' . APP_NAME_SHORT . ' account has been created. Verify your e-mail to activate it.', 'info');
            $otp = $this->startOtp($userId, 'registration');
            if (!empty($otp['email_sent'])) {
                flash('success', 'Account created. We sent a 6-digit code to your e-mail — enter it below to verify your account.');
            } else {
                flash('warning', 'Account created, but we could not send the verification e-mail. Use "Resend code" on the next screen to try again.');
            }
            redirect('verify-otp');
        }
        require VIEW_PATH . '/auth/register.php';
        unset($_SESSION['_old']);
    }

    /* ================= OTP: registration ================= */

    /** Issue (or re-issue) an OTP and stash the pending user+purpose in the session for the verify screen. */
    private function startOtp(int $userId, string $purpose): array {
        $_SESSION['otp_user_id'] = $userId;
        $_SESSION['otp_purpose'] = $purpose;
        return $this->sendOtpEmail($userId, $purpose);
    }

    /**
     * Generates the OTP and attempts to e-mail it, then returns BOTH outcomes distinctly:
     *   ['ok'=>true,  'code'=>..., 'ttl_minutes'=>..., 'email_sent'=>true|false]
     *   ['ok'=>false, 'wait'=>seconds]                        (resend cooldown still active)
     * 'ok' reflects whether a code exists/was generated; 'email_sent' reflects whether PHPMailer
     * actually delivered it — callers must check both instead of assuming "generated" == "sent".
     */
    private function sendOtpEmail(int $userId, string $purpose): array {
        $result = Otp::issue($userId, $purpose);
        if (!$result['ok']) return $result; // cooldown active; caller decides what to show

        $emailSent = false;
        $user = User::contact($userId);
        if ($user) {
            $subject = $purpose === 'registration' ? 'Verify your ' . APP_NAME_SHORT . ' account' : 'Your password reset code';
            $intro = $purpose === 'registration'
                ? 'Use this code to verify your e-mail and activate your ' . APP_NAME_SHORT . ' account.'
                : 'Use this code to reset your ' . APP_NAME_SHORT . ' password.';
            $body = '<p>' . $intro . '</p>'
                  . '<p style="font-size:32px;font-weight:bold;letter-spacing:8px;color:#0d3b66;text-align:center;margin:24px 0;">' . e($result['code']) . '</p>'
                  . '<p>This code expires in ' . (int)$result['ttl_minutes'] . ' minutes and can only be used once. '
                  . 'If you did not request this, you can safely ignore this e-mail.</p>';
            $html = Notification::brand($subject, $user['full_name'], $body);
            $text = strip_tags($intro) . " Code: {$result['code']} (expires in {$result['ttl_minutes']} minutes)";
            $emailSent = Notification::sendMail($user['email'], $user['full_name'], $subject, $html, $text);
        }

        // Audit event and description now distinguish "generated" from "generated AND delivered" —
        // never claim "OTP sent" when PHPMailer actually failed. The OTP code itself is never logged.
        $prefix = $purpose === 'registration' ? 'registration_otp' : 'password_reset_otp';
        AuditLog::write(
            $emailSent ? "{$prefix}_sent" : "{$prefix}_email_failed",
            'users', $userId,
            $emailSent ? 'OTP generated and e-mailed successfully' : 'OTP generated, but e-mail delivery failed'
        );

        $result['email_sent'] = $emailSent;
        return $result;
    }

    public function verifyOtp(): void {
        $userId = (int)($_SESSION['otp_user_id'] ?? 0);
        $purpose = (string)($_SESSION['otp_purpose'] ?? '');
        if ($userId === 0 || $purpose !== 'registration') redirect('register');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $code = trim(post_str('otp'));
            $result = Otp::verify($userId, 'registration', $code);

            if ($result['ok']) {
                User::markEmailVerified($userId);
                AuditLog::write('registration_otp_verified', 'users', $userId, 'E-mail verified');
                Notification::send($userId, 'Email Verified', 'Your e-mail has been verified. Your ' . APP_NAME_SHORT . ' account is now active.', 'success', url('login'));
                unset($_SESSION['otp_user_id'], $_SESSION['otp_purpose']);
                flash('success', 'E-mail verified successfully. You can now log in.');
                redirect('login');
            }

            flash('danger', match ($result['error']) {
                'expired' => 'That code has expired. Request a new one below.',
                'locked'  => 'Too many incorrect attempts. Request a new code below.',
                'none'    => 'No active code found. Request a new one below.',
                default   => 'Incorrect code. Please try again.',
            });
            redirect('verify-otp');
        }

        $email = User::email($userId);
        require VIEW_PATH . '/auth/verify_otp.php';
    }

    public function resendOtp(): void {
        verify_csrf();
        $purpose = (string)($_POST['purpose'] ?? $_SESSION['otp_purpose'] ?? '');
        $userId = (int)($_SESSION['otp_user_id'] ?? 0);
        if ($userId === 0 || !in_array($purpose, ['registration', 'password_reset'], true)) {
            redirect('login');
        }

        $result = $this->sendOtpEmail($userId, $purpose);
        if (!$result['ok']) {
            flash('warning', 'Please wait ' . (int)$result['wait'] . ' seconds before requesting another code.');
        } elseif (!empty($result['email_sent'])) {
            flash('success', 'A new code has been sent to your e-mail.');
        } else {
            flash('danger', 'We generated a new code, but could not send it to your e-mail. Please try again in a moment.');
        }
        redirect($purpose === 'registration' ? 'verify-otp' : 'verify-reset-otp');
    }

    /* ================= OTP: forgot password ================= */

    public function forgotPassword(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $email = strtolower(trim(post_str('email')));
            $userId = User::activeIdByEmail($email);

            // Always the same response and the same redirect, whether or not the e-mail exists —
            // this alone never reveals account existence.
            if ($userId) {
                $_SESSION['otp_user_id'] = (int)$userId;
                $_SESSION['otp_purpose'] = 'password_reset';
                $this->sendOtpEmail((int)$userId, 'password_reset');
            } else {
                // No real user id to attach a code to; verify-reset-otp will just always report
                // "invalid or expired code" for this session, which looks identical to a real,
                // expired attempt.
                unset($_SESSION['otp_user_id']);
                $_SESSION['otp_purpose'] = 'password_reset';
            }

            flash('success', 'If that e-mail is registered, a verification code has been sent to it.');
            redirect('verify-reset-otp');
        }
        require VIEW_PATH . '/auth/forgot_password.php';
    }

    public function verifyResetOtp(): void {
        if (($_SESSION['otp_purpose'] ?? '') !== 'password_reset') redirect('forgot-password');
        $userId = (int)($_SESSION['otp_user_id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $code = trim(post_str('otp'));
            // No user id in session (unknown e-mail case) -> always report generic invalid/expired.
            $result = $userId > 0 ? Otp::verify($userId, 'password_reset', $code) : ['ok' => false, 'error' => 'none'];

            if ($result['ok']) {
                // Short-lived proof that this browser just verified the code; reset-password checks this.
                $_SESSION['otp_reset_verified_for'] = $userId;
                redirect('reset-password');
            }

            flash('danger', match ($result['error']) {
                'expired' => 'That code has expired. Request a new one below.',
                'locked'  => 'Too many incorrect attempts. Request a new code below.',
                default   => 'Invalid or expired code.',
            });
            redirect('verify-reset-otp');
        }

        require VIEW_PATH . '/auth/verify_reset_otp.php';
    }

    public function resetPassword(): void {
        // Only path: the OTP (verify-reset-otp) must have been verified in this session.
        $userId = (int)($_SESSION['otp_reset_verified_for'] ?? 0);
        if ($userId === 0) {
            flash('danger', 'Please verify your reset code first.');
            redirect('forgot-password');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $pw = post_str('password');
            $v = (new Validator($_POST))->newPassword('password', 'confirm_password');
            if ($v->fails()) {
                $_SESSION['_field_errors'] = $v->errors();
                flash('danger', $v->firstError());
                redirect('reset-password');
            }

            User::resetPassword($userId, $pw);

            AuditLog::write('password_reset_completed', 'users', $userId, 'Password reset via OTP');
            unset($_SESSION['otp_reset_verified_for'], $_SESSION['otp_user_id'], $_SESSION['otp_purpose']);

            if ($user = User::contact($userId)) {
                $html = Notification::brand('Your password was changed', $user['full_name'],
                    '<p>Your ' . APP_NAME_SHORT . ' account password was just changed. If this was not you, contact the SK office immediately.</p>');
                $confirmSent = Notification::sendMail($user['email'], $user['full_name'], 'Your ' . APP_NAME_SHORT . ' password was changed', $html,
                    'Your password was just changed. If this was not you, contact the SK office immediately.');
                // Password is already changed at this point regardless — a failed confirmation e-mail
                // must never block or roll back the password change itself, only be logged.
                AuditLog::write(
                    $confirmSent ? 'password_changed_email_sent' : 'password_changed_email_failed',
                    'users', $userId,
                    $confirmSent ? 'Password-changed confirmation e-mailed' : 'Password changed, but confirmation e-mail failed to send'
                );
            }

            flash('success', 'Password reset successful. Please log in.');
            redirect('login');
        }

        require VIEW_PATH . '/auth/reset_password.php';
    }
}
