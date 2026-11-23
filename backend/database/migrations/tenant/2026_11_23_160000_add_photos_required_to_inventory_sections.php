<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_sections')) {
            return;
        }

        Schema::table('inventory_sections', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_sections', 'photos_required')) {
                $table->boolean('photos_required')->default(true)->after('color');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_sections')) {
            return;
        }

        Schema::table('inventory_sections', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_sections', 'photos_required')) {
                $table->dropColumn('photos_required');
            }
        });
    }
};
