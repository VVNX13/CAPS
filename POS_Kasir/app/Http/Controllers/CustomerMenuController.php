<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

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
            ->where('products.stock', '>', 0)
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

        $salesCounts = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.payment_status', 'paid')
            ->where('s.order_status', '!=', 'canceled')
            ->where('s.created_at', '>=', now()->subDays(30))
            ->whereIn('si.product_id', $products->modelKeys())
            ->groupBy('si.product_id')
            ->selectRaw('si.product_id, SUM(si.quantity) as quantity_sold')
            ->orderByDesc('quantity_sold')
            ->orderBy('si.product_id')
            ->limit(3)
            ->pluck('quantity_sold', 'si.product_id');

        $recommendations = $salesCounts->map(function ($quantity, $id) use ($products) {
            $product = $products->firstWhere('id', $id);
            $product->quantity_sold = (int) $quantity;

            return $product;
        })->values();
        $bestsellerIds = $recommendations->pluck('id')->all();

        return view('customer.menu', compact('categories', 'products', 'reorderCatalog', 'recommendations', 'bestsellerIds'));
    }
}
