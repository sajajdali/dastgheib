<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_widget_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('report_key', 80)->index();
            $table->string('filter_hash', 64);
            $table->json('filters');
            $table->json('result')->nullable();
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('stage')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('requested_by')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['report_key', 'filter_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_widget_snapshots');
    }
};
