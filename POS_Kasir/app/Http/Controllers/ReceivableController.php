<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cash orders that were never paid for.
 *
 * This is the screen that actually stops money from walking out of the door:
 * every unpaid sale, grouped by the phone number so the cashier can call or
 * WhatsApp one person once instead of hunting through the order list.
 *
 * Scoping note: payment lives on the sale, not on the item, so a sale is shown
 * when it contains at least one item in the viewer's categories. The whole
 * basket is displayed, because a single cash/QRIS transaction cannot be settled
 * in part.
 */
class ReceivableController extends Controller
{
    /**
     * Only sales from the last N days are chased. Anything older is treated as
     * a write-off rather than a debt worth calling about.
     */
    private const CHASE_WINDOW_DAYS = 30;

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('owner.unpaid', [
            'user' => $user,
            'scopeLabel' => $user->scopeLabel(),
            'scopeBadge' => $user->scopeBadge(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $ownerTypes = $request->user()->ownerTypes();

        $sales = DB::table('sales as s')
            ->join('sale_items as si', 'si.sale_id', '=', 's.id')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->where('s.payment_status', 'unpaid')
            ->where('s.created_at', '>=', now()->subDays(self::CHASE_WINDOW_DAYS))
            ->whereIn('c.owner_type', $ownerTypes)
            ->groupBy('s.id', 's.invoice_number', 's.customer_name', 's.customer_phone')
            ->select('s.id', 's.invoice_number', 's.customer_name', 's.customer_phone')
            ->selectRaw('MIN(s.created_at) AS created_at')
            ->selectRaw('SUM(s.total_amount) AS total_amount')
            ->orderBy('created_at')
            ->get();

        $items = DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->whereIn('si.sale_id', $sales->pluck('id'))
            ->whereIn('c.owner_type', $ownerTypes)
            ->select('si.sale_id', 'p.name as product_name', 'si.quantity', 'si.subtotal', 'c.owner_type')
            ->orderBy('si.id')
            ->get()
            ->groupBy('sale_id');

        $now = now();

        $rows = $sales->map(function ($sale) use ($items, $now) {
            $phone = $sale->customer_phone;
            $createdAt = Carbon::parse($sale->created_at);
            $minutes = $createdAt->diffInMinutes($now);
            $total = (float) $sale->total_amount;

            $message = sprintf(
                'Halo %s, pesanan Anda di Taman Teras Cafe (%s) belum dibayar. Total Rp %s. Terima kasih!',
                $sale->customer_name,
                $sale->invoice_number,
                number_format($total, 0, ',', '.')
            );

            return [
                'sale_id' => (int) $sale->id,
                'invoice_number' => $sale->invoice_number,
                'customer_name' => $sale->customer_name,
                'customer_phone' => $phone,
                // Grouping key is re-derived on read, not trusted from the row.
                // Rows written before phone validation existed can hold
                // "0812-3456-7890" or "000000", and those must still land in the
                // right bucket instead of splitting the same person in two.
                'phone_key' => PhoneNumber::normalize($phone) ?? ('raw-'.$sale->id),
                'total_amount' => $total,
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'age_minutes' => $minutes,
                'age_label' => $this->ageLabel($minutes),
                'is_overdue' => $minutes >= 60,
                'phone_usable' => PhoneNumber::isValid($phone),
                'tel_link' => PhoneNumber::telLink($phone),
                'whatsapp_link' => PhoneNumber::whatsappLink($phone, $message),
                'items' => ($items[$sale->id] ?? collect())
                    ->map(fn ($i) => [
                        'product_name' => $i->product_name,
                        'quantity' => (int) $i->quantity,
                        'subtotal' => (float) $i->subtotal,
                        'owner_type' => $i->owner_type,
                    ])
                    ->values(),
            ];
        })->values();

        // One person with three forgotten bills is one phone call, not three.
        $grouped = $rows
            ->groupBy('phone_key')
            ->map(function ($group) {
                $first = $group->first();
                $groupTotal = round($group->sum('total_amount'), 2);
                $count = $group->count();

                // The message must state what the owner is actually going to
                // ask for. Built per-bill it would quote only the first invoice
                // (Rp 3.000) while the screen shows the group total (Rp 6.000),
                // which is how a customer gets told they owe less than they do.
                $invoices = $group->pluck('invoice_number')->implode(', ');
                $message = $count > 1
                    ? sprintf(
                        'Halo %s, ada %d pesanan di Taman Teras Cafe yang belum dibayar (%s). Total tagihan Rp %s. Terima kasih!',
                        $first['customer_name'],
                        $count,
                        $invoices,
                        number_format($groupTotal, 0, ',', '.')
                    )
                    : sprintf(
                        'Halo %s, pesanan Anda di Taman Teras Cafe (%s) belum dibayar. Total Rp %s. Terima kasih!',
                        $first['customer_name'],
                        $invoices,
                        number_format($groupTotal, 0, ',', '.')
                    );

                return [
                    'customer_name' => $first['customer_name'],
                    'customer_phone' => PhoneNumber::normalize($first['customer_phone'])
                        ?? $first['customer_phone'],
                    'phone_usable' => $first['phone_usable'],
                    'tel_link' => $first['tel_link'],
                    'whatsapp_link' => PhoneNumber::whatsappLink(
                        $first['customer_phone'],
                        $message
                    ),
                    'sale_count' => $count,
                    'total_amount' => $groupTotal,
                    'oldest_minutes' => $group->max('age_minutes'),
                    'oldest_label' => $this->ageLabel((int) $group->max('age_minutes')),
                    'sales' => $group->values(),
                ];
            })
            ->sortByDesc('oldest_minutes')
            ->values();

        return ApiResponse::success([
            'groups' => $grouped,
            'summary' => [
                'sale_count' => $rows->count(),
                'customer_count' => $grouped->count(),
                'total_amount' => round($rows->sum('total_amount'), 2),
                'undialable_count' => $grouped->where('phone_usable', false)->count(),
            ],
            'window_days' => self::CHASE_WINDOW_DAYS,
        ]);
    }

    /**
     * Mark a chased bill as settled.
     *
     * Scope is enforced here: the sale must contain at least one item in the
     * caller's categories, otherwise a drink owner could settle a food-only
     * transaction they cannot even see.
     */
    public function markPaid(Request $request, int $saleId): JsonResponse
    {
        $ownerTypes = $request->user()->ownerTypes();

        $visible = DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->where('si.sale_id', $saleId)
            ->whereIn('c.owner_type', $ownerTypes)
            ->exists();

        if (! $visible) {
            return ApiResponse::error('Transaksi tidak ditemukan dalam cakupan Anda', 404);
        }

        $sale = DB::table('sales')->where('id', $saleId)->first();

        if ($sale === null) {
            return ApiResponse::error('Transaksi tidak ditemukan', 404);
        }

        DB::table('sales')->where('id', $saleId)->update(['payment_status' => 'paid']);

        return ApiResponse::success(null, "Pembayaran {$sale->invoice_number} ditandai lunas");
    }

    private function ageLabel(int $minutes): string
    {
        if ($minutes < 1) {
            return 'baru saja';
        }

        if ($minutes < 60) {
            return $minutes.' menit lalu';
        }

        $hours = intdiv($minutes, 60);

        if ($hours < 24) {
            return $hours.' jam lalu';
        }

        return intdiv($hours, 24).' hari lalu';
    }
}
