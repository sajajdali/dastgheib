<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('jalali_date', 10)->unique();
            $table->text('title')->nullable();
            $table->boolean('is_official_holiday')->default(false);
            $table->string('source', 40)->default('pnldev');
            $table->timestamp('source_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('calendar_syncs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('jalali_year');
            $table->unsignedTinyInteger('jalali_month');
            $table->timestamp('synced_at')->nullable();
            $table->string('status', 20)->default('completed');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['jalali_year', 'jalali_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_syncs');
        Schema::dropIfExists('calendar_events');
    }
};
