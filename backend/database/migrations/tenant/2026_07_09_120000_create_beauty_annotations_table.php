<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beauty_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('patient_media_id')->nullable()->constrained('patient_media')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('x_percent', 6, 3);
            $table->decimal('y_percent', 6, 3);
            $table->string('area', 120)->nullable();
            $table->string('problem', 160)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['patient_id', 'patient_media_id', 'status'], 'beauty_annotations_lookup_index');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beauty_annotations');
    }
};
