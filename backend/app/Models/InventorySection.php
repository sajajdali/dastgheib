<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class InventorySection extends Model
{
    use Auditable;

    public function activitySection(): string { return 'بخش‌های خدمات'; }

    protected $fillable = [
        'parent_id',
        'level',
        'name',
        'sort_order',
    ];

    protected $casts = [
        'level' => 'integer',
    ];

    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'section_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
