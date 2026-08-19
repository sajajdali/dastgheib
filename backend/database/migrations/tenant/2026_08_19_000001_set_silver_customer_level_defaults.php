<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $setting = AppSetting::query()->where('key', 'customer_level_settings')->first();
        if (! $setting) {
            return;
        }

        $levels = json_decode((string) $setting->value, true);
        if (! is_array($levels)) {
            return;
        }

        $levels['silver_min_period_amount'] = 0;
        $levels['silver_max_period_amount'] = 0;
        $levels['silver_visit_count'] = 0;
        $setting->update(['value' => json_encode($levels, JSON_UNESCAPED_UNICODE)]);
    }

    public function down(): void
    {
        // مقدارهای پیشین tenant قابل بازیابی قطعی نیستند.
    }
};
