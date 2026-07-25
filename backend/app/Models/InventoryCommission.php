<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCommission extends Model
{
    protected $fillable = [
        'inventory_id',
        'recipient_type',
        'recipient_id',
        'recipient_name',
        'commission_type',
        'commission_value',
    ];

    protected $casts = [
        'commission_value' => 'decimal:2',
    ];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }
}
