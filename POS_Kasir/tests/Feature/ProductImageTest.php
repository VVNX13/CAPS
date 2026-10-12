<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Menu photos.
 *
 * Before this the only way to show a picture was to paste an external URL, and
 * every product without one fell back to the same Unsplash salad bowl, fetched
 * from the internet. That was wrong twice over: the photo was not the dish the
 * cafe serves, and a menu that is supposed to work offline could not render it.
 */
class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function foodCategory(): Category
    {
        return Category::factory()->makanan()->create();
    }

    private function drinkCategory(): Category
    {
        return Category::factory()->minuman()->create();
    }

    /* ---------------------------------------------------------------- */
    /* URL resolution                                                    */
    /* ---------------------------------------------------------------- */

    public function test_image_url_handles_the_three_stored_shapes(): void
    {
        $this->assertNull(ProductImage::url(null), 'Kosong harus berarti tanpa foto.');
        $this->assertNull(ProductImage::url(''));
        $this->assertNull(ProductImage::url('   '));

        $this->assertSame(
            'https://cdn.example.com/a.jpg',
            ProductImage::url('https://cdn.example.com/a.jpg'),
            'URL yang ditempel harus dipakai apa adanya.'
        );

        $this->assertSame(
            '/storage/products/abc.jpg',
            ProductImage::url('products/abc.jpg'),
            'File yang diunggah harus disajikan lewat /storage.'
        );
    }

    public function test_external_detection(): void
    {
        $this->assertTrue(ProductImage::isExternal('http://x.test/a.jpg'));
        $this->assertTrue(ProductImage::isExternal('https://x.test/a.jpg'));
        $this->assertFalse(ProductImage::isExternal('products/a.jpg'));
        $this->assertFalse(ProductImage::isExternal(null));
    }

    /* ---------------------------------------------------------------- */
    /* Upload                                                            */
    /* ---------------------------------------------------------------- */

    public function test_owner_can_upload_a_menu_photo(): void
    {
        Storage::fake('public');

        $category = $this->foodCategory();

        $response = $this->actingAs($this->admin())->post('/api/owner/products', [
            'category_id' => $category->id,
            'name' => 'Nasi Ayam Geprek',
            'price' => 18000,
            'stock' => 20,
            'photo' => UploadedFile::fake()->image('geprek.jpg', 400, 300),
        ]);

        $response->assertCreated();

        $product = Product::where('name', 'Nasi Ayam Geprek')->firstOrFail();

        $this->assertNotEmpty($product->image);
        $this->assertStringStartsWith('products/', $product->image);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_replacing_a_photo_deletes_the_orphaned_file(): void
    {
        Storage::fake('public');

        $category = $this->foodCategory();
        $owner = $this->admin();

        $this->actingAs($owner)->post('/api/owner/products', [
            'category_id' => $category->id,
            'name' => 'Soto Ayam',
            'price' => 15000,
            'stock' => 10,
            'photo' => UploadedFile::fake()->image('soto.jpg', 300, 300),
        ])->assertCreated();

        $product = Product::where('name', 'Soto Ayam')->firstOrFail();
        $first = $product->image;
        Storage::disk('public')->assertExists($first);

        $this->actingAs($owner)->post('/api/owner/products', [
            'id' => $product->id,
            'category_id' => $category->id,
            'name' => 'Soto Ayam',
            'price' => 15000,
            'stock' => 10,
            'photo' => UploadedFile::fake()->image('baru.jpg', 300, 300),
        ])->assertOk();

        $second = $product->fresh()->image;

        $this->assertNotSame($first, $second, 'Foto baru harus punya nama file berbeda.');
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);
    }

    public function test_keeping_the_photo_untouched_does_not_delete_the_file(): void
    {
        Storage::fake('public');

        $category = $this->foodCategory();
        $owner = $this->admin();

        $this->actingAs($owner)->post('/api/owner/products', [
            'category_id' => $category->id,
            'name' => 'Kentang Goreng',
            'price' => 12000,
            'stock' => 30,
            'photo' => UploadedFile::fake()->image('kentang.jpg', 200, 200),
        ])->assertCreated();

        $product = Product::where('name', 'Kentang Goreng')->firstOrFail();
        $image = $product->image;

        // Just editing the price, not touching the photo.
        $this->actingAs($owner)->post('/api/owner/products', [
            'id' => $product->id,
            'category_id' => $category->id,
            'name' => 'Kentang Goreng',
            'price' => 13000,
            'stock' => 30,
            'image' => '',
        ])->assertOk();

        $this->assertSame($image, $product->fresh()->image, 'Foto lama harus tetap tersimpan.');
        Storage::disk('public')->assertExists($image);
    }

    public function test_photo_can_be_removed_explicitly(): void
    {
        Storage::fake('public');

        $category = $this->foodCategory();
        $owner = $this->admin();

        $this->actingAs($owner)->post('/api/owner/products', [
            'category_id' => $category->id,
            'name' => 'Dimsum',
            'price' => 22000,
            'stock' => 15,
            'photo' => UploadedFile::fake()->image('dimsum.jpg', 200, 200),
        ])->assertCreated();

        $product = Product::where('name', 'Dimsum')->firstOrFail();
        $image = $product->image;

        $this->actingAs($owner)->post('/api/owner/products', [
            'id' => $product->id,
            'category_id' => $category->id,
            'name' => 'Dimsum',
            'price' => 22000,
            'stock' => 15,
            'image' => '',
            'remove_photo' => 1,
        ])->assertOk();

        $this->assertSame('', $product->fresh()->image);
        Storage::disk('public')->assertMissing($image);
    }

    public function test_deleting_a_product_also_removes_its_photo(): void
    {
        Storage::fake('public');

        $category = $this->foodCategory();
        $owner = $this->admin();

        $this->actingAs($owner)->post('/api/owner/products', [
            'category_id' => $category->id,
            'name' => 'Menu Tanpa Penjualan',
            'price' => 10000,
            'stock' => 5,
            'photo' => UploadedFile::fake()->image('x.jpg', 200, 200),
        ])->assertCreated();

        $product = Product::where('name', 'Menu Tanpa Penjualan')->firstOrFail();
        $image = $product->image;

        $this->actingAs($owner)->deleteJson("/api/owner/products/{$product->id}")->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        Storage::disk('public')->assertMissing($image);
    }

    public function test_external_urls_are_never_deleted_from_storage(): void
    {
        Storage::fake('public');

        $category = $this->foodCategory();
        $owner = $this->admin();

        $this->actingAs($owner)->post('/api/owner/products', [
            'category_id' => $category->id,
            'name' => 'Menu URL',
            'price' => 10000,
            'stock' => 5,
            'image' => 'https://cdn.example.com/foto.jpg',
        ])->assertCreated();

        $product = Product::where('name', 'Menu URL')->firstOrFail();

        $this->actingAs($owner)->deleteJson("/api/owner/products/{$product->id}")->assertOk();

        // Nothing was stored locally, so nothing may be removed.
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /* ---------------------------------------------------------------- */
    /* Validation                                                        */
    /* ---------------------------------------------------------------- */

    public function test_non_image_uploads_are_rejected(): void
    {
        Storage::fake('public');

        $category = $this->foodCategory();

        $this->actingAs($this->admin())->post('/api/owner/products', [
            'category_id' => $category->id,
            'name' => 'Menu Berbahaya',
            'price' => 10000,
            'stock' => 5,
            'photo' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
        ])->assertStatus(422)->assertJsonValidationErrors('photo');

        $this->assertDatabaseMissing('products', ['name' => 'Menu Berbahaya']);
    }

    public function test_oversized_uploads_are_rejected(): void
    {
        Storage::fake('public');

        $category = $this->foodCategory();

        $this->actingAs($this->admin())->post('/api/owner/products', [
            'category_id' => $category->id,
            'name' => 'Foto Gede',
            'price' => 10000,
            'stock' => 5,
            'photo' => UploadedFile::fake()->create('besar.jpg', 6000, 'image/jpeg'),
        ])->assertStatus(422)->assertJsonValidationErrors('photo');
    }

    public function test_owner_cannot_upload_a_photo_into_another_owners_category(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->ownerMakanan()->create())
            ->post('/api/owner/products', [
                'category_id' => $this->drinkCategory()->id,
                'name' => 'Menu Minum',
                'price' => 10000,
                'stock' => 5,
                'photo' => UploadedFile::fake()->image('x.jpg', 200, 200),
            ])
            ->assertForbidden();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /* ---------------------------------------------------------------- */
    /* Downscale                                                         */
    /* ---------------------------------------------------------------- */

    public function test_large_photos_are_downscaled(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD tidak tersedia.');
        }

        Storage::fake('public');

        // 2400x1800, far beyond the 900px cap.
        $file = UploadedFile::fake()->image('besar.jpg', 2400, 1800);

        $this->actingAs($this->admin())->post('/api/owner/products', [
            'category_id' => $this->foodCategory()->id,
            'name' => 'Foto Resolusi Besar',
            'price' => 10000,
            'stock' => 5,
            'photo' => $file,
        ])->assertCreated();

        $product = Product::where('name', 'Foto Resolusi Besar')->firstOrFail();
        $path = Storage::disk('public')->path($product->image);

        $this->assertFileExists($path);

        [$width, $height] = getimagesize($path);

        $this->assertLessThanOrEqual(
            ProductImage::MAX_DIMENSION,
            max($width, $height),
            'Sisi terpanjang harus dikecilkan.'
        );
        $this->assertSame(900, $width, 'Lebar harus jadi 900px.');
        // Aspect ratio of 2400x1800 (4:3) must be preserved.
        $this->assertEqualsWithDelta(675, $height, 2);
    }

    public function test_png_transparency_survives_the_resize(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD tidak tersedia.');
        }

        Storage::fake('public');

        $source = imagecreatetruecolor(1200, 1200);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        $transparent = imagecolorallocatealpha($source, 0, 0, 0, 127);
        imagefilledrectangle($source, 0, 0, 1200, 1200, $transparent);
        $opaque = imagecolorallocate($source, 220, 38, 38);
        imagefilledrectangle($source, 200, 200, 400, 400, $opaque);

        $path = tempnam(sys_get_temp_dir(), 'alpha').'.png';
        imagepng($source, $path);
        imagedestroy($source);

        $this->actingAs($this->admin())->post('/api/owner/products', [
            'category_id' => $this->foodCategory()->id,
            'name' => 'PNG Transparan',
            'price' => 10000,
            'stock' => 5,
            'photo' => new UploadedFile($path, 'alpha.png', 'image/png', null, true),
        ])->assertCreated();

        $product = Product::where('name', 'PNG Transparan')->firstOrFail();
        $stored = Storage::disk('public')->path($product->image);

        [$width, $height, $type] = getimagesize($stored);

        $this->assertSame(IMAGETYPE_PNG, $type, 'Harus tetap PNG, bukan dikonversi jadi JPG.');
        $this->assertLessThanOrEqual(ProductImage::MAX_DIMENSION, max($width, $height));

        // A resized PNG that lost its alpha channel comes out fully black.
        // In GD, alpha 0 is opaque and 127 is fully transparent.
        $resized = imagecreatefrompng($stored);
        $corner = imagecolorat($resized, 2, 2);
        imagedestroy($resized);

        $alpha = ($corner >> 24) & 0x7F;
        $this->assertGreaterThan(80, $alpha, 'Sudut harus tetap transparan, bukan hitam.');

        // And the opaque block must not have been lost.
        $resized = imagecreatefrompng($stored);
        $scaledX = (int) round(200 * 900 / 1200);
        $scaledY = (int) round(200 * 900 / 1200);
        $center = imagecolorat($resized, $scaledX, $scaledY);
        imagedestroy($resized);

        $red = ($center >> 16) & 0xFF;
        $this->assertGreaterThan(150, $red, 'Blok merah di tengah harus tetap terlihat.');
    }

    /* ---------------------------------------------------------------- */
    /* Listing + views                                                  */
    /* ---------------------------------------------------------------- */

    public function test_list_exposes_a_ready_to_use_image_url(): void
    {
        Storage::fake('public');

        $category = $this->foodCategory();
        $owner = $this->admin();

        $this->actingAs($owner)->post('/api/owner/products', [
            'category_id' => $category->id,
            'name' => 'Menu Berfoto',
            'price' => 10000,
            'stock' => 5,
            'photo' => UploadedFile::fake()->image('a.jpg', 200, 200),
        ])->assertCreated();

        $products = $this->actingAs($owner)->getJson('/api/owner/products')->assertOk()->json('data.products');
        $withPhoto = collect($products)->firstWhere('name', 'Menu Berfoto');

        $this->assertStringStartsWith('/storage/products/', $withPhoto['image_url']);
        $this->assertFalse($withPhoto['image_external']);
    }

    public function test_customer_menu_renders_a_local_placeholder_instead_of_a_stock_photo(): void
    {
        $category = $this->foodCategory();
        Product::factory()->create(['category_id' => $category->id, 'name' => 'Nasi Ayam Geprek']);

        $html = $this->get('/')->assertOk()->getContent();

        // The old fallback pulled an unrelated photo from the internet, which
        // both needed the network and misrepresented the dish.
        $this->assertStringNotContainsString('images.unsplash.com', $html);
        $this->assertStringContainsString('card-product-img-placeholder', $html);
        $this->assertStringContainsString('🍲', $html);
    }

    public function test_customer_menu_shows_drink_placeholder_for_drinks(): void
    {
        Product::factory()->create([
            'category_id' => $this->drinkCategory()->id,
            'name' => 'Iced Matcha',
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('ph-drink', $html);
        $this->assertStringContainsString('🥤', $html);
    }

    public function test_uploaded_photo_reaches_the_customer_menu(): void
    {
        Storage::fake('public');

        $category = $this->foodCategory();
        $owner = $this->admin();

        $this->actingAs($owner)->post('/api/owner/products', [
            'category_id' => $category->id,
            'name' => 'Nasi Goreng Spesial',
            'price' => 28000,
            'stock' => 10,
            'photo' => UploadedFile::fake()->image('nasigoreng.jpg', 400, 300),
        ])->assertCreated();

        $image = Product::where('name', 'Nasi Goreng Spesial')->value('image');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('/storage/'.$image, $html);
        $this->assertStringNotContainsString('card-product-img-placeholder', $html);
    }

    public function test_products_page_ships_the_upload_controls(): void
    {
        $html = $this->actingAs($this->admin())
            ->get('/owner/products')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="prod_photo"', $html, 'Input upload file harus ada.');
        $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $html);
        $this->assertStringContainsString('id="prod_remove_photo"', $html);
        $this->assertStringNotContainsString('images.unsplash.com', $html);
    }
}
