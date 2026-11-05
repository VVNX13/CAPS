<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductImage;
use App\Support\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('owner.products', [
            'scopeLabel' => $user->scopeLabel(),
        ]);
    }

    /**
     * View-only stock page for owner_makanan / owner_minuman.
     */
    public function stockView(Request $request): View
    {
        $user = $request->user();

        return view('owner.stock', [
            'scopeLabel' => $user->scopeLabel(),
            'ownerType'  => $user->ownerType,
            'isAdmin'    => $user->isAdmin(),
        ]);
    }

    /**
     * View-only product stock list (no management actions).
     */
    public function stockList(Request $request): JsonResponse
    {
        $ownerTypes = $request->user()->ownerTypes();

        $products = Product::query()
            ->select('products.id', 'products.name', 'products.stock', 'products.price', 'products.is_active', 'products.image')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereIn('categories.owner_type', $ownerTypes)
            ->orderBy('products.name')
            ->get()
            ->map(fn (Product $p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'stock'     => $p->stock,
                'price'     => (float) $p->price,
                'is_active' => (bool) $p->is_active,
                'image_url' => ProductImage::url($p->image, $p->name),
            ]);

        return ApiResponse::success(['products' => $products]);
    }

    /**
     * Tambah stok produk (kasir/admin).
     */
    public function addStock(Request $request, int $product): JsonResponse
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:9999'],
        ], [
            'qty.required' => 'Jumlah stok wajib diisi',
            'qty.min'      => 'Jumlah stok minimal 1',
            'qty.max'      => 'Maksimal penambahan stok 9999 sekaligus',
        ]);

        $ownerTypes = $request->user()->ownerTypes();

        $model = Product::whereIn('category_id', Category::whereIn('owner_type', $ownerTypes)->pluck('id'))
            ->findOrFail($product);

        $model->increment('stock', (int) $data['qty']);

        return ApiResponse::success(
            ['stock' => $model->fresh()->stock],
            "Stok '{$model->name}' berhasil ditambah {$data['qty']} porsi"
        );
    }

    /**
     * Products and categories owned by the signed-in user.
     */
    public function list(Request $request): JsonResponse
    {
        $ownerTypes = $request->user()->ownerTypes();

        $products = Product::query()
            ->select('products.*')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereIn('categories.owner_type', $ownerTypes)
            ->orderByDesc('products.id')
            ->with('category')
            ->get()
            ->map(fn (Product $product) => [
                'id'             => $product->id,
                'category_id'    => $product->category_id,
                'name'           => $product->name,
                'description'    => $product->description,
                'price'          => (float) $product->price,
                'stock'          => $product->stock,
                'image'          => $product->image,
                'image_url'      => ProductImage::url($product->image, $product->name),
                'image_external' => ProductImage::isExternal($product->image),
                'is_active'      => (int) $product->is_active,
                'category_name'  => $product->category->name,
                'owner_type'     => $product->category->owner_type,
                'sold_count'     => $product->saleItems()->count(),
            ]);

        $categories = Category::whereIn('owner_type', $ownerTypes)->get();

        return ApiResponse::success([
            'products'   => $products,
            'categories' => $categories,
        ]);
    }

    /**
     * Create or update a menu item.
     */
    public function save(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'id'           => ['nullable', 'integer'],
            'category_id'  => ['required', 'integer', 'exists:categories,id'],
            'name'         => ['required', 'string', 'max:150'],
            'description'  => ['nullable', 'string'],
            'price'        => ['required', 'numeric', 'min:0.01'],
            'stock'        => ['required', 'integer', 'min:0'],
            'image'        => ['nullable', 'string', 'max:255'],
            'photo'        => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.ProductImage::MAX_UPLOAD_KB],
            'remove_photo' => ['nullable', 'boolean'],
            'is_active'    => ['nullable', 'integer', 'in:0,1'],
        ], [
            'category_id.required' => 'Kategori produk wajib diisi',
            'category_id.exists'   => 'Kategori tidak ditemukan',
            'name.required'        => 'Nama produk wajib diisi',
            'name.max'             => 'Nama produk maksimal 150 karakter',
            'price.required'       => 'Harga produk wajib diisi',
            'price.min'            => 'Harga harus lebih besar dari 0',
            'price.numeric'        => 'Harga harus berupa angka',
            'stock.required'       => 'Stok wajib diisi',
            'stock.min'            => 'Stok tidak boleh negatif',
            'image.max'            => 'URL foto terlalu panjang (maksimal 255 karakter)',
            'photo.mimetypes'      => 'Foto harus berupa gambar JPG, PNG, atau WebP',
            'photo.max'            => 'Ukuran foto maksimal 4 MB',
        ]);

        $category = Category::findOrFail($data['category_id']);

        if (! $user->canManage($category->owner_type)) {
            return ApiResponse::error('Kategori tersebut bukan milik Anda', 403);
        }

        $product  = null;
        $oldImage = null;

        if (! empty($data['id'])) {
            $product = Product::whereIn('category_id', Category::whereIn('owner_type', $user->ownerTypes())->pluck('id'))
                ->findOrFail($data['id']);

            $oldImage = $product->image;
        }

        if ($request->hasFile('photo')) {
            $image = ProductImage::store($request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            $image = '';
        } else {
            $image = $data['image'] ?? (string) $oldImage;
        }

        $attributes = [
            'category_id' => $category->id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? '',
            'price'       => $data['price'],
            'stock'       => $data['stock'],
            'image'       => $image,
            'is_active'   => $data['is_active'] ?? 1,
        ];

        if ($product) {
            $product->update($attributes);

            if ($oldImage && $oldImage !== $image) {
                ProductImage::delete($oldImage);
            }

            return ApiResponse::success(null, 'Produk berhasil diperbarui');
        }

        Product::create($attributes);

        return ApiResponse::success(null, 'Produk baru berhasil ditambahkan', 201);
    }

    /**
     * Toggle the active flag of a menu item.
     */
    public function toggleActive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'        => ['required', 'integer'],
            'is_active' => ['required', 'integer', 'in:0,1'],
        ]);

        $ownerTypes = $request->user()->ownerTypes();

        $product = Product::whereIn('category_id', Category::whereIn('owner_type', $ownerTypes)->pluck('id'))
            ->findOrFail($data['id']);

        $product->update(['is_active' => $data['is_active']]);

        return ApiResponse::success(null, 'Status produk berhasil diubah');
    }

    /**
     * Delete a menu item permanently.
     */
    public function destroy(Request $request, int $product): JsonResponse
    {
        $ownerTypes = $request->user()->ownerTypes();

        $model = Product::whereIn('category_id', Category::whereIn('owner_type', $ownerTypes)->pluck('id'))
            ->findOrFail($product);

        $soldCount = $model->saleItems()->count();

        if ($soldCount > 0) {
            return ApiResponse::error(
                "Menu '{$model->name}' sudah pernah terjual ({$soldCount} item) sehingga tidak bisa dihapus "
                .'karena akan merusak riwayat laporan. Nonaktifkan saja agar tidak muncul di menu pelanggan.',
                409
            );
        }

        $name  = $model->name;
        $image = $model->image;
        $model->delete();

        ProductImage::delete($image);

        return ApiResponse::success(null, "Menu '{$name}' berhasil dihapus");
    }
}
