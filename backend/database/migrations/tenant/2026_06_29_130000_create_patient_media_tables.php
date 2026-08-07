<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('patient_media_folders')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['patient_id', 'parent_id']);
        });

        Schema::create('patient_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('patient_media_folders')->nullOnDelete();
            $table->string('file_name');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('media_type', 20)->default('image');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->string('gender', 20)->nullable();
            $table->string('age_group', 20)->nullable();
            $table->text('description')->nullable();
            $table->json('services')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'folder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_media');
        Schema::dropIfExists('patient_media_folders');
    }
};
