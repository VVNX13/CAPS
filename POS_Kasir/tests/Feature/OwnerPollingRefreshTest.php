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
 * Binds the real owner.js polling code to the real /api/orders/check-new JSON.
 *
 * The dashboard silently stopped auto-refreshing once the plain-PHP API was
 * wrapped in ApiResponse::success(): the endpoint moved its payload under
 * "data", while owner.js kept reading has_new_order/count/latest_id at the top
 * level. Every value became undefined, the guard never fired, and no order ever
 * appeared without a manual refresh. The existing test only checked the JSON
 * shape, so nothing caught the JS reading the wrong level.
 *
 * This test executes owner.js for real against a captured payload, so a contract
 * change on either side fails here instead of in the kitchen.
 */
class OwnerPollingRefreshTest extends TestCase
{
    use RefreshDatabase;

    private const HARNESS = __DIR__.'/../Fixtures/owner-polling-harness.js';

    private const OWNER_JS = __DIR__.'/../../public/assets/js/owner.js';

    /**
     * Capture a genuine endpoint response, then replay it through owner.js.
     */
    private function runPollingAgainstRealApi(): array
    {
        $user = User::factory()->create(['role' => 'admin']);

        $food = Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
            'name' => 'Nasi Goreng',
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-POLL-1',
            'customer_name' => 'Polling Test',
            'customer_phone' => '081200000000',
            'service_type' => 'dine_in',
            'total_amount' => 15000,
            'payment_method' => 'qris',
            'payment_status' => 'unpaid',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $food->id,
            'quantity' => 2,
            'unit_price' => 15000,
            'subtotal' => 30000,
        ]);

        // Real response, straight from the endpoint.
        $response = $this->actingAs($user)
            ->getJson('/api/orders/check-new?type=makanan,minuman&last_id=0')
            ->assertOk();

        $payloadPath = tempnam(sys_get_temp_dir(), 'poll').'.json';
        file_put_contents($payloadPath, json_encode($response->json()));

        try {
            $node = $this->nodeBinary();
            $command = sprintf(
                '%s %s %s %s 2>&1',
                escapeshellarg($node),
                escapeshellarg(self::HARNESS),
                escapeshellarg(realpath(self::OWNER_JS)),
                escapeshellarg($payloadPath)
            );

            exec($command, $output, $exitCode);

            $this->assertSame(0, $exitCode, 'Node harness gagal: '.implode("\n", $output));

            return [
                'payload' => $response->json(),
                'observed' => json_decode(implode("\n", $output), true),
            ];
        } finally {
            @unlink($payloadPath);
        }
    }

    private function nodeBinary(): string
    {
        foreach (['C:\laragon\bin\nodejs\node-v22\node.exe', 'node'] as $candidate) {
            if ($candidate === 'node') {
                return $candidate;
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $this->markTestSkipped('Node.js tidak tersedia untuk menjalankan owner.js.');
    }

    public function test_api_returns_the_new_order_payload(): void
    {
        $result = $this->runPollingAgainstRealApi();

        // The API side of the contract.
        $this->assertTrue($result['payload']['data']['has_new_order']);
        $this->assertSame(1, $result['payload']['data']['count']);
        $this->assertSame(1, $result['payload']['data']['latest_id']);
    }

    public function test_owner_js_refreshes_dashboard_when_a_new_order_arrives(): void
    {
        $result = $this->runPollingAgainstRealApi();

        $observed = $result['observed'];

        $this->assertIsArray($observed, 'Harness tidak menghasilkan observasi yang sah.');

        // The bug: these were all 0 because the guard never fired.
        $this->assertSame(
            1,
            $observed['loadDashboardCalls'],
            'owner.js tidak memanggil loadDashboardData() untuk pesanan baru.'
        );
        $this->assertSame(1, $observed['beepCalls'], 'Audio bip tidak berbunyi.');
        $this->assertCount(1, $observed['toastMessages'], 'Toast notifikasi tidak muncul.');

        // lastSeenOrderId must advance, otherwise the same order re-alerts forever.
        $this->assertSame(1, $observed['lastSeenOrderId']);

        // It must have polled with the owner types and the last seen id.
        $this->assertStringContainsString('type=makanan%2Cminuman', $observed['fetchUrls'][0]);
        $this->assertStringContainsString('last_id=0', $observed['fetchUrls'][0]);
    }

    public function test_owner_js_does_not_read_polling_fields_at_the_top_level(): void
    {
        $source = file_get_contents(realpath(self::OWNER_JS));

        // ApiResponse nests the payload under "data"; a bare read of these keys
        // is always undefined and silently disables the whole polling block.
        $this->assertDoesNotMatchRegularExpression(
            '/if\s*\(\s*data\.has_new_order/',
            $source,
            'owner.js masih membaca has_new_order di level atas response.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/lastSeenOrderId\s*=\s*data\.latest_id/',
            $source,
            'owner.js masih membaca latest_id di level atas response.'
        );
    }

    public function test_polling_runs_every_five_seconds(): void
    {
        $source = file_get_contents(realpath(self::OWNER_JS));

        $this->assertMatchesRegularExpression(
            '/setInterval\s*\([\s\S]{0,120}checkNewOrdersPolling[\s\S]{0,120}5000\s*\)/',
            $source,
            'Interval polling owner.js harus 5000 ms.'
        );
    }
}
