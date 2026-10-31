<?php

namespace App\Services\Assistant;

use App\Models\AppSetting;

final class AssistantSettings
{
    public function current(): array
    {
        $raw = AppSetting::getByKey('ai_assistant_settings', '{}');
        $stored = is_string($raw) ? json_decode($raw, true) : $raw;
        $stored = is_array($stored) ? $stored : [];
        $available = array_keys(config('assistant.models', []));
        $allowed = array_values(array_intersect($available, (array) ($stored['allowed_models'] ?? $available)));
        if ($allowed === []) $allowed = $available;
        $default = (string) ($stored['default_model'] ?? config('assistant.default_model'));
        if (! in_array($default, $allowed, true)) $default = $allowed[0] ?? '';

        return [
            'enabled' => (bool) ($stored['enabled'] ?? true),
            'default_model' => $default,
            'allowed_models' => $allowed,
            'daily_limit' => min(500, max(1, (int) ($stored['daily_limit'] ?? config('assistant.max_messages_per_day', 80)))),
        ];
    }

    public function save(array $data): array
    {
        $settings = $this->current();
        $settings = array_replace($settings, $data);
        AppSetting::updateOrCreate(
            ['key' => 'ai_assistant_settings'],
            ['value' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );

        return $settings;
    }

    public function publicData(): array
    {
        $settings = $this->current();
        return [
            ...$settings,
            'configured' => filled(config('assistant.api_key')),
            'models' => collect(config('assistant.models', []))
                ->only($settings['allowed_models'])
                ->map(fn ($label, $id) => ['id' => $id, 'label' => $label])
                ->values()
                ->all(),
            'available_models' => collect(config('assistant.models', []))
                ->map(fn ($label, $id) => ['id' => $id, 'label' => $label])
                ->values()
                ->all(),
        ];
    }
}
