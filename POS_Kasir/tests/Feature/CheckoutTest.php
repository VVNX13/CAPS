<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_a_sale_with_items_and_reduces_stock(): void
    {
        $product = Product::factory()->create(['price' => 25000, 'stock' => 10]);

        $response = $this->postJson('/api/orders/checkout', [
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'service_type' => 'dine_in',
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $sale = Sale::firstOrFail();

        $this->assertSame('Budi', $sale->customer_name);
        $this->assertSame('unpaid', $sale->payment_status);
        $this->assertSame(50000.0, (float) $sale->total_amount);
        $this->assertCount(1, $sale->items);
        $this->assertSame('pending', $sale->items->first()->item_status);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_checkout_rejects_an_empty_cart(): void
    {
        $this->postJson('/api/orders/checkout', [
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'service_type' => 'dine_in',
            'payment_method' => 'cash',
            'items' => [],
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_checkout_fails_when_stock_is_insufficient(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        $this->postJson('/api/orders/checkout', [
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'service_type' => 'dine_in',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ])->assertStatus(500)->assertJsonPath('success', false);

        $this->assertSame(0, Sale::count());
    }

    public function test_checkout_rejects_inactive_products(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->postJson('/api/orders/checkout', [
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'service_type' => 'dine_in',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(500)->assertJsonPath('success', false);
    }

    public function test_receipt_page_is_public_and_renders_the_order(): void
    {
        $product = Product::factory()->create(['name' => 'Nasi Goreng', 'price' => 20000]);
        $sale = Sale::create([
            'invoice_number' => 'INV-TEST-0001',
            'customer_name' => 'Siti',
            'customer_phone' => '081200000000',
            'service_type' => 'takeaway',
            'total_amount' => 20000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 20000,
            'subtotal' => 20000,
        ]);

        $this->get('/receipt/INV-TEST-0001')
            ->assertOk()
            ->assertSee('INV-TEST-0001')
            ->assertSee('Nasi Goreng')
            ->assertSee('LUNAS');
    }

    public function test_owner_dashboard_only_returns_items_of_their_own_category(): void
    {
        $foodCategory = Category::factory()->makanan()->create();
        $drinkCategory = Category::factory()->minuman()->create();

        $food = Product::factory()->create(['category_id' => $foodCategory->id, 'name' => 'Nasi Goreng']);
        $drink = Product::factory()->create(['category_id' => $drinkCategory->id, 'name' => 'Es Kopi']);

        $sale = Sale::create([
            'invoice_number' => 'INV-TEST-0002',
            'customer_name' => 'Andi',
            'customer_phone' => '081200000001',
            'service_type' => 'dine_in',
            'total_amount' => 30000,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);

        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $food->id, 'quantity' => 1, 'unit_price' => 15000, 'subtotal' => 15000]);
        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $drink->id, 'quantity' => 1, 'unit_price' => 15000, 'subtotal' => 15000]);

        $response = $this->actingAs(User::factory()->ownerMinuman()->create())
            ->getJson('/api/owner/orders')
            ->assertOk();

        $names = array_column($response->json('data.orders'), 'product_name');

        $this->assertSame(['Es Kopi'], $names);
    }

    public function test_checkout_saves_and_displays_item_notes(): void
    {
        $drinkCategory = Category::factory()->minuman()->create();
        $drink = Product::factory()->create(['category_id' => $drinkCategory->id, 'name' => 'Kopi Tubruk', 'price' => 15000, 'stock' => 10]);

        $response = $this->postJson('/api/orders/checkout', [
            'customer_name' => 'Devano',
            'customer_phone' => '081234567890',
            'service_type' => 'dine_in',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $drink->id,
                    'quantity' => 1,
                    'notes' => 'Less sugar, panas',
                ],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $sale = Sale::firstOrFail();
        $item = $sale->items->first();
        $this->assertSame('Less sugar, panas', $item->notes);

        // Check receipt displays the note
        $this->get('/receipt/'.$sale->invoice_number)
            ->assertOk()
            ->assertSee('Less sugar, panas');

        // Check owner API includes the item note
        $owner = User::factory()->ownerMinuman()->create();
        $this->actingAs($owner)
            ->getJson('/api/owner/orders')
            ->assertOk()
            ->assertJsonPath('data.orders.0.item_notes', 'Less sugar, panas');
    }
}
