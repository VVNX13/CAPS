<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Potongan harga: kupon saat checkout, diskon manual / kupon dari kasir,
 * dan omzet laporan yang sudah dipotong diskon.
 */
class DiscountTest extends TestCase
{
    use RefreshDatabase;

    private function promo(array $attributes = []): PromoCode
    {
        return PromoCode::create(array_merge([
            'code' => 'HEMAT10',
            'name' => 'Hemat 10%',
            'type' => 'percent',
            'value' => 10,
            'min_order' => 0,
            'is_active' => true,
        ], $attributes));
    }

    private function foodProduct(int $price = 25000, int $stock = 10): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
            'price' => $price,
            'stock' => $stock,
        ]);
    }

    private function checkout(Product $product, int $qty, ?string $code)
    {
        return $this->postJson('/api/orders/checkout', [
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'service_type' => 'takeaway',
            'payment_method' => 'cash',
            'promo_code' => $code,
            'items' => [['product_id' => $product->id, 'quantity' => $qty]],
        ]);
    }

    private function unpaidSale(string $paymentStatus = 'unpaid'): Sale
    {
        $product = $this->foodProduct(28000);

        $sale = Sale::create([
            'invoice_number' => 'INV-DISC-'.$paymentStatus,
            'customer_name' => 'Tester',
            'customer_phone' => '081200000000',
            'service_type' => 'dine_in',
            'subtotal_amount' => 56000,
            'total_amount' => 56000,
            'payment_method' => 'cash',
            'payment_status' => $paymentStatus,
        ]);

        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 28000, 'subtotal' => 56000]);

        return $sale;
    }

    private function applyDiscount(Sale $sale, array $payload, ?User $user = null)
    {
        return $this->actingAs($user ?? User::factory()->ownerMakanan()->create())
            ->postJson('/api/owner/order/discount', array_merge(['sale_id' => $sale->id], $payload));
    }

    /* ---------- Kupon saat checkout ---------- */

    public function test_checkout_with_coupon_reduces_total_and_uses_quota(): void
    {
        $promo = $this->promo(['max_discount' => 4000]);

        $this->checkout($this->foodProduct(), 2, 'hemat10')->assertCreated();

        $sale = Sale::firstOrFail();
        $this->assertEquals(50000, $sale->subtotal_amount);
        $this->assertEquals(4000, $sale->discount_amount); // 10% = 5.000, dibatasi maks 4.000
        $this->assertEquals(46000, $sale->total_amount);
        $this->assertSame('HEMAT10', $sale->promo_code);
        $this->assertSame(1, $promo->fresh()->used_count);
    }

    public function test_invalid_coupon_rejects_checkout_without_touching_stock(): void
    {
        $product = $this->foodProduct();

        $this->checkout($product, 2, 'NGAWUR')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kode kupon tidak ditemukan');

        $this->assertSame(0, Sale::count());
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_coupon_rules_are_enforced(): void
    {
        $product = $this->foodProduct();

        $this->promo(['code' => 'MIN100', 'min_order' => 100000]);
        $this->promo(['code' => 'LEWAT', 'ends_at' => now()->subDay()->toDateString()]);
        $this->promo(['code' => 'HABIS', 'usage_limit' => 1, 'used_count' => 1]);
        $this->promo(['code' => 'MATI', 'is_active' => false]);

        foreach (['MIN100', 'LEWAT', 'HABIS', 'MATI'] as $code) {
            $this->checkout($product, 1, $code)->assertStatus(422);
        }

        $this->assertSame(0, Sale::count());
    }

    public function test_promo_check_previews_the_discount(): void
    {
        $this->promo(['code' => 'POTONG5K', 'type' => 'fixed', 'value' => 5000]);

        $this->postJson('/api/promo/check', ['code' => 'potong5k', 'subtotal' => 30000])
            ->assertOk()
            ->assertJsonPath('data.code', 'POTONG5K')
            ->assertJsonPath('data.discount', 5000);

        $this->postJson('/api/promo/check', ['code' => 'TIDAKADA', 'subtotal' => 30000])
            ->assertStatus(422);
    }

    /* ---------- Diskon dari kasir ---------- */

    public function test_cashier_manual_discount_updates_the_bill(): void
    {
        $sale = $this->unpaidSale();

        $this->applyDiscount($sale, ['mode' => 'manual', 'amount' => 6000, 'note' => 'Pelanggan tetap'])
            ->assertOk()
            ->assertJsonPath('data.total_amount', 50000);

        $sale->refresh();
        $this->assertEquals(6000, $sale->discount_amount);
        $this->assertSame('Pelanggan tetap', $sale->discount_note);
        $this->assertNull($sale->promo_code);
    }

    public function test_manual_discount_cannot_exceed_the_bill(): void
    {
        $sale = $this->unpaidSale();

        $this->applyDiscount($sale, ['mode' => 'manual', 'amount' => 60000])->assertStatus(422);

        $this->assertEquals(56000, $sale->fresh()->total_amount);
    }

    public function test_paid_bill_cannot_be_discounted(): void
    {
        $sale = $this->unpaidSale('paid');

        $this->applyDiscount($sale, ['mode' => 'manual', 'amount' => 1000])->assertStatus(422);
    }

    public function test_owner_outside_scope_cannot_discount(): void
    {
        $sale = $this->unpaidSale();

        $this->applyDiscount($sale, ['mode' => 'manual', 'amount' => 1000], User::factory()->ownerMinuman()->create())
            ->assertNotFound();
    }

    public function test_switching_and_removing_coupon_releases_quota(): void
    {
        $promo = $this->promo();
        $sale = $this->unpaidSale();
        $user = User::factory()->ownerMakanan()->create();

        $this->applyDiscount($sale, ['mode' => 'promo', 'code' => 'HEMAT10'], $user)->assertOk();
        $this->assertEquals(50400, $sale->fresh()->total_amount);
        $this->assertSame(1, $promo->fresh()->used_count);

        // Ganti ke diskon manual: kuota kupon dikembalikan.
        $this->applyDiscount($sale, ['mode' => 'manual', 'amount' => 1000], $user)->assertOk();
        $this->assertSame(0, $promo->fresh()->used_count);

        $this->applyDiscount($sale, ['mode' => 'remove'], $user)->assertOk();
        $sale->refresh();
        $this->assertEquals(0, $sale->discount_amount);
        $this->assertEquals(56000, $sale->total_amount);
    }

    public function test_cash_change_uses_the_discounted_total(): void
    {
        $sale = $this->unpaidSale();
        $user = User::factory()->ownerMakanan()->create();
        $this->applyDiscount($sale, ['mode' => 'manual', 'amount' => 6000], $user)->assertOk();

        $this->actingAs($user)->postJson('/api/owner/payment/status', [
            'sale_id' => $sale->id,
            'payment_status' => 'paid',
            'cash_received' => 50000,
        ])->assertOk();

        $this->assertEquals(0, $sale->fresh()->cash_change);
    }

    public function test_voiding_an_order_releases_its_coupon(): void
    {
        $promo = $this->promo();
        $this->checkout($this->foodProduct(), 1, 'HEMAT10')->assertCreated();

        $this->actingAs(User::factory()->ownerMakanan()->create())
            ->postJson('/api/owner/order/cancel', ['sale_id' => Sale::first()->id, 'reason' => 'Salah input'])
            ->assertOk();

        $this->assertSame(0, $promo->fresh()->used_count);
    }

    /* ---------- Struk & laporan ---------- */

    public function test_receipt_shows_subtotal_and_discount(): void
    {
        $sale = $this->unpaidSale();
        $this->applyDiscount($sale, ['mode' => 'manual', 'amount' => 6000, 'note' => 'Pelanggan tetap'])->assertOk();

        $this->get(route('receipt', $sale->invoice_number))
            ->assertOk()
            ->assertSee('Pelanggan tetap')
            ->assertSee('Rp 56.000')
            ->assertSee('-Rp 6.000')
            ->assertSee('Rp 50.000');
    }

    public function test_reports_and_kpi_use_revenue_after_discount(): void
    {
        $sale = $this->unpaidSale();
        $user = User::factory()->ownerMakanan()->create();
        $this->applyDiscount($sale, ['mode' => 'manual', 'amount' => 6000], $user)->assertOk();
        $sale->update(['payment_status' => 'paid']);

        $this->actingAs($user)->getJson('/api/owner/reports/data')
            ->assertOk()
            ->assertJsonPath('data.gross_revenue', 56000)
            ->assertJsonPath('data.total_discount', 6000)
            ->assertJsonPath('data.total_revenue', 50000);

        $this->actingAs($user)->getJson('/api/owner/orders')
            ->assertOk()
            ->assertJsonPath('data.stats.revenue_today', 50000);
    }

    /* ---------- Kelola kupon (admin) ---------- */

    public function test_admin_manages_coupons_and_owners_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/owner/promo')->assertOk();
        $this->actingAs($admin)->postJson('/api/owner/promos', [
            'code' => 'akhir pekan',
            'name' => 'Promo',
            'type' => 'percent',
            'value' => 10,
        ])->assertStatus(422); // spasi tidak boleh di kode

        $this->actingAs($admin)->postJson('/api/owner/promos', [
            'code' => 'weekend',
            'name' => 'Promo Akhir Pekan',
            'type' => 'fixed',
            'value' => 5000,
        ])->assertOk();

        $this->assertDatabaseHas('promo_codes', ['code' => 'WEEKEND', 'type' => 'fixed']);

        $owner = User::factory()->ownerMakanan()->create();
        $this->actingAs($owner)->get('/owner/promo')->assertForbidden();
        $this->actingAs($owner)->getJson('/api/owner/promos')->assertForbidden();
    }
}
