<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HiddenAppointmentDay extends Model
{
    protected $fillable = ['month', 'day_num'];
}
