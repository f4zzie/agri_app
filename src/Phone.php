<?php

declare(strict_types=1);

namespace App;

/**
 * Kenyan phone number helpers.
 */
final class Phone
{
    /**
     * Normalise a Kenyan mobile number to international digits without "+",
     * e.g. "0712 345 678", "+254712345678", "254-712-345678" -> "254712345678".
     * Accepts Safaricom/Airtel/Telkom ranges (07xx and 01xx). Returns null
     * for anything that is not a Kenyan mobile number.
     */
    public static function toKenyanInternational(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (preg_match('/^0([71]\d{8})$/', $digits, $m)) {
            return '254' . $m[1];
        }
        if (preg_match('/^254([71]\d{8})$/', $digits, $m)) {
            return '254' . $m[1];
        }
        if (preg_match('/^([71]\d{8})$/', $digits, $m)) {
            return '254' . $m[1];
        }
        return null;
    }

    /** WhatsApp click-to-chat URL, or null if the number isn't usable. */
    public static function whatsappUrl(?string $raw, string $message = ''): ?string
    {
        $intl = self::toKenyanInternational($raw);
        if ($intl === null) {
            return null;
        }
        return 'https://wa.me/' . $intl . ($message !== '' ? '?text=' . rawurlencode($message) : '');
    }
}
