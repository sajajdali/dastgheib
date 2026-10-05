<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralRewardLine extends Model
{
    protected $guarded = [];
    protected $casts = [
        'received_amount' => 'decimal:2', 'reward_value' => 'decimal:2',
        'reward_amount' => 'decimal:2', 'calculation_snapshot' => 'array',
        'earned_at' => 'datetime',
    ];
}
