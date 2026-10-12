<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chasing unpaid cash orders, and letting a returning customer find their own
 * history again.
 *
 * The reason the phone number is validated strictly is visible in the fixtures
 * below: the old database filled up with "000000", which cannot be dialled and
 * cannot be matched to a person, so both features were dead on arrival.
 */
class ReceivableAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function foodProduct(): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
            'name' => 'Nasi Goreng',
            'price' => 25000,
        ]);
    }

    private function drinkProduct(): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->minuman()->create()->id,
            'name' => 'Es Kopi',
            'price' => 15000,
        ]);
    }

    private function placeOrder(array $attributes, array $products = []): Sale
    {
        $products = $products ?: [$this->foodProduct()];

        $sale = Sale::create(array_merge([
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'service_type' => 'dine_in',
            'total_amount' => 25000,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ], $attributes));

        foreach ($products as $product) {
            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $product->price,
                'subtotal' => $product->price,
            ]);
        }

        return $sale;
    }

    /* --------------------------------------------------------------- */
    /* Phone number normalisation                                       */
    /* --------------------------------------------------------------- */

    public function test_phone_number_normalisation(): void
    {
        $valid = ['081234567890', '0812-3456-7890', '0812 3456 7890', '+62 812 3456 7890', '6281234567890', '(0812) 3456.7890'];

        foreach ($valid as $input) {
            $this->assertTrue(PhoneNumber::isValid($input), "{$input} seharusnya valid");
            $this->assertSame('081234567890', PhoneNumber::normalize($input), "{$input} tidak ternormalisasi sama");
        }

        $invalid = ['000000', '00000', '0', '1234567890', '081234567', '08123456789a', '0812#3456', '+1 555 0100', ''];

        foreach ($invalid as $input) {
            $this->assertFalse(PhoneNumber::isValid($input), "{$input} seharusnya tidak valid");
        }
    }

    public function test_checkout_normalises_the_stored_phone_number(): void
    {
        $product = $this->foodProduct();

        $response = $this->postJson('/api/orders/checkout', [
            'customer_name' => 'Siti',
            'customer_phone' => '+62 812-3456-7890',
            'service_type' => 'dine_in',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertSame(
            '081234567890',
            Sale::where('id', $response->json('data.sale_id'))->value('customer_phone')
        );
    }

    public function test_checkout_rejects_placeholder_phone_numbers(): void
    {
        $product = $this->foodProduct();

        $this->postJson('/api/orders/checkout', [
            'customer_name' => 'Siti',
            'customer_phone' => '000000',
            'service_type' => 'dine_in',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('customer_phone');

        $this->assertDatabaseCount('sales', 0);
    }

    /* --------------------------------------------------------------- */
    /* Owner receivables                                                */
    /* --------------------------------------------------------------- */

    public function test_unpaid_page_requires_authentication(): void
    {
        $this->get('/owner/belum-bayar')->assertRedirect('/login');
        $this->getJson('/api/owner/unpaid')->assertStatus(401);
    }

    public function test_unpaid_list_groups_bills_per_customer(): void
    {
        // Same person, two forgotten cash bills.
        $this->placeOrder(['customer_phone' => '081234567890', 'total_amount' => 25000]);
        $this->placeOrder(['customer_phone' => '0812-3456-7890', 'total_amount' => 30000]);

        // Different person, one bill.
        $this->placeOrder(['customer_name' => 'Andi', 'customer_phone' => '089876543210', 'total_amount' => 15000]);

        // Someone who did pay.
        $this->placeOrder(['customer_phone' => '081111111111', 'payment_status' => 'paid']);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/owner/unpaid')
            ->assertOk();

        $groups = $response->json('data.groups');

        $this->assertCount(2, $groups, 'Harus ada 2 pelanggan, bukan 3 tagihan.');

        $budi = collect($groups)->firstWhere('customer_phone', '081234567890');
        $this->assertNotNull($budi, 'Dua tagihan numerik berbeda harus digabung jadi satu pelanggan.');
        $this->assertSame(2, $budi['sale_count']);
        $this->assertSame(55000.0, (float) $budi['total_amount']);
        $this->assertTrue($budi['phone_usable']);
        $this->assertStringStartsWith('tel:081234567890', $budi['tel_link']);
        $this->assertStringStartsWith('https://wa.me/6281234567890', $budi['whatsapp_link']);

        $response->assertJsonPath('data.summary.sale_count', 3)
            ->assertJsonPath('data.summary.customer_count', 2);

        // JSON drops the ".0", so compare numerically rather than strictly.
        $this->assertEquals(70000.0, (float) $response->json('data.summary.total_amount'));
    }

    public function test_group_whatsapp_message_quotes_the_full_group_total(): void
    {
        // Regression: the message was built per bill, so a customer with two
        // forgotten orders was asked for only the first amount (Rp 25.000) while
        // the screen showed Rp 55.000.
        $this->placeOrder(['customer_phone' => '081234567890', 'total_amount' => 25000]);
        $this->placeOrder(['customer_phone' => '081234567890', 'total_amount' => 30000]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/owner/unpaid')
            ->assertOk();

        $group = $response->json('data.groups.0');
        $message = urldecode((string) $group['whatsapp_link']);

        $this->assertStringContainsString('55.000', $message, 'Pesan harus menyebut total seluruh tagihan.');
        $this->assertStringContainsString('2 pesanan', $message);
        $this->assertStringNotContainsString('Total Rp 25.000', $message);
    }

    public function test_unpaid_list_flags_placeholder_numbers_as_undialable(): void
    {
        $this->placeOrder(['customer_phone' => '000000']);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/owner/unpaid')
            ->assertOk();

        $group = $response->json('data.groups.0');

        $this->assertFalse($group['phone_usable']);
        $this->assertNull($group['tel_link']);
        $this->assertNull($group['whatsapp_link']);
        $response->assertJsonPath('data.summary.undialable_count', 1);
    }

    public function test_unpaid_list_is_scoped_to_the_owner_category(): void
    {
        $this->placeOrder(['customer_phone' => '081200000001'], [$this->foodProduct()]);
        $this->placeOrder(['customer_phone' => '081200000002'], [$this->drinkProduct()]);

        $food = $this->actingAs(User::factory()->ownerMakanan()->create())
            ->getJson('/api/owner/unpaid')->assertOk();
        $this->assertCount(1, $food->json('data.groups'));
        $this->assertSame('081200000001', $food->json('data.groups.0.customer_phone'));

        $drink = $this->actingAs(User::factory()->ownerMinuman()->create())
            ->getJson('/api/owner/unpaid')->assertOk();
        $this->assertCount(1, $drink->json('data.groups'));
        $this->assertSame('081200000002', $drink->json('data.groups.0.customer_phone'));

        $admin = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/owner/unpaid')->assertOk();
        $this->assertCount(2, $admin->json('data.groups'));
    }

    public function test_owner_cannot_settle_a_sale_outside_their_category(): void
    {
        $foodSale = $this->placeOrder([], [$this->foodProduct()]);

        $this->actingAs(User::factory()->ownerMinuman()->create())
            ->postJson("/api/owner/unpaid/{$foodSale->id}/paid")
            ->assertStatus(404);

        $this->assertSame('unpaid', $foodSale->fresh()->payment_status);
    }

    public function test_owner_can_settle_a_visible_sale(): void
    {
        $sale = $this->placeOrder([], [$this->foodProduct()]);

        $this->actingAs(User::factory()->ownerMakanan()->create())
            ->postJson("/api/owner/unpaid/{$sale->id}/paid")
            ->assertOk();

        $this->assertSame('paid', $sale->fresh()->payment_status);
    }

    public function test_payment_status_endpoint_also_enforces_scope(): void
    {
        // Regression: this endpoint used to accept any sale id from any account.
        $foodSale = $this->placeOrder([], [$this->foodProduct()]);

        $this->actingAs(User::factory()->ownerMinuman()->create())
            ->postJson('/api/owner/payment/status', [
                'sale_id' => $foodSale->id,
                'payment_status' => 'paid',
            ])
            ->assertStatus(404);

        $this->assertSame('unpaid', $foodSale->fresh()->payment_status);
    }

    /* --------------------------------------------------------------- */
    /* Customer history                                                 */
    /* --------------------------------------------------------------- */

    public function test_history_page_renders(): void
    {
        $this->get('/riwayat')->assertOk()->assertSee('Riwayat Pesanan', false);
    }

    public function test_history_lookup_matches_any_spelling_of_the_number(): void
    {
        $this->placeOrder(['invoice_number' => 'INV-H1', 'customer_phone' => '081234567890']);

        foreach (['081234567890', '0812-3456-7890', '+6281234567890'] as $spelling) {
            $response = $this->getJson('/api/orders/history?phone='.urlencode($spelling))->assertOk();
            $this->assertSame(1, $response->json('data.summary.order_count'), "Gagal untuk ejaan {$spelling}");
            $this->assertSame('INV-H1', $response->json('data.orders.0.invoice_number'));
        }
    }

    public function test_history_never_leaks_another_customers_orders(): void
    {
        $this->placeOrder(['customer_phone' => '081234567890', 'total_amount' => 25000]);
        $this->placeOrder(['customer_name' => 'Rahmat', 'customer_phone' => '089999999999', 'total_amount' => 99000]);

        $response = $this->getJson('/api/orders/history?phone=081234567890')->assertOk();

        $this->assertSame(1, $response->json('data.summary.order_count'));
        $response->assertJsonMissing(['invoice_number' => 'INV-TEST-']);
        $this->assertStringNotContainsString('Rahmat', $response->getContent());
    }

    public function test_history_requires_a_valid_phone_number(): void
    {
        $this->getJson('/api/orders/history?phone=000000')->assertStatus(422);
        $this->getJson('/api/orders/history')->assertStatus(422);
    }

    public function test_history_reports_unpaid_orders_prominently(): void
    {
        $this->placeOrder(['customer_phone' => '081234567890', 'total_amount' => 25000, 'payment_status' => 'unpaid']);
        $this->placeOrder(['customer_phone' => '081234567890', 'total_amount' => 40000, 'payment_status' => 'paid']);

        $response = $this->getJson('/api/orders/history?phone=081234567890')->assertOk();

        $response->assertJsonPath('data.summary.order_count', 2)
            ->assertJsonPath('data.summary.unpaid_count', 1);

        $this->assertEquals(25000.0, (float) $response->json('data.summary.unpaid_amount'));
        $this->assertEquals(40000.0, (float) $response->json('data.summary.total_spent'));

        $unpaid = collect($response->json('data.orders'))->firstWhere('is_paid', false);
        $this->assertNotNull($unpaid);
        $this->assertFalse($unpaid['is_paid']);
    }

    public function test_reorder_payload_carries_product_ids(): void
    {
        $product = $this->foodProduct();
        $this->placeOrder(['customer_phone' => '081234567890'], [$product, $product]);

        $response = $this->getJson('/api/orders/history?phone=081234567890')->assertOk();

        $this->assertSame(
            [['product_id' => $product->id, 'quantity' => 1], ['product_id' => $product->id, 'quantity' => 1]],
            collect($response->json('data.orders.0.items'))->map(fn ($i) => [
                'product_id' => $i['product_id'],
                'quantity' => $i['quantity'],
            ])->all()
        );
    }

    public function test_menu_page_exposes_the_product_catalog_for_reorder(): void
    {
        $product = $this->foodProduct();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('__MENU_PRODUCTS__', $html);
        $this->assertStringContainsString('"'.$product->id.'"', $html);
    }
}
