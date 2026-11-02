<?php

namespace App\Http\Controllers;

use App\Exceptions\DiscountException;
use App\Models\PromoCode;
use App\Models\Sale;
use App\Services\DiscountService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Potongan harga: cek kupon (pelanggan) dan diskon manual / kupon dari kasir.
 */
class DiscountController extends Controller
{
    public function __construct(private readonly DiscountService $discounts)
    {
    }

    /**
     * Pratinjau kupon di keranjang pelanggan. Hanya perkiraan: potongan yang
     * sah dihitung ulang saat checkout dari harga di database.
     */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'subtotal' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ], [
            'code.required' => 'Masukkan kode kupon',
        ]);

        $promo = PromoCode::findByCode($data['code']);
        $subtotal = (float) $data['subtotal'];

        if (! $promo) {
            return ApiResponse::error('Kode kupon tidak ditemukan', 422);
        }

        if ($reason = $promo->unavailableReason($subtotal)) {
            return ApiResponse::error($reason, 422);
        }

        return ApiResponse::success([
            'code' => $promo->code,
            'name' => $promo->name,
            'label' => $promo->label(),
            'discount' => $promo->discountFor($subtotal),
        ], "Kupon {$promo->code} dipakai");
    }

    /**
     * Kasir memasang diskon manual, kupon, atau menghapus diskon pada tagihan yang belum dibayar.
     */
    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer'],
            'mode' => ['required', 'in:manual,promo,remove'],
            'amount' => ['required_if:mode,manual', 'nullable', 'numeric', 'min:1', 'max:9999999999'],
            'code' => ['required_if:mode,promo', 'nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.required_if' => 'Masukkan nominal diskon',
            'amount.min' => 'Nominal diskon minimal Rp 1',
            'code.required_if' => 'Masukkan kode kupon',
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

        try {
            $sale = DB::transaction(function () use ($saleId, $data) {
                /** @var Sale $sale */
                $sale = Sale::lockForUpdate()->findOrFail($saleId);
                $subtotal = (float) ($sale->subtotal_amount ?? $sale->total_amount);
                $previousPromo = $sale->promo_code;

                // Cek status dulu sebelum menyentuh kuota kupon.
                $this->discounts->assertEditable($sale);

                if ($data['mode'] === 'manual' && (float) $data['amount'] > $subtotal) {
                    throw new DiscountException('Diskon tidak boleh melebihi total belanja '.rupiah($subtotal));
                }

                // Satu tagihan hanya boleh punya satu diskon; kupon lama dikembalikan kuotanya.
                $this->discounts->releasePromo($previousPromo);

                match ($data['mode']) {
                    'manual' => $this->discounts->applyToSale(
                        $sale,
                        (float) $data['amount'],
                        null,
                        trim((string) ($data['note'] ?? '')) ?: 'Diskon manual kasir'
                    ),
                    'promo' => (function () use ($sale, $data, $subtotal) {
                        [$promo, $discount] = $this->discounts->claimPromo($data['code'], $subtotal);
                        $this->discounts->applyToSale($sale, $discount, $promo->code, 'Kupon '.$promo->code);
                    })(),
                    'remove' => $this->discounts->applyToSale($sale, 0, null, null),
                };

                return $sale->fresh();
            });
        } catch (DiscountException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $message = (float) $sale->discount_amount > 0
            ? 'Diskon '.rupiah($sale->discount_amount)." dipasang. Tagihan sekarang ".rupiah($sale->total_amount)
            : 'Diskon dihapus. Tagihan kembali '.rupiah($sale->total_amount);

        return ApiResponse::success([
            'sale_id' => $sale->id,
            'subtotal_amount' => (float) $sale->subtotal_amount,
            'discount_amount' => (float) $sale->discount_amount,
            'promo_code' => $sale->promo_code,
            'total_amount' => (float) $sale->total_amount,
        ], $message);
    }
}
