<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerDashboardController extends Controller
{
    /**
     * Real-time kitchen queue for the signed-in owner.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('owner.dashboard', [
            'user' => $user,
            'ownerTypes' => implode(',', $user->ownerTypes()),
            'scopeLabel' => $user->scopeLabel(),
            'scopeBadge' => $user->scopeBadge(),
            'startWorkingLabel' => $user->startWorkingLabel(),
        ]);
    }

    /**
     * Current order items plus KPI counters for the dashboard.
     */
    public function getActiveOrders(Request $request): JsonResponse
    {
        $ownerTypes = $request->user()->ownerTypes();

        $orders = DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->whereIn('c.owner_type', $ownerTypes)
            ->orderByRaw("CASE si.item_status
                WHEN 'pending' THEN 1
                WHEN 'cooking' THEN 2
                WHEN 'done' THEN 3
                ELSE 4 END")
            ->orderByDesc('si.id')
            ->limit(100)
            ->get([
                'si.id as item_id',
                'si.sale_id',
                'si.quantity',
                'si.unit_price',
                'si.subtotal',
                'si.item_status',
                'si.notes as item_notes',
                'si.created_at',
                'p.name as product_name',
                'p.image as product_image',
                'c.name as category_name',
                'c.owner_type',
                's.invoice_number',
                's.customer_name',
                's.customer_phone',
                's.service_type',
                's.payment_method',
                's.payment_status',
                's.notes',
            ]);

        $today = now()->toDateString();

        $stats = DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->whereIn('c.owner_type', $ownerTypes)
            ->selectRaw("COUNT(CASE WHEN si.item_status = 'pending' THEN 1 END) AS pending_count")
            ->selectRaw("COUNT(CASE WHEN si.item_status = 'cooking' THEN 1 END) AS cooking_count")
            ->selectRaw("COUNT(CASE WHEN si.item_status = 'done' AND DATE(si.created_at) = ? THEN 1 END) AS done_today", [$today])
            ->selectRaw("COALESCE(SUM(CASE WHEN s.payment_status = 'paid' AND DATE(si.created_at) = ? THEN si.subtotal ELSE 0 END), 0) AS revenue_today", [$today])
            ->first();

        $lastId = DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->whereIn('c.owner_type', $ownerTypes)
            ->max('si.id');

        return ApiResponse::success([
            'orders' => $orders,
            'stats' => $stats,
            'last_id' => (int) $lastId,
        ]);
    }

    /**
     * Move a single order item through pending -> cooking -> done.
     */
    public function updateItemStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'status' => ['required', 'in:pending,cooking,done'],
        ]);

        DB::table('sale_items')->where('id', $data['item_id'])->update(['item_status' => $data['status']]);

        return ApiResponse::success(null, "Status pesanan berhasil diubah ke {$data['status']}");
    }

    /**
     * Manually confirm cash / QRIS payment collected at the counter.
     *
     * The sale must contain at least one item in the caller's categories.
     * Without this check any logged-in account could settle any transaction,
     * including one it cannot even see on the dashboard.
     */
    public function updatePaymentStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer'],
            'payment_status' => ['required', 'in:paid,unpaid'],
        ]);

        $saleId = (int) $data['sale_id'];

        $visible = DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->where('si.sale_id', $saleId)
            ->whereIn('c.owner_type', $request->user()->ownerTypes())
            ->exists();

        if (! $visible) {
            return ApiResponse::error('Transaksi tidak ditemukan dalam cakupan Anda', 404);
        }

        DB::table('sales')->where('id', $saleId)->update(['payment_status' => $data['payment_status']]);

        return ApiResponse::success(
            null,
            "Status pembayaran transaksi #{$saleId} telah diupdate ke {$data['payment_status']}"
        );
    }
}
