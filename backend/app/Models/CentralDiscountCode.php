<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentralDiscountCode extends Model
{
    protected $fillable = [
        'code',
        'title',
        'type',
        'value',
        'starts_at',
        'ends_at',
        'usage_limit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'value' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CentralDiscountRedemption::class);
    }
}
