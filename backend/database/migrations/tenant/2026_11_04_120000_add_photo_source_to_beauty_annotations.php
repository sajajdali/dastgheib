<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('beauty_annotations') || Schema::hasColumn('beauty_annotations', 'photo_source')) {
            return;
        }

        // «media» = نقطه روی یکی از عکس‌های پرونده (رفتار قبلی)
        // «profile» = نقطه روی عکس پروفایل بیمار (patient_media_id خالی است)
        Schema::table('beauty_annotations', function (Blueprint $table) {
            $table->string('photo_source', 20)->default('media')->after('patient_media_id');
            $table->index(['patient_id', 'photo_source', 'status'], 'beauty_annotations_source_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('beauty_annotations') || ! Schema::hasColumn('beauty_annotations', 'photo_source')) {
            return;
        }

        Schema::table('beauty_annotations', function (Blueprint $table) {
            $table->dropIndex('beauty_annotations_source_index');
            $table->dropColumn('photo_source');
        });
    }
};
