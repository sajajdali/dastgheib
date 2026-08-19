<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEVEL_KEYS = [
        'min_period_amount',
        'max_period_amount',
        'visit_count',
        'visit_period_months',
    ];

    public function up(): void
    {
        $this->swapLevels();
    }

    public function down(): void
    {
        $this->swapLevels();
    }

    private function swapLevels(): void
    {
        $setting = AppSetting::query()->where('key', 'customer_level_settings')->first();
        if ($setting) {
            $levels = json_decode((string) $setting->value, true);
            if (is_array($levels)) {
                foreach (self::LEVEL_KEYS as $key) {
                    $blueKey = "blue_{$key}";
                    $silverKey = "silver_{$key}";
                    [$levels[$blueKey], $levels[$silverKey]] = [
                        $levels[$silverKey] ?? null,
                        $levels[$blueKey] ?? null,
                    ];
                }

                $setting->update(['value' => json_encode($levels, JSON_UNESCAPED_UNICODE)]);
            }
        }

        DB::table('patients')
            ->whereIn('customer_level', ['blue', 'silver'])
            ->update([
                'customer_level' => DB::raw("CASE customer_level WHEN 'blue' THEN 'silver' WHEN 'silver' THEN 'blue' END"),
            ]);
    }
};
