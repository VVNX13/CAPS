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
            'scopeLabel' => $user->isAdmin() ? 'Kasir' : $user->scopeLabel(),
            'dashboardTitle' => $user->isAdmin() ? 'Dashboard Kasir' : ('Dashboard ' . $user->scopeLabel()),
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
            ->where('s.order_status', '!=', 'canceled')
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
                's.table_number',
                's.payment_method',
                's.payment_status',
                's.subtotal_amount',
                's.discount_amount',
                's.promo_code',
                's.discount_note',
                's.total_amount',
                's.notes',
            ]);

        $today = now()->toDateString();

        $stats = DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->whereIn('c.owner_type', $ownerTypes)
            ->where('s.order_status', '!=', 'canceled')
            ->selectRaw("COUNT(CASE WHEN si.item_status = 'pending' THEN 1 END) AS pending_count")
            ->selectRaw("COUNT(CASE WHEN si.item_status = 'cooking' THEN 1 END) AS cooking_count")
            ->selectRaw("COUNT(CASE WHEN si.item_status = 'done' AND DATE(si.created_at) = ? THEN 1 END) AS done_today", [$today])
            // Omzet bersih: diskon transaksi dibagi rata ke itemnya sesuai porsi harga.
            ->selectRaw("COALESCE(SUM(CASE WHEN s.payment_status = 'paid' AND DATE(si.created_at) = ? THEN CASE WHEN COALESCE(s.subtotal_amount, 0) > 0 THEN si.subtotal * s.total_amount / s.subtotal_amount ELSE si.subtotal END ELSE 0 END), 0) AS revenue_today", [$today])
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

        $canceled = DB::table('sale_items as si')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->where('si.id', $data['item_id'])
            ->where('s.order_status', 'canceled')
            ->exists();

        if ($canceled) {
            return ApiResponse::error('Pesanan ini sudah dibatalkan', 422);
        }

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
            'cash_received' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
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

        $sale = DB::table('sales')->where('id', $saleId)->first(['payment_method', 'total_amount', 'order_status']);

        if ($sale->order_status === 'canceled') {
            return ApiResponse::error('Pesanan ini sudah dibatalkan, pembayaran tidak bisa diubah', 422);
        }

        $update = [
            'payment_status' => $data['payment_status'],
            'cash_received' => null,
            'cash_change' => null,
        ];

        // Kembalian dihitung ulang di server supaya angka di struk tidak bisa dimanipulasi.
        if ($data['payment_status'] === 'paid' && $sale->payment_method === 'cash' && isset($data['cash_received'])) {
            $received = round((float) $data['cash_received'], 2);
            $total = (float) $sale->total_amount;

            if ($received < $total) {
                return ApiResponse::error('Uang yang diterima kurang dari total tagihan', 422);
            }

            $update['cash_received'] = $received;
            $update['cash_change'] = round($received - $total, 2);
        }

        DB::table('sales')->where('id', $saleId)->update($update);

        return ApiResponse::success(
            null,
            "Status pembayaran transaksi #{$saleId} telah diupdate ke {$data['payment_status']}"
        );
    }

    /**
     * Batalkan seluruh pesanan (void order) dan kembalikan stok menunya.
     *
     * Pembatalan berlaku untuk satu transaksi utuh, sama seperti pembayaran,
     * karena satu invoice tidak bisa dibatalkan sebagian. Semuanya berjalan
     * dalam satu transaksi DB dengan row lock supaya klik ganda atau dua kasir
     * yang membatalkan bersamaan tidak mengembalikan stok dua kali.
     */
    public function cancelOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'reason.required' => 'Alasan pembatalan wajib diisi',
            'reason.min' => 'Alasan pembatalan minimal 3 karakter',
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

        $result = DB::transaction(function () use ($saleId, $data, $request) {
            $sale = DB::table('sales')->where('id', $saleId)->lockForUpdate()->first();

            if ($sale->order_status === 'canceled') {
                return null;
            }

            $items = DB::table('sale_items')
                ->where('sale_id', $saleId)
                ->selectRaw('product_id, SUM(quantity) AS quantity')
                ->groupBy('product_id')
                ->get();

            // Kunci baris produk dulu (urut id) supaya penambahan stok tidak bentrok dengan checkout.
            $productIds = $items->pluck('product_id')->sort()->values();
            DB::table('products')->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get(['id']);

            foreach ($items as $item) {
                DB::table('products')->where('id', $item->product_id)->increment('stock', (int) $item->quantity);
            }

            // Kupon yang terpakai pesanan ini bisa dipakai lagi.
            if ($sale->promo_code) {
                DB::table('promo_codes')->where('code', $sale->promo_code)->where('used_count', '>', 0)->decrement('used_count');
            }

            DB::table('sales')->where('id', $saleId)->update([
                'order_status' => 'canceled',
                'canceled_at' => now(),
                'canceled_by' => $request->user()->id,
                'cancel_reason' => trim($data['reason']),
                'updated_at' => now(),
            ]);

            return [
                'invoice_number' => $sale->invoice_number,
                'was_paid' => $sale->payment_status === 'paid',
                'restocked_items' => (int) $items->sum('quantity'),
            ];
        });

        if ($result === null) {
            return ApiResponse::error('Pesanan ini sudah dibatalkan sebelumnya', 422);
        }

        $message = "Pesanan {$result['invoice_number']} dibatalkan, {$result['restocked_items']} porsi dikembalikan ke stok";

        if ($result['was_paid']) {
            $message .= '. Pesanan sudah lunas, jangan lupa kembalikan uang pelanggan';
        }

        return ApiResponse::success($result, $message);
    }
}
