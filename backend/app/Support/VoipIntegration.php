<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * «ارتباط با VoIP»: one token per tenant for the call-center panel.
 * The token starts with the tenant id ("dg_<tenant>.<secret>"), so the panel
 * only needs the token: the request finds its site without a Host header.
 */
class VoipIntegration
{
    private const KEY = 'voip_integration';

    public static function settings(): array
    {
        $raw = AppSetting::getByKey(self::KEY, '{}');
        $saved = is_string($raw) ? json_decode($raw, true) : $raw;
        $saved = is_array($saved) ? $saved : [];

        return [
            'tenant_id' => (string) tenant('id'),
            'enabled' => (bool) ($saved['enabled'] ?? false),
            'incoming_calls_enabled' => (bool) ($saved['incoming_calls_enabled'] ?? false),
            'token' => self::decrypt($saved['token'] ?? ''),
            'created_at' => (string) ($saved['created_at'] ?? ''),
        ];
    }

    public static function setEnabled(bool $enabled): array
    {
        $current = self::settings();
        self::store($enabled, $current['incoming_calls_enabled'], $current['token'], $current['created_at']);

        return self::settings();
    }

    public static function setIncomingCallsEnabled(bool $enabled): array
    {
        $current = self::settings();
        self::store($current['enabled'], $enabled, $current['token'], $current['created_at']);

        return self::settings();
    }

    /** New token for the current tenant; the previous one stops working. */
    public static function generate(): array
    {
        $token = 'dg_'.tenant('id').'.'.Str::random(48);
        self::store(true, self::settings()['incoming_calls_enabled'], $token, now()->toIso8601String());

        return self::settings();
    }

    /** Tenant id written at the start of a token, or null when the token is not ours. */
    public static function tenantIdFrom(string $token): ?string
    {
        if (! str_starts_with($token, 'dg_')) return null;
        $dot = strrpos($token, '.');

        return $dot > 3 ? substr($token, 3, $dot - 3) : null;
    }

    public static function accepts(string $given): bool
    {
        $s = self::settings();

        return $s['enabled'] && $s['token'] !== '' && hash_equals($s['token'], $given);
    }

    public static function acceptsIncomingCalls(string $given): bool
    {
        return self::settings()['incoming_calls_enabled'] && self::accepts($given);
    }

    private static function store(bool $enabled, bool $incomingCallsEnabled, string $token, string $createdAt): void
    {
        AppSetting::updateOrCreate(['key' => self::KEY], [
            'value' => json_encode([
                'enabled' => $enabled,
                'incoming_calls_enabled' => $incomingCallsEnabled,
                'token' => $token === '' ? '' : Crypt::encryptString($token),
                'created_at' => $createdAt,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private static function decrypt(string $value): string
    {
        if ($value === '') return '';
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }
}
