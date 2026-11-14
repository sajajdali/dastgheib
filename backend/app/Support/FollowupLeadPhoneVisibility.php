<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Campaign;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * تنظیم «محدودیت نمایش شماره لید»: وقتی روشن است، هر کاربر (جز مدیر سیستم) فقط شمارهٔ
 * ردیف‌هایی از پیگیری را می‌بیند که مشاورشان خود اوست. ماسک روی سرور انجام می‌شود تا شمارهٔ
 * کامل اصلاً به مرورگر کاربر دیگر نرسد؛ هنگام ذخیره هم شمارهٔ ماسک‌شده از دیتابیس بازیابی می‌شود.
 */
class FollowupLeadPhoneVisibility
{
    public const SETTING_KEY = 'followup_consultant_phone_restricted';
    public const UNRESTRICTED_ROLES = ['مدیر سیستم', 'مدیر کل', 'super admin', 'super-admin'];

    public static function enabled(): bool
    {
        return AppSetting::getByKey(self::SETTING_KEY, '0') === '1';
    }

    /** null = همهٔ شماره‌ها دیده می‌شوند؛ آرایه = فقط ردیف‌هایی که مشاورشان یکی از این نام‌هاست. */
    public static function visibleConsultants(?Request $request): ?array
    {
        $user = $request?->user();
        if (! PatientPhoneVisibility::canView($request)) return [];
        if (! self::enabled() || ($user instanceof User && $user->hasAnyRole(self::UNRESTRICTED_ROLES))) return null;
        if (! $user instanceof User) return [];

        return collect([$user->name, $user->nickname ?? null])
            ->merge(Staff::query()->where('user_id', $user->id)->pluck('name'))
            ->map(fn ($name) => self::normalizeName($name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function maskRows(array $rows, ?array $visibleConsultants): array
    {
        if ($visibleConsultants === null) return $rows;

        return array_map(function ($row) use ($visibleConsultants) {
            if (! is_array($row) || trim((string) ($row['phone'] ?? '')) === '') return $row;
            if (in_array(self::normalizeName($row['consultant'] ?? ''), $visibleConsultants, true)) return $row;
            $row['phone'] = PatientPhoneVisibility::mask((string) $row['phone']);

            return $row;
        }, $rows);
    }

    public static function maskCampaign(Campaign $campaign, ?array $visibleConsultants): Campaign
    {
        if ($visibleConsultants === null) return $campaign;
        $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
        if (is_array($payload['rows'] ?? null)) {
            $payload['rows'] = self::maskRows($payload['rows'], $visibleConsultants);
            $campaign->setAttribute('ui_payload', $payload);
        }

        return $campaign;
    }

    /** شمارهٔ ماسک‌شده‌ای که از مرورگر برمی‌گردد هرگز جای شمارهٔ واقعی ذخیره نمی‌شود. */
    public static function restoreMaskedPhones(array $rows, array $serverRows): array
    {
        $serverPhones = [];
        foreach ($serverRows as $row) {
            if (is_array($row) && isset($row['_localId'])) $serverPhones[(string) $row['_localId']] = $row['phone'] ?? '';
        }

        return array_map(function ($row) use ($serverPhones) {
            if (! is_array($row) || ! PatientPhoneVisibility::looksMasked($row['phone'] ?? '')) return $row;
            $row['phone'] = $serverPhones[(string) ($row['_localId'] ?? '')] ?? '';

            return $row;
        }, $rows);
    }

    public static function normalizeName(mixed $name): string
    {
        $name = strtr(trim((string) $name), ['ي' => 'ی', 'ك' => 'ک', "\u{200c}" => ' ']);

        return preg_replace('/\s+/u', ' ', $name) ?? $name;
    }
}
