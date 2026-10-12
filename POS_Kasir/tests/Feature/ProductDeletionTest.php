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
 * Deleting a menu item must never break the sales history.
 */
class ProductDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function foodProduct(): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
            'name' => 'Nasi Goreng',
        ]);
    }

    private function drinkProduct(): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->minuman()->create()->id,
            'name' => 'Es Kopi',
        ]);
    }

    private function sell(Product $product): void
    {
        $sale = Sale::create([
            'invoice_number' => 'INV-DEL-'.Sale::count(),
            'customer_name' => 'Tester',
            'customer_phone' => '081200000000',
            'service_type' => 'dine_in',
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
    }

    public function test_owner_can_delete_a_product_that_was_never_sold(): void
    {
        $product = $this->foodProduct();

        $this->actingAs(User::factory()->ownerMakanan()->create())
            ->deleteJson("/api/owner/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_a_product_with_sales_history_cannot_be_deleted(): void
    {
        $product = $this->foodProduct();
        $this->sell($product);

        $this->actingAs(User::factory()->ownerMakanan()->create())
            ->deleteJson("/api/owner/products/{$product->id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('sale_items', ['product_id' => $product->id]);
    }

    public function test_owner_cannot_delete_a_product_from_another_scope(): void
    {
        $drink = $this->drinkProduct();

        $this->actingAs(User::factory()->ownerMakanan()->create())
            ->deleteJson("/api/owner/products/{$drink->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('products', ['id' => $drink->id]);
    }

    public function test_admin_can_delete_products_from_both_scopes(): void
    {
        $food = $this->foodProduct();
        $drink = $this->drinkProduct();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->deleteJson("/api/owner/products/{$food->id}")->assertOk();
        $this->actingAs($admin)->deleteJson("/api/owner/products/{$drink->id}")->assertOk();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_guests_cannot_delete_products(): void
    {
        $product = $this->foodProduct();

        $this->deleteJson("/api/owner/products/{$product->id}")->assertStatus(401);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_products_list_reports_the_sold_count_so_the_ui_can_warn(): void
    {
        $sold = $this->foodProduct();
        $fresh = Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
            'name' => 'Mie Goreng',
        ]);
        $this->sell($sold);

        $response = $this->actingAs(User::factory()->ownerMakanan()->create())
            ->getJson('/api/owner/products')
            ->assertOk();

        $counts = collect($response->json('data.products'))->pluck('sold_count', 'name');

        $this->assertSame(1, (int) $counts['Nasi Goreng']);
        $this->assertSame(0, (int) $counts['Mie Goreng']);
    }
}
