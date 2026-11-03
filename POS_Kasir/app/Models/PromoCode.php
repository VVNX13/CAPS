<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kupon promo yang bisa dipakai pelanggan saat checkout atau dipasang kasir
 * pada tagihan yang belum dibayar.
 */
class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'min_order',
        'max_discount',
        'usage_limit',
        'used_count',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function normalizeCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    public static function findByCode(?string $code): ?self
    {
        $code = self::normalizeCode($code);

        return $code === '' ? null : self::where('code', $code)->first();
    }

    /**
     * Alasan kupon tidak bisa dipakai untuk belanja sebesar $subtotal, atau null jika bisa.
     */
    public function unavailableReason(float $subtotal): ?string
    {
        $today = now()->startOfDay();

        if (! $this->is_active) {
            return "Kupon {$this->code} sedang tidak aktif";
        }

        if ($this->starts_at && $today->lt($this->starts_at)) {
            return "Kupon {$this->code} baru berlaku mulai ".$this->starts_at->format('d/m/Y');
        }

        if ($this->ends_at && $today->gt($this->ends_at)) {
            return "Kupon {$this->code} sudah kedaluwarsa";
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return "Kuota kupon {$this->code} sudah habis";
        }

        if ($subtotal < (float) $this->min_order) {
            return "Kupon {$this->code} berlaku untuk belanja minimal ".rupiah($this->min_order);
        }

        return null;
    }

    /**
     * Nominal potongan untuk belanja sebesar $subtotal (dibulatkan ke rupiah, tidak melebihi subtotal).
     */
    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === 'percent'
            ? $subtotal * (float) $this->value / 100
            : (float) $this->value;

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return (float) min(floor($discount), $subtotal);
    }

    public function label(): string
    {
        return $this->type === 'percent'
            ? rtrim(rtrim(number_format((float) $this->value, 2, ',', '.'), '0'), ',').'%'
            : rupiah($this->value);
    }
}
