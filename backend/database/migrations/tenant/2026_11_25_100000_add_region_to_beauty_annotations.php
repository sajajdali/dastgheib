<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('beauty_annotations')) {
            return;
        }

        if (! Schema::hasColumn('beauty_annotations', 'region')) {
            Schema::table('beauty_annotations', function (Blueprint $table) {
                // ناحیهٔ آناتومیک نما (صورت / بدن / بیکینی) — جدا از فیلد area که تگ خدمت است
                $table->string('region', 20)->default('face')->after('photo_source');
                $table->index(['patient_id', 'region', 'photo_source', 'status'], 'beauty_annotations_region_source_index');
            });
        }

        // نقاط قبلی همگی روی عکس پروفایل/پروندهٔ صورت فرض می‌شوند
        DB::table('beauty_annotations')
            ->where(function ($query) {
                $query->whereNull('region')->orWhere('region', '');
            })
            ->update(['region' => 'face']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('beauty_annotations') || ! Schema::hasColumn('beauty_annotations', 'region')) {
            return;
        }

        Schema::table('beauty_annotations', function (Blueprint $table) {
            $table->dropIndex('beauty_annotations_region_source_index');
            $table->dropColumn('region');
        });
    }
};
