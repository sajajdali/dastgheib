<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryBookingRule extends Model
{
    protected $guarded = [];
    protected $casts = ['rule_value'=>'array', 'active'=>'boolean'];
}
