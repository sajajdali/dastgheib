<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryBookingResource extends Model
{
    protected $guarded = [];
    protected $casts = ['active'=>'boolean'];
    public function inventory() { return $this->belongsTo(Inventory::class); }
    public function doctor() { return $this->belongsTo(Doctor::class); }
    public function staff() { return $this->belongsTo(Staff::class); }
    public function availabilities() { return $this->hasMany(InventoryBookingAvailability::class, 'booking_resource_id')->orderBy('weekday')->orderBy('start_time'); }
}
