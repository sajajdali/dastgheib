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
            $after = Schema::hasColumn('inventory_sections', 'photos_required')
                ? 'photos_required'
                : (Schema::hasColumn('inventory_sections', 'color') ? 'color' : 'name');

            if (! Schema::hasColumn('inventory_sections', 'commission_type')) {
                $table->string('commission_type', 20)->nullable()->after($after);
            }
            if (! Schema::hasColumn('inventory_sections', 'commission_value')) {
                $table->decimal('commission_value', 15, 2)->nullable()->after('commission_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_sections')) {
            return;
        }

        Schema::table('inventory_sections', function (Blueprint $table) {
            foreach (['commission_value', 'commission_type'] as $column) {
                if (Schema::hasColumn('inventory_sections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
