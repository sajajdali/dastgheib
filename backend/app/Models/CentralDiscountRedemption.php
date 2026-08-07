<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentralDiscountRedemption extends Model
{
    protected $fillable = [
        'central_discount_code_id',
        'tenant_id',
        'tenant_name',
        'buyer_name',
        'buyer_email',
        'subtotal',
        'discount_amount',
        'payable_total',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'payable_total' => 'integer',
            'used_at' => 'datetime',
        ];
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(CentralDiscountCode::class, 'central_discount_code_id');
    }
}
