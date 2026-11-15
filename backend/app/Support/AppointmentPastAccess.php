<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\User;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * دسترسی به نوبت‌های روزهای گذشته (قبل از امروزِ شمسی تهران):
 * «مشاهده و ویرایش»، «فقط مشاهده» یا «عدم مشاهده». روز جاری و آینده تابع مجوزهای اصلی نوبت‌دهی است.
 */
final class AppointmentPastAccess
{
    public const VIEW = 'appointments.past_view';
    public const UPDATE = 'appointments.past_update';

    public static function today(): string
    {
        return (new JalaliMonthWindow())->date(now('Asia/Tehran'));
    }

    public static function dateKey(?string $month, mixed $day): ?string
    {
        $month = trim((string) $month);
        $day = (int) $day;
        if (! preg_match('/^\d{4}-\d{2}$/', $month) || $day < 1) return null;

        return $month.'-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT);
    }

    public static function isPast(?string $month, mixed $day): bool
    {
        $key = self::dateKey($month, $day);

        return $key !== null && $key < self::today();
    }

    public static function isPastAppointment(?Appointment $appointment): bool
    {
        return $appointment !== null && self::isPast($appointment->month, $appointment->day_num);
    }

    /** فراخوانی داخلی بدون کاربر (کارهای زمان‌بندی‌شده و تست‌ها) محدود نمی‌شود. */
    public static function canView(?Authenticatable $user): bool
    {
        return ! $user instanceof User || $user->can(self::VIEW);
    }

    public static function canEdit(?Authenticatable $user): bool
    {
        return ! $user instanceof User || ($user->can(self::VIEW) && $user->can(self::UPDATE));
    }

    public static function assertCanEdit(?Authenticatable $user, ?string $month, mixed $day): void
    {
        if (self::isPast($month, $day) && ! self::canEdit($user)) {
            abort(403, self::canView($user)
                ? 'نقش شما اجازهٔ ویرایش نوبت‌های روزهای گذشته را ندارد.'
                : 'نقش شما اجازهٔ دسترسی به نوبت‌های روزهای گذشته را ندارد.');
        }
    }

    public static function assertCanEditAppointment(?Authenticatable $user, ?Appointment $appointment): void
    {
        if ($appointment) self::assertCanEdit($user, $appointment->month, $appointment->day_num);
    }

    public static function assertCanViewAppointment(?Authenticatable $user, ?Appointment $appointment): void
    {
        if (self::isPastAppointment($appointment) && ! self::canView($user)) {
            abort(403, 'نقش شما اجازهٔ دسترسی به نوبت‌های روزهای گذشته را ندارد.');
        }
    }

    /** فقط نوبت‌های امروز و آینده؛ برای نقشی که روزهای گذشته را نمی‌بیند. */
    public static function constrainToCurrentAndFuture(Builder $query): Builder
    {
        [$year, $month, $day] = explode('-', self::today());
        $currentMonth = "{$year}-{$month}";

        return $query->where(fn (Builder $inner) => $inner
            ->where('month', '>', $currentMonth)
            ->orWhere(fn (Builder $same) => $same->where('month', $currentMonth)->where('day_num', '>=', (int) $day)));
    }
}
