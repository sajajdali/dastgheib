<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_media_folders', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_media_folders', 'folder_type')) {
                $table->string('folder_type', 30)->nullable()->after('name');
            }

            if (! Schema::hasColumn('patient_media_folders', 'folder_date')) {
                $table->string('folder_date', 20)->nullable()->after('folder_type');
            }

            if (! Schema::hasColumn('patient_media_folders', 'inventory_id')) {
                $table->foreignId('inventory_id')
                    ->nullable()
                    ->after('folder_date')
                    ->constrained('inventories')
                    ->nullOnDelete();
            }

            $table->index(['patient_id', 'parent_id', 'folder_type'], 'pmf_patient_parent_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('patient_media_folders', function (Blueprint $table) {
            $table->dropIndex('pmf_patient_parent_type_index');

            if (Schema::hasColumn('patient_media_folders', 'inventory_id')) {
                $table->dropConstrainedForeignId('inventory_id');
            }

            foreach (['folder_date', 'folder_type'] as $column) {
                if (Schema::hasColumn('patient_media_folders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
