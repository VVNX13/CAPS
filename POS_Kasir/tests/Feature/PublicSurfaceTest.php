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
 * Public-surface audit.
 *
 * Everything a customer touches is anonymous by design, so the risk is not
 * "which routes are public" but "what a public route is willing to return".
 */
class PublicSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrder(string $invoice = 'INV-LEAK-1', string $name = 'Budi Rahasia', string $phone = '081298765432'): Sale
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
            'name' => 'Sop Ubi',
            'price' => 10000,
        ]);

        $sale = Sale::create([
            'invoice_number' => $invoice,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'service_type' => 'dine_in',
            'total_amount' => 10000,
            'payment_method' => 'qris',
            'payment_status' => 'unpaid',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'subtotal' => 10000,
        ]);

        return $sale;
    }

    /* ---------------------------------------------------------------- */
    /* Owner polling must never be anonymous                              */
    /* ---------------------------------------------------------------- */

    public function test_check_new_requires_authentication(): void
    {
        $this->seedOrder();

        $this->getJson('/api/orders/check-new?type=makanan,minuman&last_id=0')
            ->assertUnauthorized();
    }

    public function test_check_new_does_not_leak_customer_data_to_guests(): void
    {
        $this->seedOrder('INV-LEAK-2', 'Siti Rahasia', '081299999999');

        // The exact payload that was readable from the internet: name, phone,
        // invoice and payment status, per order.
        $response = $this->get('/api/orders/check-new?type=makanan,minuman&last_id=0');

        // 401 for a JSON-ish request, redirect for a browser one. Either is
        // fine; what matters is that no order data comes back.
        $this->assertContains($response->status(), [401, 302]);

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('Siti Rahasia', $body);
        $this->assertStringNotContainsString('081299999999', $body);
        $this->assertStringNotContainsString('INV-LEAK-2', $body);
    }

    public function test_authenticated_owner_still_gets_polling_data(): void
    {
        $this->seedOrder('INV-OK-1', 'Chef Sukma', '081234567890');

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/orders/check-new?type=makanan,minuman&last_id=0')
            ->assertOk();

        $this->assertTrue($response->json('data.has_new_order'));
        $this->assertSame('Chef Sukma', $response->json('data.items.0.customer_name'));
    }

    /* ---------------------------------------------------------------- */
    /* No state-changing route may be anonymous                           */
    /* ---------------------------------------------------------------- */

    public function test_checkout_still_works_for_customers(): void
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
            'price' => 10000,
        ]);

        $this->postJson('/api/orders/checkout', [
            'customer_name' => 'Siti',
            'customer_phone' => '081234567890',
            'service_type' => 'dine_in',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();
    }

    public function test_no_owner_write_endpoint_is_reachable_anonymously(): void
    {
        $this->seedOrder();

        $endpoints = [
            ['postJson', '/api/owner/products'],
            ['postJson', '/api/owner/item/status'],
            ['postJson', '/api/owner/payment/status'],
            ['postJson', '/api/owner/unpaid/1/paid'],
            ['postJson', '/api/owner/qris/settings'],
            ['postJson', '/api/owner/webhook/secret/update'],
            ['deleteJson', '/api/owner/products/1'],
        ];

        foreach ($endpoints as [$method, $path]) {
            $this->{$method}($path)->assertUnauthorized();
        }
    }

    /* ---------------------------------------------------------------- */
    /* Rate limiting on phone-number lookups                              */
    /* ---------------------------------------------------------------- */

    public function test_order_history_lookup_is_rate_limited(): void
    {
        $this->seedOrder('INV-RL-1', 'Siti', '081234567890');

        // Anonymous by design (no customer accounts), so the only defence
        // against enumerating phone numbers is a request limit.
        $this->getJson('/api/orders/history?phone=081234567890')->assertOk();

        $throttled = false;
        for ($i = 0; $i < 20 && ! $throttled; $i++) {
            $response = $this->getJson('/api/orders/history?phone=081234567890');
            $throttled = $response->status() === 429;
        }

        $this->assertTrue($throttled, 'Endpoint riwayat harus dibatasi, jika tidak nomor HP bisa di-enumerate.');
    }

    /* ---------------------------------------------------------------- */
    /* Things that must never be committed                               */
    /* ---------------------------------------------------------------- */

    public function test_backups_and_dotenv_are_git_ignored(): void
    {
        $rules = array_map('trim', explode("\n", (string) file_get_contents(base_path('.gitignore'))));

        $required = [
            '.env' => 'rahasia API key & password',
            '/storage/backups' => 'dump SQL berisi nama & nomor pelanggan',
            '/vendor' => 'dependensi, bisa di-install ulang',
            '/public/deploy-check.php' => 'diagnostik yang membocorkan info server',
        ];

        foreach ($required as $rule => $why) {
            $this->assertContains(
                $rule,
                $rules,
                "aturan .gitignore '{$rule}' hilang; itu melindungi {$why}."
            );
        }
    }
}
