<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentralStoreTermAcceptance extends Model
{
    protected $fillable = [
        'central_store_term_id',
        'tenant_id',
        'tenant_name',
        'user_id',
        'buyer_name',
        'buyer_email',
        'items',
        'subtotal',
        'discount_amount',
        'payable_total',
        'accepted_at',
        'paid_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'payable_total' => 'integer',
            'accepted_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(CentralStoreTerm::class, 'central_store_term_id');
    }
}
