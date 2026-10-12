<?php

namespace App\Support;

/**
 * Indonesian mobile number handling.
 *
 * Customers type whatever is convenient: "0812-3456-7890", "+62 812 3456 7890",
 * "62 81234567890". Everything is normalised to the local form "08xxxxxxxxxx" so
 * that the same person is stored under one key, which is what makes the
 * receivables list and the customer history work at all.
 *
 * Before this existed the checkout accepted anything, so the whole database
 * filled up with placeholder numbers like "000000" and the same real customer
 * could never be matched to their own orders.
 */
final class PhoneNumber
{
    /**
     * Local form: 0 + 8 + one non-zero digit + 7..11 digits (10 to 14 chars).
     */
    private const LOCAL_PATTERN = '/^08[1-9][0-9]{7,11}$/';

    /**
     * Reduce any accepted input to digits only, country code folded to "0".
     *
     * Returns null when the input is empty, is made only of zeros, or contains
     * a character that is not a phone-number character at all. Silently dropping
     * stray letters would turn "08123456789a" into a valid number.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // Only digits and the usual separators are acceptable. Anything else
        // (letters, "#", "@") means this is not a phone number.
        if (preg_match('/[^0-9+\-.\s()]/', $value) === 1) {
            return null;
        }

        $digits = preg_replace('/[+\-.\s()]/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        // Strip any leading zeros first, so 0062... and 62... both reduce to 62...
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return null;
        }

        // 628123456789 -> 081234567890
        if (str_starts_with($digits, '62')) {
            $digits = substr($digits, 2);

            if ($digits === '') {
                return null;
            }
        }

        return '0'.$digits;
    }

    public static function isValid(?string $value): bool
    {
        $normalized = self::normalize($value);

        return $normalized !== null && preg_match(self::LOCAL_PATTERN, $normalized) === 1;
    }

    /**
     * International form without separators, ready for wa.me links.
     */
    public static function toInternational(?string $value): ?string
    {
        if (! self::isValid($value)) {
            return null;
        }

        return '62'.substr((string) self::normalize($value), 1);
    }

    /**
     * Ready-to-use WhatsApp deep link, or null when the number cannot be dialled.
     */
    public static function whatsappLink(?string $value, string $message = ''): ?string
    {
        $international = self::toInternational($value);

        if ($international === null) {
            return null;
        }

        $link = 'https://wa.me/'.$international;

        if ($message !== '') {
            $link .= '?text='.rawurlencode($message);
        }

        return $link;
    }

    /**
     * tel: URI for the "call the customer" button, or null when undiallable.
     */
    public static function telLink(?string $value): ?string
    {
        $normalized = self::normalize($value);

        if ($normalized === null || ! self::isValid($normalized)) {
            return null;
        }

        return 'tel:'.$normalized;
    }
}
