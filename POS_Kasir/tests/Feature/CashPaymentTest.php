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
 * Kalkulator kembalian: uang diterima & kembalian disimpan untuk dicetak di struk.
 */
class CashPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function foodSale(string $method = 'cash'): Sale
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-CASH-'.$method,
            'customer_name' => 'Tester',
            'customer_phone' => '081200000000',
            'service_type' => 'dine_in',
            'total_amount' => 56000,
            'payment_method' => $method,
            'payment_status' => 'unpaid',
        ]);

        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 28000, 'subtotal' => 56000]);

        return $sale;
    }

    public function test_cash_received_and_change_are_saved(): void
    {
        $sale = $this->foodSale();

        $this->actingAs(User::factory()->ownerMakanan()->create())
            ->postJson('/api/owner/payment/status', [
                'sale_id' => $sale->id,
                'payment_status' => 'paid',
                'cash_received' => 100000,
            ])
            ->assertOk();

        $sale->refresh();
        $this->assertSame('paid', $sale->payment_status);
        $this->assertEquals(100000, $sale->cash_received);
        $this->assertEquals(44000, $sale->cash_change);
    }

    public function test_cash_received_less_than_total_is_rejected(): void
    {
        $sale = $this->foodSale();

        $this->actingAs(User::factory()->ownerMakanan()->create())
            ->postJson('/api/owner/payment/status', [
                'sale_id' => $sale->id,
                'payment_status' => 'paid',
                'cash_received' => 50000,
            ])
            ->assertStatus(422);

        $sale->refresh();
        $this->assertSame('unpaid', $sale->payment_status);
        $this->assertNull($sale->cash_received);
    }

    public function test_qris_payment_ignores_cash_received(): void
    {
        $sale = $this->foodSale('qris');

        $this->actingAs(User::factory()->ownerMakanan()->create())
            ->postJson('/api/owner/payment/status', [
                'sale_id' => $sale->id,
                'payment_status' => 'paid',
                'cash_received' => 100000,
            ])
            ->assertOk();

        $sale->refresh();
        $this->assertSame('paid', $sale->payment_status);
        $this->assertNull($sale->cash_received);
        $this->assertNull($sale->cash_change);
    }

    public function test_receipt_shows_cash_received_and_change(): void
    {
        $sale = $this->foodSale();
        $sale->update(['payment_status' => 'paid', 'cash_received' => 100000, 'cash_change' => 44000]);

        $this->get(route('receipt', $sale->invoice_number))
            ->assertOk()
            ->assertSee('Tunai Diterima')
            ->assertSee('Rp 100.000')
            ->assertSee('Rp 44.000');
    }
}
