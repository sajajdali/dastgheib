<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventorySection extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'section_id');
    }
}
