<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Sms;
use PHPUnit\Framework\TestCase;

/**
 * DEMO_MODE=1 must never send real email or SMS, even when SMTP_* and AT_*
 * are configured; without demo mode the same settings enable sending.
 */
final class DemoMessagingTest extends TestCase
{
    private const KEYS = ['DEMO_MODE', 'SMTP_USER', 'SMTP_PASS', 'SMTP_HOST', 'AT_USERNAME', 'AT_API_KEY'];

    /** @var array<string, mixed> */
    private array $savedEnv = [];
    private string $savedLog;
    private string $log;

    protected function setUp(): void
    {
        foreach (self::KEYS as $k) {
            $this->savedEnv[$k] = $_ENV[$k] ?? null;
        }
        $_ENV['SMTP_USER']   = 'someone@example.com';
        $_ENV['SMTP_PASS']   = 'secret';
        $_ENV['SMTP_HOST']   = '127.0.0.1'; // never reached in these tests
        $_ENV['AT_USERNAME'] = 'sandbox';
        $_ENV['AT_API_KEY']  = 'key';

        $this->log      = (string) tempnam(sys_get_temp_dir(), 'agri_log');
        $this->savedLog = (string) ini_get('error_log');
        ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->savedLog);
        @unlink($this->log);
        foreach ($this->savedEnv as $k => $v) {
            if ($v === null) {
                unset($_ENV[$k]);
            } else {
                $_ENV[$k] = $v;
            }
        }
    }

    public function testDemoModeSimulatesEmailAndSms(): void
    {
        $_ENV['DEMO_MODE'] = '1';

        self::assertFalse(mailConfigured(), 'registrations are auto-verified like without SMTP');
        self::assertFalse(sendEmail('victim@example.com', 'V', 'Hello', '<p>Hi</p>'));
        self::assertNull(Sms::fromEnv());
        self::assertFalse(sendSms('0712345678', 'Hello'));

        $log = (string) file_get_contents($this->log);
        self::assertStringContainsString('demo mode: email not sent: Hello', $log);
        self::assertStringContainsString('demo mode: SMS not sent', $log);
    }

    public function testSameSettingsEnableSendingOutsideDemoMode(): void
    {
        $_ENV['DEMO_MODE'] = '0';

        self::assertTrue(mailConfigured());
        self::assertInstanceOf(Sms::class, Sms::fromEnv());
    }
}
