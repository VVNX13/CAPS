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
 * Role scoping: each owner only sees their own category, while the admin sees both.
 */
class RoleScopeTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrders(): void
    {
        $food = Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
            'name' => 'Nasi Goreng',
        ]);

        $drink = Product::factory()->create([
            'category_id' => Category::factory()->minuman()->create()->id,
            'name' => 'Es Kopi',
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-SCOPE-1',
            'customer_name' => 'Tester',
            'customer_phone' => '081200000000',
            'service_type' => 'dine_in',
            'total_amount' => 40000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $food->id, 'quantity' => 1, 'unit_price' => 15000, 'subtotal' => 15000]);
        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $drink->id, 'quantity' => 1, 'unit_price' => 25000, 'subtotal' => 25000]);
    }

    public function test_owner_makanan_only_sees_food_orders(): void
    {
        $this->seedOrders();

        $response = $this->actingAs(User::factory()->ownerMakanan()->create())
            ->getJson('/api/owner/orders')
            ->assertOk();

        $this->assertSame(['Nasi Goreng'], array_column($response->json('data.orders'), 'product_name'));
    }

    public function test_owner_minuman_only_sees_drink_orders(): void
    {
        $this->seedOrders();

        $response = $this->actingAs(User::factory()->ownerMinuman()->create())
            ->getJson('/api/owner/orders')
            ->assertOk();

        $this->assertSame(['Es Kopi'], array_column($response->json('data.orders'), 'product_name'));
    }

    public function test_admin_sees_orders_from_both_owners(): void
    {
        $this->seedOrders();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/owner/orders')
            ->assertOk();

        $names = array_column($response->json('data.orders'), 'product_name');
        sort($names);

        $this->assertSame(['Es Kopi', 'Nasi Goreng'], $names);
        $this->assertSame(
            ['makanan', 'minuman'],
            collect($response->json('data.orders'))->pluck('owner_type')->sort()->values()->all()
        );
    }

    public function test_admin_menu_and_reports_cover_both_categories(): void
    {
        $this->seedOrders();

        $admin = User::factory()->create(['role' => 'admin']);

        $products = $this->actingAs($admin)->getJson('/api/owner/products')->assertOk();
        $this->assertCount(2, $products->json('data.products'));
        $this->assertCount(2, $products->json('data.categories'));

        $report = $this->actingAs($admin)
            ->getJson('/api/owner/reports/data?start_date=2000-01-01&end_date=2100-01-01')
            ->assertOk();

        $this->assertSame(40000.0, (float) $report->json('data.total_revenue'));
        $this->assertSame(2, $report->json('data.total_items_sold'));
        $this->assertSame('Semua Kategori', $report->json('data.scope_label'));
    }

    public function test_owner_minuman_cannot_edit_a_food_product(): void
    {
        $food = Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
        ]);

        $this->actingAs(User::factory()->ownerMinuman()->create())
            ->postJson('/api/owner/products/toggle', ['id' => $food->id, 'is_active' => 0])
            ->assertNotFound();
    }

    public function test_dashboard_uses_a_role_specific_start_label(): void
    {
        $this->assertStringContainsString('Mulai Masak', (string) User::factory()->ownerMakanan()->create()->startWorkingLabel());
        $this->assertStringContainsString('Mulai Buat Minuman', (string) User::factory()->ownerMinuman()->create()->startWorkingLabel());
        $this->assertStringContainsString('Mulai Proses', (string) User::factory()->create(['role' => 'admin'])->startWorkingLabel());
    }

    public function test_admin_dashboard_page_renders_combined_scope(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/owner/dashboard')
            ->assertOk()
            ->assertSee('Semua Kategori')
            ->assertSee('ADMIN')
            ->assertSee('Mulai Proses', false);
    }

    public function test_polling_accepts_multiple_owner_types(): void
    {
        $this->seedOrders();

        // Polling is owner-internal; it must be authenticated (see
        // PublicSurfaceTest for why it can never be anonymous).
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $all = $this->getJson('/api/orders/check-new?type=makanan,minuman&last_id=0')->assertOk();
        $this->assertSame(2, $all->json('data.count'));

        $foodOnly = $this->getJson('/api/orders/check-new?type=makanan&last_id=0')->assertOk();
        $this->assertSame(1, $foodOnly->json('data.count'));

        $this->getJson('/api/orders/check-new?type=ngawur&last_id=0')->assertStatus(422);
    }
}
