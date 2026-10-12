<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Jobs\SendPushNotification;
use App\Services\PushNotificationService;

/**
 * Pushes a "new order" notification to the owner devices watching the
 * categories involved in the order.
 */
class NotifyOwnersOfNewOrder
{
    public function handle(OrderPlaced $event): void
    {
        if (! PushNotificationService::isConfigured()) {
            return;
        }

        $recipients = PushNotificationService::recipientsFor($event->ownerTypes);

        if ($recipients === []) {
            return;
        }

        $sale = $event->sale;

        $items = $sale->items()->with('product')->get();
        $summary = $items->pluck('product.name')->take(3)->implode(', ');
        $extra = $items->count() > 3 ? ' +'.($items->count() - 3).' menu' : '';

        SendPushNotification::dispatch(
            $recipients,
            [
                'title' => '🔔 Pesanan baru! '.$sale->invoice_number,
                'body' => $summary.$extra.' · '.rupiah((float) $sale->total_amount),
                'tag' => 'order-'.$sale->invoice_number,
                'url' => route('owner.dashboard'),
                'vibrate' => [200, 100, 200, 100, 200],
            ]
        );
    }
}
