<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batal pesanan (void order): status jadi canceled, stok kembali, dan pesanan
 * batal tidak lagi ikut di antrean, piutang, maupun laporan omzet.
 */
class VoidOrderTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private function foodSale(string $paymentStatus = 'unpaid'): Sale
    {
        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
            'stock' => 3,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-VOID-'.$paymentStatus,
            'customer_name' => 'Tester',
            'customer_phone' => '081200000000',
            'service_type' => 'dine_in',
            'total_amount' => 56000,
            'payment_method' => 'cash',
            'payment_status' => $paymentStatus,
        ]);

        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 28000, 'subtotal' => 56000]);

        return $sale;
    }

    private function cancel(Sale $sale, ?User $user = null, string $reason = 'Pelanggan membatalkan')
    {
        return $this->actingAs($user ?? User::factory()->ownerMakanan()->create())
            ->postJson('/api/owner/order/cancel', ['sale_id' => $sale->id, 'reason' => $reason]);
    }

    public function test_cancel_marks_sale_canceled_and_restores_stock(): void
    {
        $sale = $this->foodSale();
        $user = User::factory()->ownerMakanan()->create();

        $this->cancel($sale, $user)
            ->assertOk()
            ->assertJsonPath('data.restocked_items', 2);

        $sale->refresh();
        $this->assertSame('canceled', $sale->order_status);
        $this->assertSame('Pelanggan membatalkan', $sale->cancel_reason);
        $this->assertSame($user->id, $sale->canceled_by);
        $this->assertNotNull($sale->canceled_at);
        $this->assertSame(5, $this->product->fresh()->stock);
    }

    public function test_cancel_twice_does_not_restore_stock_twice(): void
    {
        $sale = $this->foodSale();

        $this->cancel($sale)->assertOk();
        $this->cancel($sale)->assertStatus(422);

        $this->assertSame(5, $this->product->fresh()->stock);
    }

    public function test_reason_is_required(): void
    {
        $sale = $this->foodSale();

        $this->cancel($sale, null, '')->assertStatus(422);

        $this->assertSame('pending', $sale->fresh()->order_status);
        $this->assertSame(3, $this->product->fresh()->stock);
    }

    public function test_owner_outside_scope_cannot_cancel(): void
    {
        $sale = $this->foodSale();

        $this->cancel($sale, User::factory()->ownerMinuman()->create())->assertNotFound();

        $this->assertSame('pending', $sale->fresh()->order_status);
    }

    public function test_paid_sale_can_be_canceled_with_refund_reminder(): void
    {
        $sale = $this->foodSale('paid');

        $this->cancel($sale)
            ->assertOk()
            ->assertJsonPath('data.was_paid', true);
    }

    public function test_canceled_sale_cannot_be_paid(): void
    {
        $sale = $this->foodSale();
        $user = User::factory()->ownerMakanan()->create();
        $this->cancel($sale, $user)->assertOk();

        $this->actingAs($user)
            ->postJson('/api/owner/payment/status', ['sale_id' => $sale->id, 'payment_status' => 'paid'])
            ->assertStatus(422);

        $this->assertSame('unpaid', $sale->fresh()->payment_status);
    }

    public function test_canceled_sale_is_hidden_from_queue_unpaid_list_and_reports(): void
    {
        $sale = $this->foodSale('paid');
        $user = User::factory()->ownerMakanan()->create();
        $unpaid = Sale::create([
            'invoice_number' => 'INV-VOID-unpaid2',
            'customer_name' => 'Tester',
            'customer_phone' => '081200000000',
            'service_type' => 'dine_in',
            'total_amount' => 28000,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);
        SaleItem::create(['sale_id' => $unpaid->id, 'product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 28000, 'subtotal' => 28000]);

        $this->cancel($sale, $user)->assertOk();
        $this->cancel($unpaid, $user)->assertOk();

        $this->actingAs($user)->getJson('/api/owner/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data.orders')
            ->assertJsonPath('data.stats.revenue_today', 0);

        $this->actingAs($user)->getJson('/api/owner/unpaid')
            ->assertOk()
            ->assertJsonPath('data.summary.sale_count', 0);

        $this->actingAs($user)->getJson('/api/owner/reports/data')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_receipt_shows_canceled_banner(): void
    {
        $sale = $this->foodSale();
        $this->cancel($sale)->assertOk();

        $this->get(route('receipt', $sale->invoice_number))
            ->assertOk()
            ->assertSee('Pesanan ini telah dibatalkan')
            ->assertSee('Pelanggan membatalkan');
    }
}
