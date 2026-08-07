<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralBillingPlan extends Model
{
    protected $fillable = [
        'name',
        'duration_days',
        'base_price',
        'is_trial',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'base_price' => 'integer',
            'is_trial' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
