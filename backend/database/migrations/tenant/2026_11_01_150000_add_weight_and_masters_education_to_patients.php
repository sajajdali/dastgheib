<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('patients', 'weight')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->decimal('weight', 5, 1)->nullable()->after('education');
            });
        }

        // مراکزی که فهرست تحصیلات را ذخیره کرده‌اند گزینهٔ «فوق لیسانس» را بعد از «لیسانس» دریافت می‌کنند.
        $setting = DB::table('app_settings')->where('key', 'education_options')->first();
        $options = $setting ? json_decode((string) $setting->value, true) : null;
        if (is_array($options) && $options !== [] && ! in_array('فوق لیسانس', $options, true)) {
            $index = array_search('لیسانس', $options, true);
            array_splice($options, $index === false ? count($options) : $index + 1, 0, ['فوق لیسانس']);
            DB::table('app_settings')->where('key', 'education_options')->update([
                'value' => json_encode(array_values($options), JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('patients', 'weight')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropColumn('weight');
            });
        }
    }
};
