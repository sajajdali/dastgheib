<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_sections', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_sections', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('id')->constrained('inventory_sections')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('inventory_sections', 'level')) {
                $table->unsignedTinyInteger('level')->default(1)->after('parent_id');
            }
        });

        // Existing services and their IDs must survive the hierarchy upgrade.
        // Legacy sections become root sections until explicitly organized.
    }

    public function down(): void
    {
        Schema::table('inventory_sections', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_sections', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }

            if (Schema::hasColumn('inventory_sections', 'level')) {
                $table->dropColumn('level');
            }
        });
    }
};
