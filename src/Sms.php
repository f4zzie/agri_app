<?php

declare(strict_types=1);

namespace App;

/**
 * SMS notifications through Africa's Talking (https://africastalking.com).
 *
 * Many farmers don't check email, so new inquiries and replies are also
 * sent by SMS when AT_USERNAME and AT_API_KEY are set. Use username
 * "sandbox" with a sandbox API key to test without sending real messages.
 * Without configuration every call is a no-op that returns false.
 */
final class Sms
{
    private const SANDBOX_URL = 'https://api.sandbox.africastalking.com/version1/messaging';
    private const LIVE_URL    = 'https://api.africastalking.com/version1/messaging';

    /** @var callable(string $url, array<string,string> $headers, array<string,string> $form): ?array */
    private $transport;

    public function __construct(
        private string $username,
        private string $apiKey,
        private ?string $senderId = null,
        ?callable $transport = null
    ) {
        $this->transport = $transport ?? [self::class, 'curlPost'];
    }

    public static function fromEnv(): ?self
    {
        if (Demo::enabled()) {
            return null; // a public demo must not send SMS to visitor-entered numbers
        }
        $user = $_ENV['AT_USERNAME'] ?? '';
        $key  = $_ENV['AT_API_KEY'] ?? '';
        if ($user === '' || $key === '') {
            return null;
        }
        return new self($user, $key, ($_ENV['AT_SENDER_ID'] ?? '') ?: null);
    }

    /** Endpoint for this account (the "sandbox" username uses the sandbox API). */
    public function endpoint(): string
    {
        return $this->username === 'sandbox' ? self::SANDBOX_URL : self::LIVE_URL;
    }

    /**
     * Send one SMS. Returns true when Africa's Talking accepted it.
     */
    public function send(string $phone, string $message): bool
    {
        $intl = Phone::toKenyanInternational($phone);
        if ($intl === null) {
            return false;
        }

        $form = ['username' => $this->username, 'to' => '+' . $intl, 'message' => mb_substr($message, 0, 459)];
        if ($this->senderId !== null) {
            $form['from'] = $this->senderId;
        }

        $response = ($this->transport)(
            $this->endpoint(),
            ['apiKey' => $this->apiKey, 'Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded'],
            $form
        );

        $recipients = $response['SMSMessageData']['Recipients'] ?? [];
        $ok = isset($recipients[0]['statusCode']) && in_array((int) $recipients[0]['statusCode'], [100, 101, 102], true);
        if (!$ok) {
            error_log('AgriTrack SMS not accepted: ' . json_encode($response));
        }
        return $ok;
    }

    /** @param array<string,string> $headers @param array<string,string> $form */
    private static function curlPost(string $url, array $headers, array $form): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($form),
            CURLOPT_HTTPHEADER     => array_map(static fn ($k, $v) => "{$k}: {$v}", array_keys($headers), $headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        if ($body === false) {
            error_log('AgriTrack SMS transport error: ' . $err);
            return null;
        }
        $json = json_decode((string) $body, true);
        return is_array($json) ? $json : null;
    }
}
