<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The new-password rule: at least PASSWORD_MIN_LENGTH (8) characters and at least 1 special character. */
final class PasswordRuleTest extends TestCase
{
    private const LENGTH  = 'at least 8 characters';
    private const SPECIAL = 'at least 1 special character (e.g. ! @ # $ %)';

    public static function passwords(): array
    {
        return [
            'letters and digits only'   => ['password1', [self::SPECIAL]],
            'with a special character'  => ['password1!', []],
            'special but too short'     => ['short!', [self::LENGTH]],
            'empty'                     => ['', [self::LENGTH, self::SPECIAL]],
            'exactly 8 with ~'          => ['abcdefg~', []],
            'digits only'               => ['12345678', [self::SPECIAL]],
            'a space is not special'    => ['pass word', [self::SPECIAL]],
            'accents count as length'   => ['ÑÑÑÑÑÑÑ!', []],
            '7 characters with special' => ['abcdef!', [self::LENGTH]],
        ];
    }

    #[DataProvider('passwords')]
    public function testPasswordProblems(string $password, array $expected): void
    {
        $this->assertSame($expected, password_problems($password));
    }

    public function testEveryPrintableAsciiPunctuationCharacterCounts(): void
    {
        foreach (str_split(PASSWORD_SPECIAL_CHARS) as $c) {
            $this->assertSame([], password_problems("abcdefg$c"), "special character $c");
        }
        $this->assertSame(32, strlen(PASSWORD_SPECIAL_CHARS));
    }

    public function testTheMessageNamesExactlyWhatFailed(): void
    {
        $this->assertSame('Your password needs at least 1 special character (e.g. ! @ # $ %).', password_rule_message([self::SPECIAL]));
        $this->assertSame('Your password needs at least 8 characters and at least 1 special character (e.g. ! @ # $ %).',
            password_rule_message([self::LENGTH, self::SPECIAL]));
    }

    public function testValidatorUsesTheSameRule(): void
    {
        $v = (new Validator(['password' => 'password1']))->strongPassword('password');
        $this->assertSame(['password' => 'Your password needs at least 1 special character (e.g. ! @ # $ %).'], $v->errors());
        $this->assertFalse((new Validator(['password' => 'password1!']))->strongPassword('password')->fails());
    }
}
