<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use Auditable;

    public function activitySection(): string { return 'تنظیمات'; }

    protected $fillable = ['key', 'value'];

    // فعال کردن کستینگ بومی لاراول
    // protected $casts = [
    //     'value' => 'json'
    // ];

    // اصلاح متد برای هماهنگی با کستینگ لاراول
    /**
     * نام مجموعه برای پیامک‌ها و فرم‌ها. صفحهٔ تنظیمات آن را با کلید company_name ذخیره می‌کند؛
     * clinic_name فقط برای داده‌های قدیمی خوانده می‌شود.
     */
    public static function clinicName(): string
    {
        foreach (['company_name', 'clinic_name'] as $key) {
            $name = trim((string) self::getByKey($key, ''));
            if ($name !== '') return $name;
        }

        return '';
    }

    public static function getByKey($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        if (!$setting) return $default;

        // چون کستینگ فعال است، لاراول خودش در این مرحله value را تبدیل به آبجکت/آرایه کرده است
        return $setting->value;
    }
}
