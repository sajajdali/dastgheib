<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Inventory extends Model
{
    use HasFactory, Auditable;

    public function activitySection(): string { return 'انبار'; }

    protected $fillable = [
        'section_id',
        'name',
        'service_tags',
        'amount',
        'price',
        'count',
        'time',
        'stock',
        'min_stock',
        'active',
        'followup_days',
        'sort_order',
        'default_commission_type',
        'default_commission_value',
    ];

    protected $casts = [
        'active' => 'boolean',
        'service_tags' => 'array',
        'default_commission_value' => 'decimal:2',
    ];

    public function section()
    {
        return $this->belongsTo(InventorySection::class, 'section_id');
    }

    public function commissions()
    {
        return $this->hasMany(InventoryCommission::class);
    }

    public function defaultAddons(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'inventory_addons', 'inventory_id', 'addon_inventory_id')
            ->withTimestamps();
    }

    public function addonDefinitions(): BelongsToMany
    {
        return $this->belongsToMany(InventoryAddonDefinition::class, 'inventory_addon_assignments', 'inventory_id', 'inventory_addon_definition_id')
            ->withTimestamps();
    }
}
