<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class CustomerMenuController extends Controller
{
    /**
     * Public self-order menu shown to customers after scanning the table QR code.
     */
    public function index(): View
    {
        $categories = Category::orderBy('id')->get();

        $products = Product::query()
            ->select('products.*')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('products.is_active', 1)
            ->orderBy('categories.owner_type')
            ->orderBy('products.name')
            ->with('category')
            ->get();

        // Lets the "Pesan Lagi" button on the order history page rebuild the
        // cart from product ids alone, without a second menu request.
        $reorderCatalog = $products->mapWithKeys(fn (Product $product) => [
            (string) $product->id => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'image' => $product->image ?? '',
                'category' => $product->category->name ?? '',
            ],
        ]);

        return view('customer.menu', compact('categories', 'products', 'reorderCatalog'));
    }
}
