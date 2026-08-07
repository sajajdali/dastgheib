<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['doctors', 'staff'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('hourly_rate')->default(0)->after('salary');
                $table->unsignedBigInteger('overtime_hourly_rate')->default(0)->after('hourly_rate');
                $table->unsignedBigInteger('shortage_hourly_deduction')->default(0)->after('overtime_hourly_rate');
                $table->decimal('allowed_shortage_hours', 6, 2)->default(0)->after('shortage_hourly_deduction');
            });
        }
    }

    public function down(): void
    {
        foreach (['doctors', 'staff'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn([
                'hourly_rate', 'overtime_hourly_rate', 'shortage_hourly_deduction', 'allowed_shortage_hours',
            ]));
        }
    }
};
