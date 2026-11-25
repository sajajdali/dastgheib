<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class BeautyAnnotation extends Model
{
    use Auditable;

    public const SOURCE_MEDIA = 'media';
    public const SOURCE_PROFILE = 'profile';
    public const SOURCE_TEMPLATE = 'template';

    public const REGION_FACE = 'face';
    public const REGION_BODY = 'body';
    public const REGION_BIKINI = 'bikini';

    public const REGIONS = [
        self::REGION_FACE => 'صورت',
        self::REGION_BODY => 'بدن',
        self::REGION_BIKINI => 'بیکینی',
    ];

    public function activitySection(): string { return 'زیبایار'; }
    public function activityLabel(): string { return trim(($this->area ?? '').' '.($this->problem ?? '')) ?: 'نقطه زیبایار #'.$this->getKey(); }

    protected $fillable = [
        'patient_id',
        'patient_media_id',
        'photo_source',
        'region',
        'created_by',
        'x_percent',
        'y_percent',
        'area',
        'problem',
        'note',
        'voice_path',
        'voice_duration',
        'status',
        'annotation_date',
    ];

    protected $casts = [
        'x_percent' => 'float',
        'y_percent' => 'float',
        'annotation_date' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function media()
    {
        return $this->belongsTo(PatientMedia::class, 'patient_media_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function regionKeys(): array
    {
        return array_keys(self::REGIONS);
    }

    public static function isValidRegion(?string $region): bool
    {
        return in_array((string) $region, self::regionKeys(), true);
    }
}
