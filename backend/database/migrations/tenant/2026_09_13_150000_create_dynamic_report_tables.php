<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('stage')->nullable();
            $table->json('columns');
            $table->json('filters');
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dynamic_report_rows', function (Blueprint $table) {
            $table->id();
            $table->uuid('report_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->json('payload');
            $table->timestamp('created_at')->nullable();
            $table->unique(['report_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_report_rows');
        Schema::dropIfExists('dynamic_reports');
    }
};
