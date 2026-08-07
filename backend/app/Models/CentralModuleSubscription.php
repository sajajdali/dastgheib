<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralModuleSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'module_key',
        'module_title',
        'billing_period',
        'duration_days',
        'price_paid',
        'starts_at',
        'expires_at',
        'last_paid_at',
        'status',
        'renewed_from_id',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'price_paid' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_paid_at' => 'datetime',
        ];
    }

    public function isLifetime(): bool
    {
        return $this->billing_period === 'one_time' || $this->expires_at === null;
    }
}
