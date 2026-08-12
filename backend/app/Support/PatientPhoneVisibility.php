<?php

namespace App\Support;

use Illuminate\Http\Request;

class PatientPhoneVisibility
{
    public const PERMISSION = 'patients.view_phone';

    public static function canView(?Request $request): bool
    {
        return (bool) $request?->user()?->can(self::PERMISSION);
    }

    public static function mask(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: $phone;
        if (mb_strlen($digits) <= 4) {
            return '••••';
        }

        return mb_substr($digits, 0, 3).'••••'.mb_substr($digits, -2);
    }

    public static function looksMasked(mixed $value): bool
    {
        return str_contains((string) $value, '•') || str_contains((string) $value, '*');
    }

    public static function hideValue(mixed $value, ?Request $request): mixed
    {
        return self::canView($request) ? $value : self::mask((string) $value);
    }

    public static function hideArrayPhones(array $row, ?Request $request, array $keys = ['phone', 'second_phone', 'referrer_phone', 'patient_phone']): array
    {
        if (self::canView($request)) {
            return $row;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $row[$key] = self::mask((string) $row[$key]);
            }
        }

        return $row;
    }
}
