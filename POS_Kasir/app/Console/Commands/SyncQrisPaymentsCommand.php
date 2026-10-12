<?php

namespace App\Console\Commands;

use App\Jobs\SyncQrisPayments;
use App\Services\QrisPaymentSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncQrisPaymentsCommand extends Command
{
    protected $signature = 'qris:sync
                            {--watch : Keep syncing in the background (re-queues itself every minute)}
                            {--minutes= : How far back to look for unpaid orders}';

    protected $description = 'Auto-confirm unpaid QRIS orders by asking the gateway which ones are paid';

    public function handle(QrisPaymentSyncService $service): int
    {
        $window = $this->option('minutes') !== null
            ? (int) $this->option('minutes')
            : (int) config('qris.sync_window_minutes');

        if ($this->option('watch')) {
            if ($this->loopAlreadyQueued()) {
                $this->warn('Background QRIS sync sudah berjalan (ada job sync yang masih terjadwal).');
                $this->line('  Tidak perlu menjalankan --watch dua kali.');

                return self::SUCCESS;
            }

            SyncQrisPayments::dispatch($window);

            $this->info('Background QRIS sync started (setiap '.SyncQrisPayments::intervalSeconds().' detik).');
            $this->line('  Proses ini memakai queue:work - pastikan worker sudah jalan.');
            $this->line('  Jendela pemeriksaan: '.$window.' menit ke belakang.');

            return self::SUCCESS;
        }

        $summary = $service->syncPending($window);

        $this->info('QRIS sync selesai');
        $this->line('  dicek   : '.$summary['checked']);
        $this->line('  lunas   : '.$summary['paid']);
        $this->line('  gagal   : '.$summary['failed']);
        $this->line('  notifikasi owner terkirim: '.$summary['notified']);

        if ($summary['paid'] === 0 && $summary['checked'] > 0) {
            $this->comment('  Belum ada pembayaran baru yang terdeteksi.');
        }

        return self::SUCCESS;
    }

    /**
     * Guard against stacking several self-rescheduling loops.
     */
    private function loopAlreadyQueued(): bool
    {
        return DB::table('jobs')
            ->where('payload', 'like', '%SyncQrisPayments%')
            ->exists();
    }
}
