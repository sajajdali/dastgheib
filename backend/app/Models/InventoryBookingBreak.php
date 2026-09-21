<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryBookingBreak extends Model
{
    protected $guarded = [];
    protected $casts = ['active'=>'boolean'];
    public function availability() { return $this->belongsTo(InventoryBookingAvailability::class, 'availability_id'); }
}
