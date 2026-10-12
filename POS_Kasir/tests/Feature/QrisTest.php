<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use App\Support\QrisHelper;
use App\Support\WebhookHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrisTest extends TestCase
{
    use RefreshDatabase;

    public function test_static_to_dynamic_switches_to_dynamic_mode_and_injects_the_amount(): void
    {
        $dynamic = QrisHelper::convertStaticToDynamic(QrisHelper::DEFAULT_STATIC_QRIS, 27500);

        $this->assertStringStartsWith('000201', $dynamic);
        $this->assertStringContainsString('010212', $dynamic, 'Point of initiation should become dynamic.');
        $this->assertStringContainsString('540527500', $dynamic, 'Tag 54 should carry the exact amount.');
        $this->assertStringContainsString('5802ID', $dynamic, 'Country code must be preserved.');
    }

    public function test_crc16_matches_the_emvco_checksum(): void
    {
        $this->assertSame('D51B', QrisHelper::calculateCRC16(substr(QrisHelper::DEFAULT_STATIC_QRIS, 0, -4)));
    }

    public function test_qris_settings_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/owner/qris/settings')->assertStatus(401);
    }

    public function test_owner_can_generate_a_test_dynamic_qris(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/owner/qris/test', ['amount' => 15000])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.crc16', substr(
                QrisHelper::convertStaticToDynamic(QrisHelper::getStaticPayload(), 15000),
                -4
            ));
    }

    public function test_webhook_requires_the_shared_secret(): void
    {
        $this->postJson('/api/webhook/payment', [
            'invoice_number' => 'INV-TEST-0001',
        ])->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_webhook_marks_a_matching_invoice_as_paid(): void
    {
        $sale = Sale::create([
            'invoice_number' => 'INV-TEST-0001',
            'customer_name' => 'Rina',
            'customer_phone' => '081200000002',
            'service_type' => 'dine_in',
            'total_amount' => 45000,
            'payment_method' => 'qris',
            'payment_status' => 'unpaid',
        ]);

        $this->postJson('/api/webhook/payment', [
            'invoice_number' => 'INV-TEST-0001',
        ], ['X-Webhook-Secret' => WebhookHelper::getSecret()])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertSame('paid', $sale->fresh()->payment_status);
    }

    public function test_webhook_falls_back_to_amount_matching_for_notification_forwarders(): void
    {
        $sale = Sale::create([
            'invoice_number' => 'INV-TEST-0002',
            'customer_name' => 'Joko',
            'customer_phone' => '081200000003',
            'service_type' => 'dine_in',
            'total_amount' => 37500,
            'payment_method' => 'qris',
            'payment_status' => 'unpaid',
        ]);

        $this->postJson('/api/webhook/payment', [
            'message' => 'Pembayaran diterima Rp 37.500',
        ], ['X-Webhook-Secret' => WebhookHelper::getSecret()])
            ->assertOk();

        $this->assertSame('paid', $sale->fresh()->payment_status);
    }
}
