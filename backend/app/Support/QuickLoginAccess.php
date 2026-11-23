<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * ورود سریع با «مدیر کل» فقط برای IPهای مجاز (config/quick_login.php) و اجرای محلی.
 *
 * IP همان آدرس واقعی اتصال است (پروکسی‌ای مورد اعتماد نیست، پس هدر X-Forwarded-For اثری ندارد).
 * آدرس loopback به‌تنهایی کافی نیست: روی سرور پشت پروکسی معکوس همهٔ درخواست‌ها از 127.0.0.1 می‌آیند؛
 * برای همین «محلی» فقط وقتی پذیرفته می‌شود که خود سایت هم با localhost یا *.localhost باز شده باشد.
 */
final class QuickLoginAccess
{
    private const LOOPBACK = ['127.0.0.1', '::1'];

    public static function allowed(Request $request): bool
    {
        $ip = (string) $request->ip();
        if (in_array($ip, config('quick_login.allowed_ips', []), true)) {
            return true;
        }

        $host = strtolower((string) $request->getHost());
        $localHost = in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.localhost');

        return $localHost && in_array($ip, self::LOOPBACK, true);
    }

    /** نخستین کاربر فعال با نقش «مدیر سیستم» (مدیر کل کلینیک). */
    public static function adminUser(): ?User
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'مدیر سیستم'))
            ->where(fn ($query) => $query->whereNull('access_blocked')->orWhere('access_blocked', false))
            ->orderBy('id')
            ->first();
    }
}
