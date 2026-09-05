<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('appointments', 'wait_minutes')) {
            Schema::table('appointments', fn (Blueprint $table) => $table->unsignedInteger('wait_minutes')->nullable()->after('completed_at'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('appointments', 'wait_minutes')) {
            Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn('wait_minutes'));
        }
    }
};
