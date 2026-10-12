<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guard against Blade `@section('scripts')` / `@push('scripts')` mismatches,
 * which silently drop every page script without any visible error.
 */
class ViewAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_injects_the_csrf_helper_and_token(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('assets/js/csrf.js', $html);
        $this->assertStringContainsString('name="csrf-token"', $html);
    }

    public function test_login_page_ships_its_script(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('function handleLogin', false)
            ->assertSee('function quickLogin', false);
    }

    public function test_customer_menu_ships_its_script(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('assets/js/customer.js', false)
            ->assertSee('initCustomerMenu', false);
    }

    public function test_owner_dashboard_ships_the_owner_script(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/owner/dashboard')
            ->assertOk()
            ->assertSee('assets/js/owner.js', false)
            ->assertSee('initOwnerPolling', false);
    }

    public function test_owner_reports_and_qris_pages_ship_their_scripts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/owner/reports')
            ->assertOk()
            ->assertSee('function loadReport', false);

        // The QRIS screen is admin only, so it has to be requested as one.
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/owner/qris')
            ->assertOk()
            ->assertSee('function testGenerateQris', false)
            ->assertSee('function runWebhookTest', false);
    }
}
