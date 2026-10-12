<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PollingController extends Controller
{
    /**
     * Lightweight endpoint polled by the owner dashboard to detect new order items.
     */
    public function checkNew(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'last_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $types = array_values(array_intersect(
            ['makanan', 'minuman'],
            array_map('trim', explode(',', $data['type']))
        ));

        if ($types === []) {
            return ApiResponse::error('Parameter type harus makanan atau minuman', 422, [
                'type' => ['Parameter type harus makanan atau minuman'],
            ]);
        }

        $lastId = (int) ($data['last_id'] ?? 0);

        $newItems = DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->whereIn('c.owner_type', $types)
            ->where('si.id', '>', $lastId)
            ->orderBy('si.id')
            ->get([
                'si.id as item_id',
                'si.sale_id',
                'si.quantity',
                'si.unit_price',
                'si.subtotal',
                'si.item_status',
                'si.created_at',
                'p.name as product_name',
                'c.name as category_name',
                'c.owner_type',
                's.invoice_number',
                's.customer_name',
                's.customer_phone',
                's.service_type',
                's.payment_method',
                's.payment_status',
                's.order_status',
            ]);

        $count = $newItems->count();
        $latestId = $count > 0 ? (int) $newItems->last()->item_id : $lastId;

        return ApiResponse::success([
            'owner_type' => $types,
            'has_new_order' => $count > 0,
            'count' => $count,
            'latest_id' => $latestId,
            'items' => $newItems,
        ]);
    }
}
