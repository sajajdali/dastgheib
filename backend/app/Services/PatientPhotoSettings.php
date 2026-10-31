<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\PatientMediaFolder;

class PatientPhotoSettings
{
    public const DEFAULTS = [
        'max_side' => 2400,
        'quality' => 88,
        'target_kb' => 1024,
    ];

    public function current(): array
    {
        $stored = json_decode((string) AppSetting::getByKey('patient_photo_settings', '{}'), true);

        return $this->normalize(is_array($stored) ? $stored : []);
    }

    public function forFolder(?PatientMediaFolder $folder): array
    {
        while ($folder) {
            if ($folder->folder_type === 'service' && is_array($folder->photo_processing_settings)) {
                return $this->normalize($folder->photo_processing_settings);
            }

            $folder = $folder->parent;
        }

        return $this->current();
    }

    public function normalize(array $settings): array
    {
        return [
            'max_side' => max(600, min(6000, (int) ($settings['max_side'] ?? self::DEFAULTS['max_side']))),
            'quality' => max(80, min(100, (int) ($settings['quality'] ?? self::DEFAULTS['quality']))),
            'target_kb' => max(100, min(10240, (int) ($settings['target_kb'] ?? self::DEFAULTS['target_kb']))),
        ];
    }

    public function save(array $settings): array
    {
        $value = $this->normalize($settings);
        AppSetting::updateOrCreate(
            ['key' => 'patient_photo_settings'],
            ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]
        );

        return $value;
    }
}
