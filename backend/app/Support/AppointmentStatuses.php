<?php

namespace App\Support;

/**
 * Canonical appointment status labels (Persian) and stable machine keys.
 * DB / API store the Persian label; keys are for code and docs.
 */
final class AppointmentStatuses
{
    public const WAITING_SLOT_KEY = 'waiting_slot';

    public const WAITING_SLOT = 'در انتظار وقت';

    public const SCHEDULED = 'وقت داده شد';

    public const ARRIVED = 'آمد';

    public const CANCELLED = 'کنسل شد';

    public const NO_ANSWER = 'پاسخ نداد';

    public const FOLLOWUP = 'پیگیری';

    public const TRANSFERRED = 'انتقال داده شده';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::WAITING_SLOT,
            self::SCHEDULED,
            self::ARRIVED,
            self::CANCELLED,
            self::NO_ANSWER,
            self::FOLLOWUP,
            self::TRANSFERRED,
        ];
    }

    public static function normalize(?string $status): string
    {
        $raw = trim((string) $status);
        if ($raw === '' || $raw === '—') {
            return '';
        }

        $aliases = [
            'waiting_slot' => self::WAITING_SLOT,
            'درانتظار وقت' => self::WAITING_SLOT,
            'انتقال روز' => self::TRANSFERRED,
            'انتقال وقت' => self::TRANSFERRED,
            'انتقال یافت' => self::TRANSFERRED,
        ];

        return $aliases[$raw] ?? $raw;
    }

    public static function isWaitingSlot(?string $status): bool
    {
        return self::normalize($status) === self::WAITING_SLOT;
    }

    /** Confirmed booking (not waiting for a slot). */
    public static function isConfirmedScheduled(?string $status): bool
    {
        return self::normalize($status) === self::SCHEDULED;
    }

    /** Counts toward scheduled/forecast “وقت” KPIs (excludes waiting_slot). */
    public static function isScheduledForReports(?string $status): bool
    {
        $value = self::normalize($status);

        return $value === self::SCHEDULED
            || ($value !== self::WAITING_SLOT && str_contains($value, 'وقت داده'));
    }
}
