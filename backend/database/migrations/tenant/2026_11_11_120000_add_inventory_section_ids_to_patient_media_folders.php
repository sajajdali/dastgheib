<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patient_media_folders') || Schema::hasColumn('patient_media_folders', 'inventory_section_ids')) {
            return;
        }

        // یک پوشهٔ عکس می‌تواند چند خدمت داشته باشد (مثلاً ژل و بوتاکس در یک جلسه).
        Schema::table('patient_media_folders', function (Blueprint $table) {
            $table->json('inventory_section_ids')->nullable()->after('inventory_section_id');
        });

        DB::table('patient_media_folders')->whereNotNull('inventory_section_id')->orderBy('id')
            ->each(fn ($folder) => DB::table('patient_media_folders')->where('id', $folder->id)
                ->update(['inventory_section_ids' => json_encode([(int) $folder->inventory_section_id])]));
    }

    public function down(): void
    {
        if (Schema::hasColumn('patient_media_folders', 'inventory_section_ids')) {
            Schema::table('patient_media_folders', fn (Blueprint $table) => $table->dropColumn('inventory_section_ids'));
        }
    }
};
