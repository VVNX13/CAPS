<?php

namespace App\Http\Controllers;

use App\Events\OrderPlaced;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Rules\IndonesianPhoneNumber;
use App\Services\QrisifyService;
use App\Support\ApiResponse;
use App\Support\PhoneNumber;
use App\Support\QrisHelper;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrderController extends Controller
{
    /**
     * Create an order from the customer cart.
     */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'max:25', new IndonesianPhoneNumber],
            'customer_email' => ['nullable', 'email', 'max:100'],
            'service_type' => ['required', 'in:dine_in,takeaway'],
            'payment_method' => ['required', 'in:qris,cash'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'customer_name.required' => 'Nama pelanggan wajib diisi',
            'customer_name.max' => 'Nama pelanggan maksimal 100 karakter',
            'customer_phone.required' => 'Nomor WhatsApp pelanggan wajib diisi',
            'customer_email.email' => 'Format email pelanggan tidak valid',
            'service_type.required' => 'Pilih jenis layanan (dine in / takeaway)',
            'service_type.in' => 'Jenis layanan tidak valid',
            'payment_method.required' => 'Pilih metode pembayaran',
            'payment_method.in' => 'Metode pembayaran tidak valid',
            'items.required' => 'Keranjang pesanan masih kosong',
            'items.min' => 'Keranjang pesanan masih kosong',
            'items.*.product_id.exists' => 'Salah satu menu yang dipilih sudah tidak tersedia',
            'items.*.quantity.min' => 'Jumlah pesanan minimal 1',
        ]);

        // Store one canonical spelling so the same person is always matched to
        // their own orders, no matter how they typed the number.
        $data['customer_phone'] = PhoneNumber::normalize($data['customer_phone']);

        $items = collect($data['items']);
        $productIds = $items->pluck('product_id')->unique()->values();

        try {
            $sale = DB::transaction(function () use ($data, $items, $productIds): Sale {
                // Lock the touched products so concurrent checkouts cannot oversell stock.
                $products = Product::whereIn('id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $totalAmount = 0;
                $lines = [];

                foreach ($items as $item) {
                    $productId = (int) $item['product_id'];
                    $quantity = (int) $item['quantity'];

                    /** @var Product|null $product */
                    $product = $products->get($productId);

                    if (! $product) {
                        throw new RuntimeException("Produk dengan ID {$productId} tidak ditemukan");
                    }

                    if (! $product->is_active) {
                        throw new RuntimeException("Menu '{$product->name}' sedang tidak aktif");
                    }

                    if ($product->stock < $quantity) {
                        throw new RuntimeException("Stok untuk '{$product->name}' tidak mencukupi (Tersisa {$product->stock})");
                    }

                    $unitPrice = (float) $product->price;
                    $subtotal = $unitPrice * $quantity;
                    $totalAmount += $subtotal;

                    $product->decrement('stock', $quantity);

                    $lines[] = [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                        'item_status' => 'pending',
                    ];
                }

                $invoiceNumber = 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));

                $qrisPayload = null;
                $qrisTransactionId = null;
                $qrisAmountTotal = $totalAmount;

                if ($data['payment_method'] === 'qris') {
                    if (QrisifyService::isEnabled()) {
                        $qrisifyResponse = QrisifyService::createTransaction($invoiceNumber, $totalAmount);

                        if ($qrisifyResponse && ! empty($qrisifyResponse['qris_string'])) {
                            $qrisPayload = $qrisifyResponse['qris_string'];
                            $qrisTransactionId = $qrisifyResponse['transaction_id'] ?? null;
                            $qrisAmountTotal = (float) ($qrisifyResponse['amount_total'] ?? $totalAmount);
                        }
                    }

                    if (empty($qrisPayload)) {
                        $qrisPayload = QrisHelper::convertStaticToDynamic(null, $totalAmount);
                        $qrisAmountTotal = $totalAmount;
                    }
                }

                $sale = Sale::create([
                    'invoice_number' => $invoiceNumber,
                    'customer_name' => $data['customer_name'],
                    'customer_phone' => $data['customer_phone'],
                    'customer_email' => $data['customer_email'] ?? null,
                    'service_type' => $data['service_type'],
                    'total_amount' => $totalAmount,
                    'qris_amount_total' => $qrisAmountTotal,
                    'payment_method' => $data['payment_method'],
                    'payment_status' => 'unpaid',
                    'qris_payload' => $qrisPayload,
                    'qris_transaction_id' => $qrisTransactionId,
                    'order_status' => 'pending',
                    'notes' => $data['notes'] ?? null,
                ]);

                $sale->items()->createMany($lines);

                return $sale;
            });
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }

        $totalAmount = (float) $sale->total_amount;

        OrderPlaced::dispatch(
            $sale,
            $sale->items()->with('product.category')->get()
                ->map(fn (SaleItem $item) => $item->product->category->owner_type)
                ->unique()
                ->values()
                ->all()
        );

        return ApiResponse::success([
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'customer_name' => $sale->customer_name,
            'total_amount' => $totalAmount,
            'payment_method' => $sale->payment_method,
            'payment_status' => $sale->payment_status,
            'qris_payload' => $sale->qris_payload,
            'qris_image_url' => $sale->qris_payload ? QrisHelper::getQrImageUrl($sale->qris_payload, 300) : null,
            'receipt_url' => route('receipt', $sale->invoice_number),
        ], 'Pesanan berhasil dibuat!', 201);
    }

    /**
     * Customer-facing digital receipt for a single invoice.
     */
    public function receipt(string $invoiceNumber): View
    {
        $sale = Sale::where('invoice_number', $invoiceNumber)->firstOrFail();

        $items = $sale->items()
            ->with('product.category')
            ->get()
            ->map(function ($item) {
                return [
                    'product_name' => $item->product->name,
                    'category_name' => $item->product->category->name,
                    'owner_type' => $item->product->category->owner_type,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                ];
            });

        $qrisData = null;

        if ($sale->payment_method === 'qris') {
            $payload = $sale->qris_payload ?: QrisHelper::convertStaticToDynamic(null, (float) $sale->total_amount);
            $details = QrisHelper::parseQrisDetails($payload);
            $effectiveAmount = (float) ($sale->qris_amount_total ?: $sale->total_amount);
            $createdAt = $sale->created_at ? Carbon::parse($sale->created_at) : now();

            $qrisData = [
                'payload' => $payload,
                'image_url' => QrisHelper::getQrImageUrl($payload, 300),
                'merchant_name' => $details['merchant_name'] ?: 'Taman Teras, MAMAJANG',
                'merchant_city' => $details['merchant_city'] ?: 'MAKASSAR',
                'amount' => $effectiveAmount,
                'transaction_id' => $sale->qris_transaction_id,
                'remaining_seconds' => max(0, 900 - (int) abs($createdAt->diffInSeconds(now()))),
            ];
        }

        return view('customer.receipt', compact('sale', 'items', 'qrisData'));
    }

    /**
     * Poll the payment status of an invoice (used by the receipt page).
     */
    public function status(string $invoiceNumber): JsonResponse
    {
        $sale = Sale::where('invoice_number', $invoiceNumber)->first();

        if (! $sale) {
            return ApiResponse::error('Invoice tidak ditemukan', 404);
        }

        // Unpaid QRIS-ify orders are reconciled against the gateway on demand.
        if ($sale->payment_status === 'unpaid' && $sale->qris_transaction_id) {
            try {
                $transaction = QrisifyService::getTransaction($sale->qris_transaction_id);

                if (QrisifyService::isPaid($transaction)) {
                    $sale->update(['payment_status' => 'paid']);
                }
            } catch (\Throwable) {
                // Keep the cached status when the gateway is unreachable.
            }
        }

        return ApiResponse::success([
            'id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'payment_status' => $sale->payment_status,
            'order_status' => $sale->order_status,
            'qris_transaction_id' => $sale->qris_transaction_id,
        ]);
    }
}
