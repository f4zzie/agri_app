<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Phone;
use App\Sms;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneAndSmsTest extends TestCase
{
    #[DataProvider('numbers')]
    public function testNormalisesKenyanMobileNumbers(string $raw, ?string $expected): void
    {
        self::assertSame($expected, Phone::toKenyanInternational($raw));
    }

    public static function numbers(): array
    {
        return [
            'local with spaces'   => ['0712 345 678', '254712345678'],
            'international +'     => ['+254712345678', '254712345678'],
            'dashes'              => ['254-712-345678', '254712345678'],
            '01xx range'          => ['0110123456', '254110123456'],
            'missing leading 0'   => ['712345678', '254712345678'],
            'landline'            => ['020 1234567', null],
            'too short'           => ['12345', null],
            'foreign'             => ['+447700900123', null],
        ];
    }

    public function testWhatsappUrl(): void
    {
        self::assertSame('https://wa.me/254712345678?text=Habari%20yako', Phone::whatsappUrl('0712345678', 'Habari yako'));
        self::assertNull(Phone::whatsappUrl('not a phone'));
    }

    public function testSmsPostsToAfricasTalkingAndReadsTheResult(): void
    {
        $calls = [];
        $transport = static function (string $url, array $headers, array $form) use (&$calls): array {
            $calls[] = compact('url', 'headers', 'form');
            return ['SMSMessageData' => ['Recipients' => [['statusCode' => 101, 'status' => 'Success']]]];
        };

        $sms = new Sms('sandbox', 'key-123', null, $transport);

        self::assertTrue($sms->send('0712 345 678', 'New inquiry'));
        self::assertSame('https://api.sandbox.africastalking.com/version1/messaging', $calls[0]['url']);
        self::assertSame('key-123', $calls[0]['headers']['apiKey']);
        self::assertSame(['username' => 'sandbox', 'to' => '+254712345678', 'message' => 'New inquiry'], $calls[0]['form']);
    }

    public function testSmsReportsRejectedAndSkipsInvalidNumbers(): void
    {
        $calls = 0;
        $rejected = static function () use (&$calls): array {
            $calls++;
            return ['SMSMessageData' => ['Recipients' => [['statusCode' => 403, 'status' => 'InvalidPhoneNumber']]]];
        };
        $sms = new Sms('agritrack', 'k', 'AGRITRACK', $rejected);

        self::assertSame('https://api.africastalking.com/version1/messaging', $sms->endpoint());
        $prev = ini_set('error_log', '/dev/null');
        self::assertFalse($sms->send('0712345678', 'x'));
        ini_set('error_log', (string) $prev);
        self::assertFalse($sms->send('020 1234567', 'x'));
        self::assertSame(1, $calls, 'landline must not reach the API');
    }

    public function testSmsIsDisabledWithoutConfiguration(): void
    {
        $backup = $_ENV;
        unset($_ENV['AT_USERNAME'], $_ENV['AT_API_KEY']);
        self::assertNull(Sms::fromEnv());
        self::assertFalse(sendSms('0712345678', 'x'));
        $_ENV = $backup;
    }
}
