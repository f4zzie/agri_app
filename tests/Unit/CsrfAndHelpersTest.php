<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CsrfAndHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = ['lang' => 'en'];
    }

    public function testTokenIsStableWithinASession(): void
    {
        $token = csrf_token();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertSame($token, csrf_token());
    }

    public function testValidation(): void
    {
        $token = csrf_token();

        self::assertTrue(csrf_is_valid($token));
        self::assertFalse(csrf_is_valid('wrong'));
        self::assertFalse(csrf_is_valid(null));
        self::assertFalse(csrf_is_valid(['array']));
    }

    public function testEmptyTokenNeverValidatesAgainstEmptySession(): void
    {
        // Regression: hash_equals('', '') is true, so a session without a
        // token used to accept a form posted with an empty token.
        self::assertFalse(csrf_is_valid(''));
    }

    public function testFlashMessagesAreReadOnce(): void
    {
        flash('success', 'Saved');

        self::assertSame('Saved', flash('success'));
        self::assertNull(flash('success'));
    }

    public function testFormatPrice(): void
    {
        self::assertSame('KSh 1,250.00 / kg', format_price('1250', 'kg'));
        self::assertSame('Negotiable', format_price(null, 'kg'));
    }

    public function testEscaping(): void
    {
        self::assertSame('&lt;script&gt;&quot;x&quot;&#039;', e('<script>"x"\''));
        self::assertSame('', e(null));
    }

    public function testCategoryNamesAreTranslated(): void
    {
        $_SESSION['lang'] = 'sw';
        self::assertSame('Mboga', t_category('Vegetables'));
        self::assertSame('Unknown thing', t_category('Unknown thing'));
        self::assertSame('—', t_category(null));
    }
}
