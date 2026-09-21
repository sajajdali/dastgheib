<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryBookingException extends Model
{
    protected $guarded = [];
    protected $casts = ['exception_date'=>'date:Y-m-d', 'metadata'=>'array'];
}
