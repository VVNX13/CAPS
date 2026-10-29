<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nomor meja dari checkout: disimpan untuk makan di tempat, tampil di struk.
 */
class TableNumberTest extends TestCase
{
    use RefreshDatabase;

    private function checkout(array $overrides = [])
    {
        $product = Product::factory()->create(['price' => 25000, 'stock' => 10]);

        return $this->postJson('/api/orders/checkout', array_merge([
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'service_type' => 'dine_in',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $overrides));
    }

    public function test_dine_in_order_saves_the_table_number_in_uppercase(): void
    {
        $this->checkout(['table_number' => ' a3 '])->assertCreated();

        $this->assertSame('A3', Sale::firstOrFail()->table_number);
    }

    public function test_takeaway_order_ignores_the_table_number(): void
    {
        $this->checkout(['service_type' => 'takeaway', 'table_number' => '5'])->assertCreated();

        $this->assertNull(Sale::firstOrFail()->table_number);
    }

    public function test_table_number_with_invalid_characters_is_rejected(): void
    {
        $this->checkout(['table_number' => '<b>5</b>'])->assertStatus(422);

        $this->assertSame(0, Sale::count());
    }

    public function test_receipt_shows_the_table_number(): void
    {
        $this->checkout(['table_number' => '7'])->assertCreated();
        $sale = Sale::firstOrFail();

        $this->get(route('receipt', $sale->invoice_number))
            ->assertOk()
            ->assertSee('No. Meja');
    }
}
