<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentServiceBooking extends Model
{
    protected $guarded = [];
    protected $casts = ['starts_at'=>'datetime', 'ends_at'=>'datetime', 'cancelled_at'=>'datetime', 'metadata'=>'array'];
    public function appointment() { return $this->belongsTo(Appointment::class); }
    public function inventory() { return $this->belongsTo(Inventory::class); }
    public function doctor() { return $this->belongsTo(Doctor::class); }
    public function staff() { return $this->belongsTo(Staff::class); }
}
