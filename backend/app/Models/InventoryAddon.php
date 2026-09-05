<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryAddon extends Model
{
    protected $fillable = ['inventory_id', 'addon_inventory_id'];
}
