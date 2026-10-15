<?php

namespace App\Models;

use App\Models\Concerns\HasSaveVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InventoryAddonDefinition extends Model
{
    use HasSaveVersion;

    protected $appends = ['save_version'];
    protected $fillable = ['name', 'amount', 'price', 'stock', 'min_stock', 'active', 'sort_order'];

    protected $casts = ['active' => 'boolean'];

    public function inventories(): BelongsToMany
    {
        return $this->belongsToMany(Inventory::class, 'inventory_addon_assignments', 'inventory_addon_definition_id', 'inventory_id')
            ->withTimestamps();
    }
}
