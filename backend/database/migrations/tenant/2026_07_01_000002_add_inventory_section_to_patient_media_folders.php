<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_media_folders', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_media_folders', 'inventory_section_id')) {
                $table->foreignId('inventory_section_id')
                    ->nullable()
                    ->after('inventory_id')
                    ->constrained('inventory_sections')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('patient_media_folders', function (Blueprint $table) {
            if (Schema::hasColumn('patient_media_folders', 'inventory_section_id')) {
                $table->dropConstrainedForeignId('inventory_section_id');
            }
        });
    }
};
