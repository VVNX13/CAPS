<?php

namespace App\Http\Controllers;

use App\Rules\IndonesianPhoneNumber;
use App\Support\ApiResponse;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customer-facing order history, looked up by phone number.
 *
 * There is no customer account in this app, so the phone number is the only
 * identity a returning customer has. That same number is also what the cashier
 * uses to chase an unpaid bill, which is why it is validated strictly now.
 *
 * Privacy: the lookup returns only what the customer already knows about
 * themselves, is limited to a recent window, and reveals nothing about anyone
 * else's orders.
 */
class CustomerHistoryController extends Controller
{
    private const WINDOW_DAYS = 90;

    private const MAX_ORDERS = 30;

    public function index(): View
    {
        return view('customer.history', [
            'windowDays' => self::WINDOW_DAYS,
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:25', new IndonesianPhoneNumber],
        ], [
            'phone.required' => 'Masukkan nomor WhatsApp Anda',
        ]);

        $phone = PhoneNumber::normalize($data['phone']);

        $orders = DB::table('sales')
            ->where('customer_phone', $phone)
            ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->orderByDesc('created_at')
            ->limit(self::MAX_ORDERS)
            ->get(['id', 'invoice_number', 'customer_name', 'service_type', 'total_amount',
                'payment_method', 'payment_status', 'order_status', 'created_at']);

        $items = DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->whereIn('si.sale_id', $orders->pluck('id'))
            ->select('si.sale_id', 'si.product_id', 'p.name as product_name', 'si.quantity', 'si.subtotal', 'si.notes')
            ->orderBy('si.id')
            ->get()
            ->groupBy('sale_id');

        $now = now();

        $formatted = $orders->map(function ($order) use ($items, $now) {
            $createdAt = Carbon::parse($order->created_at);

            return [
                'invoice_number' => $order->invoice_number,
                'customer_name' => $order->customer_name,
                'service_type' => $order->service_type,
                'total_amount' => (float) $order->total_amount,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'is_paid' => $order->payment_status === 'paid',
                'order_status' => $order->order_status,
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'date_label' => $createdAt->format('d M Y, H:i'),
                'age_label' => $this->ageLabel($createdAt->diffInMinutes($now)),
                'receipt_url' => route('receipt', $order->invoice_number),
                'items' => ($items[$order->id] ?? collect())->map(fn ($i) => [
                    'product_id' => (int) $i->product_id,
                    'product_name' => $i->product_name,
                    'quantity' => (int) $i->quantity,
                    'subtotal' => (float) $i->subtotal,
                    'notes' => $i->notes,
                ])->values(),
            ];
        })->values();

        return ApiResponse::success([
            'phone' => $phone,
            'orders' => $formatted,
            'summary' => [
                'order_count' => $formatted->count(),
                'unpaid_count' => $formatted->where('is_paid', false)->count(),
                'unpaid_amount' => round($formatted->where('is_paid', false)->sum('total_amount'), 2),
                'total_spent' => round($formatted->where('is_paid', true)->sum('total_amount'), 2),
            ],
        ]);
    }

    private function ageLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return max($minutes, 1).' menit lalu';
        }

        $hours = intdiv($minutes, 60);

        if ($hours < 24) {
            return $hours.' jam lalu';
        }

        return intdiv($hours, 24).' hari lalu';
    }
}
