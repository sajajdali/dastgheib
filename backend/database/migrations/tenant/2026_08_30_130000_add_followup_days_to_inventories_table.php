<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('inventories', function (Blueprint $table) {
            if (! Schema::hasColumn('inventories', 'followup_days')) $table->unsignedInteger('followup_days')->default(0)->after('active');
        });
    }
    public function down(): void { Schema::table('inventories', fn (Blueprint $table) => $table->dropColumn('followup_days')); }
};
