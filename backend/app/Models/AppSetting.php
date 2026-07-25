<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    // فعال کردن کستینگ بومی لاراول
    // protected $casts = [
    //     'value' => 'json'
    // ];

    // اصلاح متد برای هماهنگی با کستینگ لاراول
    public static function getByKey($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        if (!$setting) return $default;

        // چون کستینگ فعال است، لاراول خودش در این مرحله value را تبدیل به آبجکت/آرایه کرده است
        return $setting->value;
    }
}