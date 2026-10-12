<?php

namespace App\Support;

use App\Models\Setting;

class WebhookHelper
{
    public const SETTING_KEY = 'webhook_secret';

    /**
     * Get the shared webhook secret, generating one on first use.
     */
    public static function getSecret(): string
    {
        $secret = Setting::get(self::SETTING_KEY);

        if ($secret !== null && $secret !== '') {
            return trim($secret);
        }

        $newSecret = 'whsec_'.bin2hex(random_bytes(16));
        self::setSecret($newSecret);

        return $newSecret;
    }

    public static function setSecret(string $secret): bool
    {
        $secret = trim($secret);

        if ($secret === '') {
            return false;
        }

        Setting::put(self::SETTING_KEY, $secret);

        return true;
    }

    /**
     * Verify an incoming webhook by shared secret or HMAC-SHA256 signature.
     */
    public static function verifyRequest(?string $providedSecret, string $rawBody = '', ?string $signature = null): bool
    {
        $expectedSecret = self::getSecret();

        if ($providedSecret !== null && $providedSecret !== '' && hash_equals($expectedSecret, trim($providedSecret))) {
            return true;
        }

        if ($signature !== null && $signature !== '' && $rawBody !== '') {
            if (hash_equals(hash_hmac('sha256', $rawBody, $expectedSecret), trim($signature))) {
                return true;
            }
        }

        return false;
    }
}
