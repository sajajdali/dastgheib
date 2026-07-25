<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['doctors', 'staff'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('absence_deduction')->default(0)->after('shortage_hourly_deduction');
            });
        }
    }

    public function down(): void
    {
        foreach (['doctors', 'staff'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('absence_deduction');
            });
        }
    }
};
