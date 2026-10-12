<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class QrisifyService
{
    public const DEFAULT_API_URL = 'https://qrisify.adihub.my.id/api/v1/transactions';

    public const DEFAULT_API_KEY = 'qris_live_WQmJC2QAcxUns8DwvG0qOyPiIhcoJNSJ';

    public const SETTING_API_KEY = 'qrisify_api_key';

    public const SETTING_ENABLED = 'qrisify_enabled';

    public static function getApiKey(): string
    {
        $key = Setting::get(self::SETTING_API_KEY);

        return ($key !== null && $key !== '') ? trim($key) : self::DEFAULT_API_KEY;
    }

    public static function setApiKey(string $key): bool
    {
        Setting::put(self::SETTING_API_KEY, trim($key));

        return true;
    }

    public static function isEnabled(): bool
    {
        $val = Setting::get(self::SETTING_ENABLED);

        return $val === null || $val === '1' || $val === 'true';
    }

    /**
     * True when the gateway is switched on and has a usable API key, i.e. when
     * automatic payment confirmation is possible at all.
     */
    public static function isConfigured(): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        return trim(self::getApiKey()) !== '';
    }

    public static function setEnabled(bool $enabled): bool
    {
        Setting::put(self::SETTING_ENABLED, $enabled ? '1' : '0');

        return true;
    }

    /**
     * Create a dynamic QRIS transaction through the QRIS-ify gateway.
     *
     * @return array<string, mixed>|null
     */
    public static function createTransaction(string $invoiceNumber, float $amount, ?string $webhookUrl = null, ?string $webhookSecret = null): ?array
    {
        $payload = [
            'amount' => (int) $amount,
            'external_id' => $invoiceNumber,
            'expiry_minutes' => 15,
        ];

        if (! empty($webhookUrl)) {
            $payload['webhook_url'] = $webhookUrl;
        }

        if (! empty($webhookSecret)) {
            $payload['webhook_secret'] = $webhookSecret;
        }

        try {
            $response = Http::withHeaders(self::requestHeaders())
                ->timeout(8)
                ->post(self::DEFAULT_API_URL, $payload);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $result = $response->json();

        return (! empty($result['success']) && ! empty($result['data'])) ? $result['data'] : null;
    }

    /**
     * Fetch the current state of a QRIS-ify transaction.
     *
     * @return array<string, mixed>|null
     */
    public static function getTransaction(string $transactionId): ?array
    {
        try {
            $response = Http::withHeaders(self::requestHeaders())
                ->timeout(6)
                ->get(self::DEFAULT_API_URL.'/'.urlencode($transactionId));
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $result = $response->json();

        return (! empty($result['success']) && ! empty($result['data'])) ? $result['data'] : null;
    }

    /**
     * Decide whether a gateway transaction means "money received".
     *
     * Shared by the receipt page poll and the background syncer so both agree on
     * what counts as paid.
     *
     * @param  array<string, mixed>|null  $transaction
     */
    public static function isPaid(?array $transaction): bool
    {
        if (empty($transaction)) {
            return false;
        }

        if (! empty($transaction['paid_at'])) {
            return true;
        }

        $status = strtoupper((string) ($transaction['status'] ?? ''));

        return in_array($status, ['SUCCESS', 'PAID', 'SETTLED'], true);
    }

    /**
     * @return array<string, string>
     */
    private static function requestHeaders(): array
    {
        return [
            'x-api-key' => self::getApiKey(),
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];
    }
}
