<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Doctor extends Model
{
    use Auditable;

    public function activitySection(): string { return 'پزشکان'; }

    protected $fillable = [
        'name',
        'user_id',
        'bonus',
        'commission_customer_scope',
        'commission_after_materials',
        'sales_bonus_enabled',
        'sales_bonus_tiers',
        'salary',
        'hourly_rate',
        'overtime_hourly_rate',
        'shortage_hourly_deduction',
        'absence_deduction',
        'allowed_shortage_hours',
        'available_days',
        'service_section_ids',
        'profile_photo_path',
        'profile_thumbnail_path',
    ];

    protected $casts = [
        'available_days' => 'array',
        'service_section_ids' => 'array',
        'commission_after_materials' => 'boolean',
        'sales_bonus_enabled' => 'boolean',
        'sales_bonus_tiers' => 'array',
        'allowed_shortage_hours' => 'decimal:2',
    ];

    protected $appends = ['profile_photo_url', 'profile_thumbnail_url', 'avatar_url'];

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path
            ? Storage::disk('public')->url($this->profile_photo_path)
            : null;
    }

    public function getProfileThumbnailUrlAttribute(): ?string
    {
        return $this->profile_thumbnail_path
            ? Storage::disk('public')->url($this->profile_thumbnail_path)
            : null;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->profile_thumbnail_url ?: $this->profile_photo_url;
    }
}
