<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Demo;
use PHPUnit\Framework\TestCase;

final class DemoTest extends TestCase
{
    private ?string $saved;

    protected function setUp(): void
    {
        $this->saved = $_ENV['DEMO_MODE'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->saved === null) {
            unset($_ENV['DEMO_MODE']);
        } else {
            $_ENV['DEMO_MODE'] = $this->saved;
        }
    }

    public function testDemoLoginsAreProtectedInDemoMode(): void
    {
        $_ENV['DEMO_MODE'] = '1';
        self::assertTrue(Demo::enabled());
        self::assertTrue(Demo::isProtectedAccount('farmer@agritrack.test'));
        self::assertTrue(Demo::isProtectedAccount(' Buyer@AgriTrack.test '));
        self::assertFalse(Demo::isProtectedAccount('someone@example.com'));
        self::assertFalse(Demo::isProtectedAccount(null));
    }

    public function testNothingIsProtectedOutsideDemoMode(): void
    {
        foreach (['0', '', 'true'] as $value) {
            $_ENV['DEMO_MODE'] = $value;
            self::assertFalse(Demo::enabled(), "DEMO_MODE={$value}");
            self::assertFalse(Demo::isProtectedAccount('farmer@agritrack.test'), "DEMO_MODE={$value}");
        }
        unset($_ENV['DEMO_MODE']);
        self::assertFalse(Demo::isProtectedAccount('buyer@agritrack.test'));
    }
}
