<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_media', function (Blueprint $table) {
            $table->string('comparison_stage', 20)->nullable()->after('services');
            $table->string('photo_angle_key', 60)->nullable()->after('comparison_stage');
            $table->string('photo_angle_label', 120)->nullable()->after('photo_angle_key');
            $table->unsignedSmallInteger('photo_angle_degrees')->nullable()->after('photo_angle_label');

            $table->index(['patient_id', 'comparison_stage', 'photo_angle_key'], 'patient_media_comparison_angle_index');
        });
    }

    public function down(): void
    {
        Schema::table('patient_media', function (Blueprint $table) {
            $table->dropIndex('patient_media_comparison_angle_index');
            $table->dropColumn([
                'comparison_stage',
                'photo_angle_key',
                'photo_angle_label',
                'photo_angle_degrees',
            ]);
        });
    }
};
