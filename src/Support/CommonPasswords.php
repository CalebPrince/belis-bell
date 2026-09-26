<?php
declare(strict_types=1);

namespace Belis\Support;

/**
 * A short built-in list of very common passwords, checked without any network call. CTL-AUTH-001 asks for a check
 * against known breached passwords: this list is a small local stand-in, NOT the full breached-password check.
 * A full check (for example the Have I Been Pwned range API) is a third-party call and needs an approved change.
 */
final class CommonPasswords
{
    private const LIST = [
        'password', 'password1', 'password12', 'password123', 'password1234', 'passw0rd123', 'p@ssw0rd123', 'qwerty1234',
        'qwertyuiop', 'qwerty12345', 'qwerty123456', '1234567890', '12345678910', '123456789012', '0123456789', '1q2w3e4r5t',
        '1qaz2wsx3edc', 'iloveyou123', 'iloveyou12', 'welcome123', 'welcome1234', 'letmein123', 'letmein1234', 'admin12345',
        'administrator', 'admin123456', 'changeme123', 'abcd123456', 'abc1234567', 'abcdefghij', 'asdfghjkl1', 'asdfghjklqwerty',
        'zxcvbnm123', 'monkey1234', 'dragon1234', 'football123', 'baseball123', 'superman123', 'trustno1234', 'sunshine123',
        'princess123', 'master12345', 'shadow12345', 'michael1234', 'jessica1234', 'charlie1234', 'login12345', 'freedom123',
        'whatever123', 'starwars123', 'computer123', 'internet123', 'ghana12345', 'ghana123456', 'accra123456', 'kumasi12345',
        'belisbell123', 'belis12345', 'belisbell1', 'cleaning123', 'secret12345', 'default1234', 'test1234567', 'testtest123',
        '1111111111', '2222222222', '0000000000', '1234512345', '9876543210', '1234567891', 'aaaaaaaaaa', 'qqqqqqqqqq',
    ];

    public static function contains(string $password): bool
    {
        return in_array(strtolower($password), self::LIST, true);
    }
}
