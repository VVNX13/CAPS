<?php

namespace App\Services;

use App\Exceptions\DiscountException;
use App\Models\PromoCode;
use App\Models\Sale;
use App\Support\QrisHelper;

/**
 * Satu-satunya tempat yang menghitung potongan harga, supaya checkout pelanggan
 * dan diskon dari kasir selalu memakai aturan yang sama.
 *
 * Semua method di sini harus dipanggil di dalam DB::transaction().
 */
class DiscountService
{
    /**
     * Kunci kupon, cek masih berlaku, lalu hitung potongannya untuk $subtotal.
     * Kuota kupon langsung dipakai satu.
     *
     * @return array{0: PromoCode, 1: float}
     */
    public function claimPromo(string $code, float $subtotal): array
    {
        $promo = PromoCode::where('code', PromoCode::normalizeCode($code))->lockForUpdate()->first();

        if (! $promo) {
            throw new DiscountException('Kode kupon tidak ditemukan');
        }

        if ($reason = $promo->unavailableReason($subtotal)) {
            throw new DiscountException($reason);
        }

        $promo->increment('used_count');

        return [$promo, $promo->discountFor($subtotal)];
    }

    /**
     * Kembalikan kuota kupon yang sebelumnya terpakai oleh transaksi ini.
     */
    public function releasePromo(?string $code): void
    {
        if (! $code) {
            return;
        }

        PromoCode::where('code', $code)->where('used_count', '>', 0)->decrement('used_count');
    }

    /**
     * Pasang (atau hapus, jika $discount = 0) potongan pada tagihan yang belum dibayar.
     */
    public function applyToSale(Sale $sale, float $discount, ?string $promoCode, ?string $note): void
    {
        $this->assertEditable($sale);

        $subtotal = (float) ($sale->subtotal_amount ?? $sale->total_amount);
        $discount = min(max(0, floor($discount)), $subtotal);
        $total = $subtotal - $discount;

        $update = [
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discount,
            'promo_code' => $discount > 0 ? $promoCode : null,
            'discount_note' => $discount > 0 ? $note : null,
            'total_amount' => $total,
        ];

        // QR dinamis menyimpan nominal di dalamnya, jadi harus dibuat ulang.
        if ($sale->payment_method === 'qris') {
            $update['qris_payload'] = QrisHelper::convertStaticToDynamic(null, $total) ?: null;
            $update['qris_amount_total'] = $total;
        }

        $sale->update($update);
    }

    /**
     * Diskon hanya boleh diubah selama tagihan masih terbuka.
     */
    public function assertEditable(Sale $sale): void
    {
        if ($sale->isCanceled()) {
            throw new DiscountException('Pesanan ini sudah dibatalkan');
        }

        if ($sale->isPaid()) {
            throw new DiscountException('Tagihan sudah lunas, diskon tidak bisa diubah');
        }

        // Nominal transaksi QRIS-ify sudah terdaftar di gateway dan tidak bisa diubah dari sini.
        if ($sale->payment_method === 'qris' && $sale->qris_transaction_id) {
            throw new DiscountException('Nominal QRIS-ify sudah dikirim ke gateway. Minta pelanggan bayar tunai, atau batalkan dan buat pesanan baru');
        }
    }
}
