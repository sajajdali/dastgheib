<?php

namespace App\Support;

use App\Models\AppSetting;

class SatisfactionIntegration
{
    public static function settings(): array
    {
        $raw = AppSetting::getByKey('satisfaction_external_integration', '{}');
        $saved = is_string($raw) ? json_decode($raw, true) : $raw;

        return [
            'enabled' => (bool) ($saved['enabled'] ?? false),
            'page_url' => (string) ($saved['page_url'] ?? ''),
        ];
    }

}
