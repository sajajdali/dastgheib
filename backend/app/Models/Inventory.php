<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

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
}
