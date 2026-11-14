<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PatientMediaFolder extends Model
{
    use Auditable;

    public function activitySection(): string { return 'پوشه عکس‌ها'; }

    protected $fillable = [
        'patient_id',
        'parent_id',
        'name',
        'folder_type',
        'folder_date',
        'inventory_id',
        'inventory_section_id',
        'inventory_section_ids',
        'services',
        'upload_description',
        'upload_usage_consent',
        'upload_is_featured',
        'photo_processing_settings',
        'sort_order',
    ];

    protected $casts = [
        'services' => 'array',
        'inventory_section_ids' => 'array',
        'upload_usage_consent' => 'boolean',
        'upload_is_featured' => 'boolean',
        'photo_processing_settings' => 'array',
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

    /** همهٔ خدمات پوشه؛ پوشه‌های قدیمی فقط inventory_section_id دارند. */
    public function sectionIds(): array
    {
        $ids = array_map('intval', array_filter((array) ($this->inventory_section_ids ?: [])));
        if ($ids === [] && $this->inventory_section_id) $ids = [(int) $this->inventory_section_id];

        return array_values(array_unique($ids));
    }
}
