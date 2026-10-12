<?php

namespace App\Jobs;

use App\Services\QrisPaymentSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Periodically reconciles unpaid QRIS orders with the gateway.
 *
 * The job re-schedules itself after every run, so the existing `queue:work`
 * process is all that is needed - no second `schedule:work` process to remember.
 * Start the loop with:  php artisan qris:sync --watch
 */
class SyncQrisPayments implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** A fresh sync on every attempt is pointless, and it must not retry forever. */
    public int $tries = 1;

    public function __construct(
        public ?int $windowMinutes = null,
        public bool $reschedule = true,
    ) {}

    public function handle(QrisPaymentSyncService $service): void
    {
        try {
            $service->syncPending($this->windowMinutes);
        } catch (Throwable $e) {
            // Never let a single bad run kill the loop.
            Log::error('QRIS sync loop error', ['message' => $e->getMessage()]);
        } finally {
            if ($this->reschedule) {
                self::dispatch()->delay(now()->addSeconds(self::intervalSeconds()));
            }
        }
    }

    public function middleware(): array
    {
        // Two copies of the loop must never run at the same time.
        return [(new WithoutOverlapping('qris-payment-sync'))->expireAfter(300)];
    }

    public static function intervalSeconds(): int
    {
        return (int) config('qris.sync_interval_seconds', 60);
    }
}
