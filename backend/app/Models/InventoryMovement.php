<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'inventory_id', 'inventory_name', 'quantity', 'type', 'source_key',
        'appointment_id', 'description', 'occurred_at',
    ];

    protected $casts = ['quantity' => 'decimal:3', 'occurred_at' => 'datetime'];
}
