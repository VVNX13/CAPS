<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'customer_name',
        'customer_phone',
        'customer_email',
        'service_type',
        'table_number',
        'total_amount',
        'qris_amount_total',
        'payment_method',
        'payment_status',
        'cash_received',
        'cash_change',
        'qris_payload',
        'qris_transaction_id',
        'order_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'qris_amount_total' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'cash_change' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}
