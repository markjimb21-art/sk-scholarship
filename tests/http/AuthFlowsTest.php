<?php
declare(strict_types=1);

/**
 * Registration → e-mail code → verified; forgot password → code → new password. The code is e-mailed in real life, so
 * the test replaces the stored hash with a code it knows.
 */
final class AuthFlowsTest extends HttpTestCase
{
    private static function setCode(int $userId, string $purpose, string $code): void
    {
        $stmt = Database::conn()->prepare('UPDATE email_otp_codes SET otp_hash = ? WHERE user_id = ? AND purpose = ?');
        $stmt->execute([password_hash($code, PASSWORD_DEFAULT), $userId, $purpose]);
    }

    public function testRegisterVerifyAndSignIn(): void
    {
        $c = new HttpClient(self::$server->baseUrl);
        $c->get('/register');
        $c->post('/register', ['full_name' => 'Vera Verify', 'email' => 'VERA@test.local', 'password' => 'Verify#123', 'confirm_password' => 'Verify#123']);
        $this->assertStringEndsWith('/verify-otp', $c->location);
        $uid = (int)self::value("SELECT id FROM users WHERE email = 'vera@test.local'");
        $this->assertSame(0, (int)self::value('SELECT email_verified FROM users WHERE id = ?', [$uid]));
        $this->assertStringContainsString('vera@test.local', $c->get('/verify-otp')->body, 'the verify page shows the e-mail');

        self::setCode($uid, 'registration', '123456');
        $c->post('/verify-otp', ['otp' => '000000']);
        $this->assertSame('Incorrect code. Please try again.', self::flashAfter($c));
        $c->post('/verify-otp', ['otp' => '123456'], [], [], '/verify-otp');
        $this->assertStringEndsWith('/login', $c->location);
        $this->assertSame(1, (int)self::value('SELECT email_verified FROM users WHERE id = ?', [$uid]));
        $this->assertTrue((new HttpClient(self::$server->baseUrl))->login('vera@test.local', 'Verify#123'));
    }

    public function testForgotPasswordResetsThePasswordAndUnlocks(): void
    {
        Database::conn()->exec('UPDATE users SET failed_attempts = 3, locked_until = NOW() + INTERVAL 5 MINUTE WHERE id = 7');
        $c = new HttpClient(self::$server->baseUrl);
        $c->get('/forgot-password');
        $c->post('/forgot-password', ['email' => 'Qualified@Test.Local']);
        $this->assertStringEndsWith('/verify-reset-otp', $c->location);
        self::setCode(7, 'password_reset', '654321');
        $c->post('/verify-reset-otp', ['otp' => '654321'], [], [], '/verify-reset-otp');
        $this->assertStringEndsWith('/reset-password', $c->location);

        $c->post('/reset-password', ['password' => 'short', 'confirm_password' => 'short'], [], [], '/reset-password');
        $this->assertSame('Your password needs at least 8 characters and at least 1 special character (e.g. ! @ # $ %).', self::flashAfter($c));
        $c->post('/reset-password', ['password' => 'Reset#2026', 'confirm_password' => 'Reset#2026'], [], [], '/reset-password');
        $this->assertStringEndsWith('/login', $c->location);
        $this->assertSame(['failed_attempts' => 0, 'locked_until' => null], self::row('SELECT failed_attempts, locked_until FROM users WHERE id = 7'));
        $this->assertTrue((new HttpClient(self::$server->baseUrl))->login('qualified@test.local', 'Reset#2026'));
    }

    public function testForgotPasswordForAnUnknownEmailLooksTheSame(): void
    {
        $c = new HttpClient(self::$server->baseUrl);
        $c->get('/forgot-password');
        $c->post('/forgot-password', ['email' => 'nobody@test.local']);
        $this->assertStringEndsWith('/verify-reset-otp', $c->location);
        $this->assertSame('If that e-mail is registered, a verification code has been sent to it.', self::flashAfter($c));
    }
}
