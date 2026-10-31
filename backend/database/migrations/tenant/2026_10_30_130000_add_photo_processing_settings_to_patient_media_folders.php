<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_media_folders', function (Blueprint $table) {
            $table->json('photo_processing_settings')->nullable()->after('upload_is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('patient_media_folders', function (Blueprint $table) {
            $table->dropColumn('photo_processing_settings');
        });
    }
};
