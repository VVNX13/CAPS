<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reconciles unpaid QRIS orders against the gateway.
 *
 * The receipt page already polls the gateway, but only while that tab is open.
 * If a customer pays and then closes their phone, nothing would ever mark the
 * order as paid. This service closes that gap and is safe to run repeatedly.
 */
class QrisPaymentSyncService
{
    /**
     * QRIS-ify orders expire after 15 minutes, so there is no point asking the
     * gateway about anything older than this.
     */
    public const DEFAULT_WINDOW_MINUTES = 30;

    /**
     * Fetches a gateway transaction by id.
     *
     * @var callable(string): (array<string, mixed>|null)
     */
    private $fetcher;

    /**
     * @param  (callable(string): (array<string, mixed>|null))|null  $fetcher  Injected by tests so no
     *                                                                         test ever calls the live gateway.
     */
    public function __construct(?callable $fetcher = null)
    {
        $this->fetcher = $fetcher ?? static fn (string $transactionId) => QrisifyService::getTransaction($transactionId);
    }

    /**
     * @return array{checked: int, paid: int, failed: int, notified: int}
     */
    public function syncPending(?int $windowMinutes = null): array
    {
        $summary = ['checked' => 0, 'paid' => 0, 'failed' => 0, 'notified' => 0];

        if (! QrisifyService::isConfigured()) {
            return $summary;
        }

        $window = $windowMinutes ?? self::DEFAULT_WINDOW_MINUTES;

        $pending = Sale::query()
            ->where('payment_method', 'qris')
            ->where('payment_status', 'unpaid')
            ->whereNotNull('qris_transaction_id')
            ->where('created_at', '>=', now()->subMinutes($window))
            ->orderBy('id')
            ->get();

        foreach ($pending as $sale) {
            $summary['checked']++;

            try {
                $transaction = ($this->fetcher)($sale->qris_transaction_id);
            } catch (Throwable $e) {
                $summary['failed']++;
                Log::warning('QRIS sync failed', [
                    'invoice' => $sale->invoice_number,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }

            if (! QrisifyService::isPaid($transaction)) {
                continue;
            }

            $sale->update(['payment_status' => 'paid']);
            $summary['paid']++;

            $this->notifyOwners($sale, $summary);
        }

        return $summary;
    }

    /**
     * Tell the relevant owners that the money arrived, mirroring what a manual
     * confirmation would have done for the kitchen queue.
     */
    private function notifyOwners(Sale $sale, array &$summary): void
    {
        if (! PushNotificationService::isConfigured()) {
            return;
        }

        $ownerTypes = SaleItem::query()
            ->where('sale_id', $sale->id)
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->pluck('categories.owner_type')
            ->unique()
            ->values()
            ->all();

        $recipients = PushNotificationService::recipientsFor($ownerTypes);

        if ($recipients === []) {
            return;
        }

        SendPushNotification::dispatch(
            $recipients,
            [
                'title' => '💰 Pembayaran diterima!',
                'body' => $sale->invoice_number.' · '.rupiah((float) $sale->total_amount).' — QRIS terverifikasi otomatis.',
                'tag' => 'paid-'.$sale->invoice_number,
                'url' => route('owner.dashboard'),
            ]
        );

        $summary['notified']++;
    }
}
