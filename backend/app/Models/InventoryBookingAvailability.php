<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryBookingAvailability extends Model
{
    protected $guarded = [];
    protected $casts = ['active'=>'boolean'];
    public function resource() { return $this->belongsTo(InventoryBookingResource::class, 'booking_resource_id'); }
    public function breaks() { return $this->hasMany(InventoryBookingBreak::class, 'availability_id')->orderBy('start_time'); }
}
