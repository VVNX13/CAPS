<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dynamic QRIS screen, the QR stand printer and the gateway / webhook
 * credentials are shop-wide configuration, so they belong to the admin only.
 *
 * Before this the routes were guarded by "auth" alone, which meant a food or
 * drink owner could open the QRIS page and POST a new webhook secret or
 * QRIS-ify API key, silently changing how the cafe's payments are confirmed.
 */
class AdminOnlyAccessTest extends TestCase
{
    use RefreshDatabase;

    private function ownerPaths(): array
    {
        return [
            ['get', '/owner/qris'],
            ['get', '/qr-center'],
        ];
    }

    private function ownerApiPaths(): array
    {
        return [
            ['getJson', '/api/owner/qris/settings'],
            ['getJson', '/api/owner/qrisify/settings'],
            ['getJson', '/api/owner/webhook/config'],
        ];
    }

    public function test_owner_roles_cannot_open_the_qris_or_qr_center_pages(): void
    {
        foreach ([User::factory()->ownerMakanan()->create(), User::factory()->ownerMinuman()->create()] as $owner) {
            foreach ($this->ownerPaths() as [$method, $path]) {
                $this->actingAs($owner)->{$method}($path)->assertForbidden();
            }
        }
    }

    public function test_owner_roles_cannot_read_gateway_or_webhook_settings(): void
    {
        foreach ([User::factory()->ownerMakanan()->create(), User::factory()->ownerMinuman()->create()] as $owner) {
            foreach ($this->ownerApiPaths() as [$method, $path]) {
                $this->actingAs($owner)->{$method}($path)->assertForbidden();
            }
        }
    }

    public function test_owner_roles_cannot_change_the_webhook_secret_or_gateway_key(): void
    {
        $owner = User::factory()->ownerMakanan()->create();

        $this->actingAs($owner)
            ->postJson('/api/owner/webhook/secret/regenerate')
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson('/api/owner/webhook/secret/update', ['secret' => 'attacker-controlled'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson('/api/owner/qrisify/settings', ['api_key' => 'attacker-controlled'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson('/api/owner/qris/settings', ['qris_static_payload' => 'tampered'])
            ->assertForbidden();

        $this->assertDatabaseMissing('settings', ['key' => 'qrisify_api_key', 'value' => 'attacker-controlled']);
    }

    public function test_admin_keeps_access_to_everything(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/owner/qris')->assertOk();
        $this->actingAs($admin)->get('/qr-center')->assertOk();
        $this->actingAs($admin)->getJson('/api/owner/qris/settings')->assertOk();
        $this->actingAs($admin)->getJson('/api/owner/qrisify/settings')->assertOk();
        $this->actingAs($admin)->getJson('/api/owner/webhook/config')->assertOk();
    }

    public function test_guests_are_redirected_rather_than_shown_a_403(): void
    {
        $this->get('/owner/qris')->assertRedirect(route('login'));
        $this->get('/qr-center')->assertRedirect(route('login'));
    }

    public function test_admin_only_links_are_hidden_from_owner_roles(): void
    {
        $owner = User::factory()->ownerMakanan()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['/owner/dashboard', '/owner/products', '/owner/reports', '/owner/belum-bayar'] as $path) {
            $ownerHtml = $this->actingAs($owner)->get($path)->assertOk()->getContent();
            $adminHtml = $this->actingAs($admin)->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString('/owner/qris', $ownerHtml, "{$path} masih menampilkan link QRIS");
            $this->assertStringNotContainsString('/qr-center', $ownerHtml, "{$path} masih menampilkan link Cetak QR");

            $this->assertStringContainsString('/owner/qris', $adminHtml, "{$path} untuk admin harusnya ada link QRIS");
            $this->assertStringContainsString('/qr-center', $adminHtml, "{$path} untuk admin harusnya ada link Cetak QR");
        }
    }
}
