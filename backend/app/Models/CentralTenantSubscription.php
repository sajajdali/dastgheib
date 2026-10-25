<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralTenantSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PENDING = 'pending';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'plan_id', 'plan_name', 'duration_days',
        'licensed_users', 'included_users', 'base_price', 'extra_user_price',
        'price_paid', 'starts_at', 'expires_at', 'paid_at', 'status',
        'payment_reference', 'renewed_from_id',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'licensed_users' => 'integer',
            'included_users' => 'integer',
            'base_price' => 'integer',
            'extra_user_price' => 'integer',
            'price_paid' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
