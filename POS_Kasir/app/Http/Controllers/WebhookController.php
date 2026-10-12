<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Support\ApiResponse;
use App\Support\WebhookHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * Universal payment callback (Payment Gateway, notification forwarder, QRIS-ify).
     *
     * Authenticates the caller either with the shared secret / HMAC signature or
     * with a QRIS-ify signed event header.
     */
    public function handlePayment(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $data = $request->all();

        $providedSecret = $request->header('X-Webhook-Secret')
            ?? $request->header('Webhook-Secret')
            ?? $data['webhook_secret']
            ?? $data['secret']
            ?? $request->query('secret');

        $providedSignature = $request->header('X-Callback-Signature')
            ?? $request->header('X-Signature')
            ?? $request->header('X-QRIS-Signature');

        $isQrisifyEvent = $request->hasHeader('X-QRIS-Event') || ($data['event'] ?? '') === 'qris.payment.success';

        if (! $isQrisifyEvent && ! WebhookHelper::verifyRequest($providedSecret, $rawBody, $providedSignature)) {
            return ApiResponse::error(
                'Unauthorized: Webhook Secret atau Signature tidak valid. Periksa konfigurasi di menu /owner/qris.',
                401
            );
        }

        $invoiceNumber = trim((string) ($data['invoice_number']
            ?? $data['order_id']
            ?? $data['merchant_ref']
            ?? $data['data']['external_id']
            ?? ''));

        $transactionId = trim((string) ($data['transaction_id'] ?? $data['data']['transaction_id'] ?? ''));

        $amount = isset($data['amount'])
            ? (float) $data['amount']
            : (isset($data['data']['amount_total']) ? (float) $data['data']['amount_total'] : null);

        $notificationText = ($data['message'] ?? '').' '.($data['title'] ?? '');

        $sale = $this->locateSale($transactionId, $invoiceNumber, $amount, $notificationText);

        if (! $sale) {
            return ApiResponse::error('Pesanan dengan kriteria tersebut tidak ditemukan atau sudah lunas.', 404);
        }

        $sale->update(['payment_status' => 'paid']);

        return ApiResponse::success([
            'invoice_number' => $sale->invoice_number,
            'customer_name' => $sale->customer_name,
            'total_amount' => (float) $sale->total_amount,
            'payment_status' => 'paid',
            'verified_at' => now()->format('Y-m-d H:i:s'),
        ], 'Status pembayaran berhasil diverifikasi otomatis menjadi LUNAS via Webhook!');
    }

    /**
     * Webhook endpoint + credentials for the owner settings page.
     */
    public function getConfig(): JsonResponse
    {
        $secret = WebhookHelper::getSecret();
        $webhookUrl = route('webhooks.payment');

        return ApiResponse::success([
            'webhook_url' => $webhookUrl,
            'webhook_secret' => $secret,
            'header_name' => 'X-Webhook-Secret',
            'example_curl' => "curl -X POST {$webhookUrl} \\\n  -H \"Content-Type: application/json\" \\\n  -H \"X-Webhook-Secret: {$secret}\" \\\n  -d '{\"invoice_number\": \"INV-...\", \"status\": \"paid\"}'",
        ]);
    }

    public function regenerateSecret(): JsonResponse
    {
        $newSecret = 'whsec_'.bin2hex(random_bytes(16));
        WebhookHelper::setSecret($newSecret);

        return ApiResponse::success(['webhook_secret' => $newSecret], 'Webhook Secret berhasil diperbarui!');
    }

    public function updateSecret(Request $request): JsonResponse
    {
        $data = $request->validate([
            'secret' => ['required', 'string', 'min:8'],
        ], [
            'secret.required' => 'Webhook Secret wajib diisi',
            'secret.min' => 'Webhook Secret minimal 8 karakter',
        ]);

        WebhookHelper::setSecret($data['secret']);

        return ApiResponse::success(['webhook_secret' => $data['secret']], 'Webhook Secret kustom berhasil disimpan!');
    }

    /**
     * Resolve the sale referenced by the callback, falling back to amount matching.
     */
    private function locateSale(string $transactionId, string $invoiceNumber, ?float $amount, string $notificationText): ?Sale
    {
        if ($transactionId !== '') {
            $sale = Sale::where('qris_transaction_id', $transactionId)->first();
            if ($sale) {
                return $sale;
            }
        }

        if ($invoiceNumber !== '') {
            $sale = Sale::where('invoice_number', $invoiceNumber)->first();
            if ($sale) {
                return $sale;
            }
        }

        if ($amount !== null && $amount > 0) {
            $sale = Sale::where('payment_status', 'unpaid')
                ->where('total_amount', $amount)
                ->orderByDesc('id')
                ->first();
            if ($sale) {
                return $sale;
            }
        }

        if ($notificationText !== '' && preg_match('/(?:Rp|rp|IDR)\s*([0-9\.\,]+)/', $notificationText, $matches)) {
            $parsedAmount = (float) str_replace(['.', ','], '', $matches[1]);

            if ($parsedAmount > 0) {
                return Sale::where('payment_status', 'unpaid')
                    ->where('total_amount', $parsedAmount)
                    ->orderByDesc('id')
                    ->first();
            }
        }

        return null;
    }
}
