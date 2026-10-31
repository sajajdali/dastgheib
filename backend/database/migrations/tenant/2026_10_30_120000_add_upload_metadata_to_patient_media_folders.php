<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_media_folders', function (Blueprint $table) {
            $table->json('services')->nullable()->after('inventory_section_id');
            $table->text('upload_description')->nullable()->after('services');
            $table->boolean('upload_usage_consent')->default(true)->after('upload_description');
            $table->boolean('upload_is_featured')->default(false)->after('upload_usage_consent');
        });
    }

    public function down(): void
    {
        Schema::table('patient_media_folders', function (Blueprint $table) {
            $table->dropColumn([
                'services',
                'upload_description',
                'upload_usage_consent',
                'upload_is_featured',
            ]);
        });
    }
};
