<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitchenController extends Controller
{
    /**
     * Kitchen Display System (KDS) — main view.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('dapur.dashboard', [
            'user' => $user,
        ]);
    }

    /**
     * Active orders for the kitchen: all items in pending/cooking status.
     * Price info is intentionally excluded.
     */
    public function getOrders(Request $request): JsonResponse
    {
        $orders = DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->whereIn('si.item_status', ['pending', 'cooking'])
            ->where('s.order_status', '!=', 'canceled')
            ->orderByRaw("CASE si.item_status WHEN 'pending' THEN 1 WHEN 'cooking' THEN 2 ELSE 3 END")
            ->orderBy('si.id')
            ->limit(150)
            ->get([
                'si.id as item_id',
                'si.sale_id',
                'si.quantity',
                'si.item_status',
                'si.notes as item_notes',
                'si.created_at',
                'p.name as product_name',
                'c.name as category_name',
                'c.owner_type',
                's.invoice_number',
                's.customer_name',
                's.service_type',
                's.table_number',
                's.notes as order_notes',
            ]);

        // Group by sale_id so the KDS shows one ticket per order
        $grouped = [];
        foreach ($orders as $item) {
            $sid = $item->sale_id;
            if (! isset($grouped[$sid])) {
                $grouped[$sid] = [
                    'sale_id'        => $sid,
                    'invoice_number' => $item->invoice_number,
                    'customer_name'  => $item->customer_name,
                    'service_type'   => $item->service_type,
                    'table_number'   => $item->table_number,
                    'order_notes'    => $item->order_notes,
                    'created_at'     => $item->created_at,
                    'items'          => [],
                ];
            }
            $grouped[$sid]['items'][] = [
                'item_id'       => $item->item_id,
                'product_name'  => $item->product_name,
                'category_name' => $item->category_name,
                'owner_type'    => $item->owner_type,
                'quantity'      => $item->quantity,
                'item_status'   => $item->item_status,
                'item_notes'    => $item->item_notes,
            ];
        }

        // Derive ticket-level status: cooking if any item is cooking, else pending
        $tickets = array_values(array_map(function ($ticket) {
            $statuses = array_column($ticket['items'], 'item_status');
            $ticket['ticket_status'] = in_array('cooking', $statuses, true) ? 'cooking' : 'pending';
            return $ticket;
        }, $grouped));

        // KPI
        $pendingCount = DB::table('sale_items as si')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->where('si.item_status', 'pending')
            ->where('s.order_status', '!=', 'canceled')
            ->count();

        $cookingCount = DB::table('sale_items as si')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->where('si.item_status', 'cooking')
            ->where('s.order_status', '!=', 'canceled')
            ->count();

        $doneToday = DB::table('sale_items as si')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->where('si.item_status', 'done')
            ->where('s.order_status', '!=', 'canceled')
            ->whereDate('si.updated_at', now()->toDateString())
            ->count();

        $lastItemId = DB::table('sale_items')->max('id');

        return ApiResponse::success([
            'tickets'      => $tickets,
            'pending'      => $pendingCount,
            'cooking'      => $cookingCount,
            'done_today'   => $doneToday,
            'last_item_id' => (int) $lastItemId,
        ]);
    }

    /**
     * History of completed orders today (and optionally older).
     */
    public function getHistory(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        $items = DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->where('si.item_status', 'done')
            ->where('s.order_status', '!=', 'canceled')
            ->whereDate('si.updated_at', $date)
            ->orderByDesc('si.updated_at')
            ->limit(200)
            ->get([
                'si.id as item_id',
                'si.sale_id',
                'si.quantity',
                'si.updated_at',
                'si.notes as item_notes',
                'p.name as product_name',
                'c.name as category_name',
                's.invoice_number',
                's.customer_name',
                's.service_type',
                's.table_number',
            ]);

        // Group by sale
        $grouped = [];
        foreach ($items as $item) {
            $sid = $item->sale_id;
            if (! isset($grouped[$sid])) {
                $grouped[$sid] = [
                    'sale_id'        => $sid,
                    'invoice_number' => $item->invoice_number,
                    'customer_name'  => $item->customer_name,
                    'service_type'   => $item->service_type,
                    'table_number'   => $item->table_number,
                    'completed_at'   => $item->updated_at,
                    'items'          => [],
                ];
            }
            $grouped[$sid]['items'][] = [
                'product_name'  => $item->product_name,
                'category_name' => $item->category_name,
                'quantity'      => $item->quantity,
                'item_notes'    => $item->item_notes,
            ];
        }

        return ApiResponse::success(['history' => array_values($grouped)]);
    }

    /**
     * Start cooking for all pending items of a sale in one click.
     */
    public function startCookingSale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer'],
        ]);

        $canceled = DB::table('sales')
            ->where('id', $data['sale_id'])
            ->where('order_status', 'canceled')
            ->exists();

        if ($canceled) {
            return ApiResponse::error('Pesanan ini sudah dibatalkan', 422);
        }

        DB::table('sale_items')
            ->where('sale_id', $data['sale_id'])
            ->where('item_status', 'pending')
            ->update([
                'item_status' => 'cooking',
                'updated_at'  => now(),
            ]);

        DB::table('sales')
            ->where('id', $data['sale_id'])
            ->where('order_status', 'pending')
            ->update([
                'order_status' => 'processing',
                'updated_at'   => now(),
            ]);

        return ApiResponse::success(null, 'Pesanan mulai dimasak');
    }

    /**
     * Update a single item's status (kitchen only: pending→cooking or cooking→done).
     */
    public function updateItemStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'status'  => ['required', 'in:cooking,done'],
        ]);

        $saleItem = DB::table('sale_items as si')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->where('si.id', $data['item_id'])
            ->select('si.id', 'si.sale_id', 's.order_status')
            ->first();

        if (! $saleItem) {
            return ApiResponse::error('Item tidak ditemukan', 404);
        }

        if ($saleItem->order_status === 'canceled') {
            return ApiResponse::error('Pesanan ini sudah dibatalkan', 422);
        }

        DB::table('sale_items')
            ->where('id', $data['item_id'])
            ->update([
                'item_status' => $data['status'],
                'updated_at'  => now(),
            ]);

        // Sync order-level status
        if ($data['status'] === 'cooking' && $saleItem->order_status === 'pending') {
            DB::table('sales')
                ->where('id', $saleItem->sale_id)
                ->where('order_status', 'pending')
                ->update(['order_status' => 'processing', 'updated_at' => now()]);
        } elseif ($data['status'] === 'done') {
            $hasRemaining = DB::table('sale_items')
                ->where('sale_id', $saleItem->sale_id)
                ->whereIn('item_status', ['pending', 'cooking'])
                ->exists();

            if (! $hasRemaining) {
                DB::table('sales')
                    ->where('id', $saleItem->sale_id)
                    ->where('order_status', '!=', 'canceled')
                    ->update(['order_status' => 'completed', 'updated_at' => now()]);
            }
        }

        return ApiResponse::success(null, "Status item diubah ke {$data['status']}");
    }

    /**
     * Mark ALL items of a sale as done in one click.
     */
    public function completeSale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer'],
        ]);

        $canceled = DB::table('sales')
            ->where('id', $data['sale_id'])
            ->where('order_status', 'canceled')
            ->exists();

        if ($canceled) {
            return ApiResponse::error('Pesanan ini sudah dibatalkan', 422);
        }

        DB::table('sale_items')
            ->where('sale_id', $data['sale_id'])
            ->whereIn('item_status', ['pending', 'cooking'])
            ->update([
                'item_status' => 'done',
                'updated_at'  => now(),
            ]);

        DB::table('sales')
            ->where('id', $data['sale_id'])
            ->where('order_status', '!=', 'canceled')
            ->update([
                'order_status' => 'completed',
                'updated_at'   => now(),
            ]);

        return ApiResponse::success(null, 'Semua item pesanan selesai diproses');
    }
}

