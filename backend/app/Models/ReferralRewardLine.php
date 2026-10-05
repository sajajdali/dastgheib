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

    public function appointment() { return $this->belongsTo(Appointment::class); }
    public function referrer() { return $this->belongsTo(Patient::class, 'referrer_patient_id'); }
    public function referred() { return $this->belongsTo(Patient::class, 'referred_patient_id'); }
}
