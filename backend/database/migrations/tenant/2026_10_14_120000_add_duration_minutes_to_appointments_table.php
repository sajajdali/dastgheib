<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('appointments', 'duration_minutes')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->unsignedSmallInteger('duration_minutes')->nullable()->after('time');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('appointments', 'duration_minutes')) {
            Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn('duration_minutes'));
        }
    }
};
