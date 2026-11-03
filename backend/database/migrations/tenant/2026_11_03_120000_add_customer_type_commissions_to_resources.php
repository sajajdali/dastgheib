<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * پورسانت جداگانه برای مشتری جدید و قدیمی.
 * مقدار null یعنی برای آن نوع مشتری پورسانتی تعریف نشده است.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['doctors', 'staff'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->decimal('new_customer_bonus', 5, 2)->nullable()->after('commission_customer_scope');
                $blueprint->decimal('existing_customer_bonus', 5, 2)->nullable()->after('new_customer_bonus');
            });

            // تنظیمات قبلی (یک درصد پایه + «پورسانت برای») بدون تغییر در نتیجه منتقل می‌شود.
            DB::table($table)->whereIn('commission_customer_scope', ['new', 'both'])
                ->update(['new_customer_bonus' => DB::raw('COALESCE(bonus, 0)')]);
            DB::table($table)->whereIn('commission_customer_scope', ['existing', 'both'])
                ->update(['existing_customer_bonus' => DB::raw('COALESCE(bonus, 0)')]);
        }
    }

    public function down(): void
    {
        foreach (['doctors', 'staff'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn(['new_customer_bonus', 'existing_customer_bonus']));
        }
    }
};
