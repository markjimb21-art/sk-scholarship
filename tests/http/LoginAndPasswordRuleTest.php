<?php
declare(strict_types=1);

/**
 * Through the real pages: sign-in never applies the new-password rule (old passwords keep working), while
 * registration does. Runs the application on the built-in server against the test database.
 */
final class LoginAndPasswordRuleTest extends DatabaseTestCase
{
    private static TestServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = new TestServer();
        self::$server->start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
        self::rebuild();   // registration created an account
    }

    private function client(): HttpClient { return new HttpClient(self::$server->baseUrl); }

    public function testAnOldPasswordWithoutASpecialCharacterStillSignsIn(): void
    {
        $this->assertContains('at least 1 special character (e.g. ! @ # $ %)', password_problems('oldpass12'),
            'oldpass12 would fail the new-password rule');
        $c = $this->client();
        $this->assertTrue($c->login('submitted@test.local', 'oldpass12'));
        $this->assertStringEndsWith('/applicant/dashboard', $c->location);
    }

    public function testWrongPasswordIsRefused(): void
    {
        $c = $this->client();
        $this->assertFalse($c->login('submitted@test.local', 'oldpass13'));
        $this->assertStringEndsWith('/login', $c->location);
    }

    public function testEmptyFieldsGetTheFriendlyMessage(): void
    {
        $c = $this->client();
        $c->get('/login');
        $c->post('/login', ['email' => '', 'password' => '']);
        $this->assertStringEndsWith('/login', $c->location);
        $this->assertStringContainsString('Please enter your email address and password.', $c->get('/login')->body);
    }

    public function testRegistrationAppliesTheRule(): void
    {
        $c = $this->client();
        $c->get('/register');
        $c->post('/register', ['full_name' => 'New Person', 'email' => 'new.person@test.local', 'password' => 'password1', 'confirm_password' => 'password1']);
        $this->assertStringEndsWith('/register', $c->location);
        $this->assertStringContainsString('Your password needs at least 1 special character (e.g. ! @ # $ %).', $c->get('/register')->body);
        $this->assertSame(0, (int)self::value("SELECT COUNT(*) FROM users WHERE email = 'new.person@test.local'"));

        $c->post('/register', ['full_name' => 'New Person', 'email' => 'new.person@test.local', 'password' => 'password1!', 'confirm_password' => 'password1!']);
        $this->assertStringEndsWith('/verify-otp', $c->location);
        $this->assertSame(1, (int)self::value("SELECT COUNT(*) FROM users WHERE email = 'new.person@test.local'"));
    }

    public function testTheServerLoggedNoPhpErrors(): void
    {
        $this->assertSame([], self::$server->phpErrors());
    }
}
