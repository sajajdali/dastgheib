<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientMediaFolder extends Model
{
    protected $fillable = [
        'patient_id',
        'parent_id',
        'name',
        'folder_type',
        'folder_date',
        'inventory_id',
        'inventory_section_id',
        'sort_order',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function media()
    {
        return $this->hasMany(PatientMedia::class, 'folder_id');
    }

    public function inventorySection()
    {
        return $this->belongsTo(InventorySection::class, 'inventory_section_id');
    }
}
