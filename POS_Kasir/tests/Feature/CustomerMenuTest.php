<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_page_lists_active_products(): void
    {
        Category::factory()->makanan()->create(['name' => 'Makanan Berat']);
        $active = Product::factory()->create(['name' => 'Nasi Goreng Spesial', 'price' => 28000]);
        Product::factory()->inactive()->create(['name' => 'Menu Rahasia']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Nasi Goreng Spesial')
            ->assertSee('Rp 28.000')
            ->assertSee('Makanan Berat')
            ->assertDontSee('Menu Rahasia');
    }

    public function test_menu_alias_is_public(): void
    {
        $this->get('/menu')->assertOk();
    }

    /* ---------------------------------------------------------------- */
    /* Description copy                                                  */
    /* ---------------------------------------------------------------- */

    public function test_no_filler_description_is_invented_for_empty_products(): void
    {
        // Every card used to fall back to the same sentence, so a menu with no
        // written descriptions still looked like it had fifteen descriptions.
        $category = Category::factory()->makanan()->create(['name' => 'Makanan Berat']);
        Product::factory()->create(['category_id' => $category->id, 'name' => 'Nasi Goreng', 'description' => '']);
        Product::factory()->create(['category_id' => $category->id, 'name' => 'Soto Ayam', 'description' => null]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('Menu lezat pilihan segar khas restoran', $html);
    }

    public function test_empty_description_keeps_the_layout_spacer(): void
    {
        // The price row is pinned to the bottom with a flex spacer that used to
        // be the description paragraph. Dropping it outright would leave the
        // cards uneven, so an invisible spacer has to take its place.
        $category = Category::factory()->makanan()->create();
        Product::factory()->create(['category_id' => $category->id, 'name' => 'Nasi Goreng', 'description' => '']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('card-product-desc-empty', $html);
    }

    public function test_real_description_is_still_shown(): void
    {
        $category = Category::factory()->makanan()->create();
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Nasi Ayam Geprek',
            'description' => 'Sambal melingkup, sambal matah',
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Sambal melingkup, sambal matah', $html);
        $this->assertStringNotContainsString('card-product-desc-empty', $html);
    }

    public function test_qr_center_is_no_longer_public(): void
    {
        // The QR stand printer used to be reachable by anyone, including
        // anonymous visitors. It is a shop tool, so it is admin only now.
        $this->get('/qr-center')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->ownerMakanan()->create())
            ->get('/qr-center')
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/qr-center')
            ->assertOk()
            ->assertSee('Scan 1 QR Code untuk Pesan');
    }

    public function test_unknown_route_returns_404(): void
    {
        $this->get('/halaman-yang-tidak-ada')->assertNotFound();
    }
}
