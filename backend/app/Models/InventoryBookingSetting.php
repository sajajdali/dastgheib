<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryBookingSetting extends Model
{
    protected $guarded = [];
    protected $casts = [
        'booking_enabled'=>'boolean', 'use_default_schedule'=>'boolean', 'requires_doctor'=>'boolean',
        'requires_operator'=>'boolean', 'conflict_check_enabled'=>'boolean', 'online_enabled'=>'boolean',
        'online_payment_enabled'=>'boolean', 'online_cancellation_enabled'=>'boolean', 'extra_settings'=>'array',
    ];
    public function inventory() { return $this->belongsTo(Inventory::class); }
}
