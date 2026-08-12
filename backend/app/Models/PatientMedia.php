<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PatientMedia extends Model
{
    use Auditable;

    public function activitySection(): string { return 'عکس‌ها'; }
    public function activityLabel(): string { return $this->original_name ?: $this->file_name ?: 'رسانه #'.$this->getKey(); }

    protected $fillable = [
        'patient_id',
        'uploaded_by',
        'folder_id',
        'file_name',
        'original_name',
        'mime_type',
        'media_type',
        'path',
        'size',
        'is_featured',
        'usage_consent',
        'gender',
        'age_group',
        'description',
        'services',
        'comparison_stage',
        'photo_angle_key',
        'photo_angle_label',
        'photo_angle_degrees',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'usage_consent' => 'boolean',
        'services' => 'array',
    ];

    protected $appends = ['url'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function folder()
    {
        return $this->belongsTo(PatientMediaFolder::class, 'folder_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
