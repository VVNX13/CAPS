<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Struk thermal: versi cetak 58mm/80mm ada di halaman struk.
 */
class ThermalReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_contains_the_thermal_print_layout(): void
    {
        $product = Product::factory()->create(['name' => 'Nasi Goreng']);

        $sale = Sale::create([
            'invoice_number' => 'INV-THERMAL-1',
            'customer_name' => 'Budi',
            'customer_phone' => '081200000000',
            'service_type' => 'dine_in',
            'table_number' => '5',
            'total_amount' => 56000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'cash_received' => 100000,
            'cash_change' => 44000,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 28000,
            'subtotal' => 56000,
            'notes' => 'Pedas',
        ]);

        $this->get(route('receipt', $sale->invoice_number))
            ->assertOk()
            ->assertSee('class="thermal-print"', false)
            ->assertSee('id="paperSize"', false)
            ->assertSee(config('app.address'))
            ->assertSeeInOrder(['Nasi Goreng', '2 x 28.000', '56.000', '* Pedas'])
            ->assertSeeInOrder(['TOTAL', 'Rp 56.000', 'Tunai', 'Rp 100.000', 'Kembalian', 'Rp 44.000'])
            ->assertSee('Meja 5');
    }
}
