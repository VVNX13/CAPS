<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner pages are used on phones, so the navbar must collapse behind a
 * hamburger below 768px. These guard the pieces that make that work.
 */
class MobileNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_layout_loads_nav_js_on_every_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('assets/js/nav.js', false);

        $this->actingAs(User::factory()->create())
            ->get('/owner/dashboard')
            ->assertOk()
            ->assertSee('assets/js/nav.js', false);
    }

    /**
     * Every owner page, including the ones that used to roll their own header.
     */
    private function ownerPages(): array
    {
        return [
            '/owner/dashboard',
            '/owner/products',
            '/owner/reports',
            '/owner/belum-bayar',
            '/owner/qris',
            '/qr-center',
        ];
    }

    public function test_every_owner_page_exposes_a_navigation_panel_for_the_toggle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ($this->ownerPages() as $path) {
            $this->actingAs($admin)
                ->get($path)
                ->assertOk()
                ->assertSee('navbar-container', false)
                ->assertSee('owner-nav-actions', false)
                ->assertSee('assets/js/nav.js', false);
        }
    }

    public function test_every_owner_page_shows_the_same_navigation_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // The QR stand printer adds a page-specific button, so compare the
        // shared links rather than the whole markup.
        $shared = [
            'route("owner.dashboard")' => 'Dashboard',
            'route("owner.products")' => 'Menu',
            'route("owner.reports")' => 'Laporan',
            'route("owner.unpaid")' => 'Belum Bayar',
        ];

        foreach ($this->ownerPages() as $path) {
            $html = $this->actingAs($admin)->get($path)->assertOk()->getContent();

            foreach ($shared as $routeCall) {
                $this->assertStringContainsString(
                    $routeCall,
                    $html,
                    "{$path} tidak memuat link {$routeCall}"
                );
            }

            $this->assertStringContainsString('Keluar', $html, "{$path} tidak punya tombol Keluar");
        }
    }

    public function test_owner_pages_no_longer_hand_roll_their_own_header(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ($this->ownerPages() as $path) {
            $html = $this->actingAs($admin)->get($path)->assertOk()->getContent();

            // The old inline headers were plain <div class="nav-actions">, which
            // is the class nav.js also has to look for. One shared partial means
            // only .owner-nav-actions should appear now.
            $this->assertStringNotContainsString(
                'class="nav-actions"',
                $html,
                "{$path} masih memakai header sendiri"
            );
        }
    }

    public function test_the_stylesheet_declares_the_hamburger_breakpoint(): void
    {
        $css = (string) file_get_contents(public_path('assets/css/style.css'));

        $this->assertStringContainsString('.nav-toggle', $css);
        $this->assertStringContainsString('nav-open', $css);

        $mediaQuery = strpos($css, '@media (max-width: 768px)');
        $shown = strpos($css, 'display: inline-flex');

        $this->assertNotFalse($mediaQuery, 'Expected a mobile media query.');
        $this->assertNotFalse($shown, 'The hamburger must be shown on small screens.');
        $this->assertGreaterThan(
            $mediaQuery,
            $shown,
            'The hamburger is only displayed inside the mobile media query.'
        );
    }

    public function test_the_nav_helper_script_is_served(): void
    {
        $this->assertFileExists(public_path('assets/js/nav.js'));

        $script = (string) file_get_contents(public_path('assets/js/nav.js'));

        $this->assertStringContainsString('nav-open', $script);
        $this->assertStringContainsString('aria-expanded', $script);
    }

    public function test_the_responsive_block_comes_after_the_owner_nav_rule(): void
    {
        $css = (string) file_get_contents(public_path('assets/css/style.css'));

        $ownerRule = strpos($css, '.owner-nav-actions {');
        $mediaQuery = strpos($css, '@media (max-width: 768px)');

        $this->assertNotFalse($ownerRule, 'Expected a base .owner-nav-actions rule.');
        $this->assertNotFalse($mediaQuery, 'Expected a mobile media query.');

        $this->assertGreaterThan(
            $ownerRule,
            $mediaQuery,
            'The media query must come after .owner-nav-actions, otherwise the base '
            .'"display: flex" rule wins and the panel never hides on phones.'
        );
    }
}
