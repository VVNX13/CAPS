<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Jobs\SyncQrisPayments;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\QrisifyService;
use App\Services\QrisPaymentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Background auto-confirmation of QRIS payments.
 *
 * The gateway is never called for real: the fetcher is injected into
 * QrisPaymentSyncService, which is also why it is constructor-injected.
 */
class QrisPaymentSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function sync(?array $response = null, ?RuntimeException $throws = null, ?int $window = null): array
    {
        $fetcher = static function () use ($response, $throws) {
            if ($throws) {
                throw $throws;
            }

            return $response;
        };

        return (new QrisPaymentSyncService($fetcher))->syncPending($window);
    }

    private function unpaidQrisSale(array $attributes = [], ?string $createdAt = null): Sale
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 20000]);

        $sale = Sale::create(array_merge([
            'invoice_number' => 'INV-SYNC-'.(Sale::count() + 1),
            'customer_name' => 'Penguji',
            'customer_phone' => '081200000123',
            'service_type' => 'dine_in',
            'total_amount' => 20000,
            'qris_amount_total' => 20000,
            'payment_method' => 'qris',
            'payment_status' => 'unpaid',
            'qris_payload' => '000201010212',
            'qris_transaction_id' => 'tx-'.(Sale::count() + 1),
        ], $attributes));

        if ($createdAt !== null) {
            // created_at is guarded, so it has to be written explicitly.
            Sale::where('id', $sale->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
        }

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 20000,
            'subtotal' => 20000,
        ]);

        return $sale->fresh();
    }

    public function test_it_marks_an_order_paid_when_the_gateway_reports_success(): void
    {
        $sale = $this->unpaidQrisSale();

        $summary = $this->sync(['status' => 'SUCCESS', 'paid_at' => now()->toDateTimeString()]);

        $this->assertSame(1, $summary['checked']);
        $this->assertSame(1, $summary['paid']);
        $this->assertSame('paid', $sale->fresh()->payment_status);
    }

    public function test_it_leaves_an_order_alone_while_still_unpaid(): void
    {
        $sale = $this->unpaidQrisSale();

        $summary = $this->sync(['status' => 'PENDING']);

        $this->assertSame(0, $summary['paid']);
        $this->assertSame('unpaid', $sale->fresh()->payment_status);
    }

    public function test_cash_orders_are_never_auto_confirmed(): void
    {
        $sale = $this->unpaidQrisSale(['payment_method' => 'cash', 'qris_transaction_id' => null]);

        $this->assertSame(0, $this->sync(['status' => 'SUCCESS'])['checked']);
        $this->assertSame('unpaid', $sale->fresh()->payment_status);
    }

    public function test_local_static_qris_without_a_gateway_id_is_skipped(): void
    {
        $sale = $this->unpaidQrisSale(['qris_transaction_id' => null]);

        $this->assertSame(0, $this->sync(['status' => 'SUCCESS'])['checked']);
        $this->assertSame('unpaid', $sale->fresh()->payment_status);
    }

    public function test_orders_older_than_the_window_are_left_for_the_cashier(): void
    {
        $sale = $this->unpaidQrisSale([], now()->subHours(3)->toDateTimeString());

        $this->assertSame(0, $this->sync(['status' => 'SUCCESS'], null, 30)['checked']);
        $this->assertSame('unpaid', $sale->fresh()->payment_status);
    }

    public function test_a_gateway_outage_does_not_crash_the_run(): void
    {
        $sale = $this->unpaidQrisSale();

        $summary = $this->sync(null, new RuntimeException('Connection timed out'));

        $this->assertSame(1, $summary['failed']);
        $this->assertSame(0, $summary['paid']);
        $this->assertSame('unpaid', $sale->fresh()->payment_status);
    }

    public function test_one_broken_transaction_does_not_block_the_others(): void
    {
        $first = $this->unpaidQrisSale();
        $second = $this->unpaidQrisSale();

        $calls = 0;
        $fetcher = static function () use (&$calls) {
            $calls++;

            if ($calls === 1) {
                throw new RuntimeException('flaky gateway');
            }

            return ['status' => 'SUCCESS'];
        };

        $summary = (new QrisPaymentSyncService($fetcher))->syncPending();

        $this->assertSame(1, $summary['failed']);
        $this->assertSame(1, $summary['paid']);
        $this->assertSame('unpaid', $first->fresh()->payment_status);
        $this->assertSame('paid', $second->fresh()->payment_status);
    }

    public function test_owners_are_notified_when_a_payment_is_auto_confirmed(): void
    {
        Queue::fake();
        $owner = User::factory()->ownerMakanan()->create();

        $this->unpaidQrisSale();

        $this->sync(['status' => 'SUCCESS']);

        Queue::assertPushed(SendPushNotification::class, function (SendPushNotification $job) use ($owner) {
            return str_contains($job->payload['title'], 'Pembayaran diterima')
                && in_array($owner->id, $job->userIds, true);
        });
    }

    public function test_nothing_is_synced_when_the_gateway_is_switched_off(): void
    {
        Setting::put('qrisify_enabled', '0');

        $sale = $this->unpaidQrisSale();

        $this->assertSame(0, $this->sync(['status' => 'SUCCESS'])['checked']);
        $this->assertSame('unpaid', $sale->fresh()->payment_status);
    }

    public function test_an_already_paid_order_is_not_queried_again(): void
    {
        $sale = $this->unpaidQrisSale(['payment_status' => 'paid']);

        $this->assertSame(0, $this->sync(['status' => 'SUCCESS'])['checked']);
        $this->assertSame('paid', $sale->fresh()->payment_status);
    }

    public function test_the_paid_flag_detection_is_shared(): void
    {
        $this->assertTrue(QrisifyService::isPaid(['paid_at' => '2026-01-01 10:00:00']));
        $this->assertTrue(QrisifyService::isPaid(['status' => 'success']));
        $this->assertTrue(QrisifyService::isPaid(['status' => 'PAID']));
        $this->assertTrue(QrisifyService::isPaid(['status' => 'SETTLED']));
        $this->assertFalse(QrisifyService::isPaid(['status' => 'PENDING']));
        $this->assertFalse(QrisifyService::isPaid(null));
        $this->assertFalse(QrisifyService::isPaid([]));
    }

    public function test_the_loop_requeues_itself_after_a_run(): void
    {
        Queue::fake();

        (new SyncQrisPayments(null, false))->handle(app(QrisPaymentSyncService::class));

        Queue::assertPushed(SyncQrisPayments::class, 0);
    }

    public function test_a_recurring_run_requeues_exactly_one_successor(): void
    {
        Queue::fake();

        (new SyncQrisPayments)->handle(app(QrisPaymentSyncService::class));

        Queue::assertPushed(SyncQrisPayments::class, 1);
    }

    public function test_a_failing_run_still_requeues_itself(): void
    {
        Queue::fake();

        $service = new class extends QrisPaymentSyncService
        {
            public function syncPending(?int $windowMinutes = null): array
            {
                throw new RuntimeException('boom');
            }
        };

        (new SyncQrisPayments)->handle($service);

        Queue::assertPushed(SyncQrisPayments::class, 1);
    }

    public function test_the_command_runs_a_single_pass_without_starting_the_loop(): void
    {
        Queue::fake();

        $this->artisan('qris:sync')->assertSuccessful();

        Queue::assertPushed(SyncQrisPayments::class, 0);
    }
}
