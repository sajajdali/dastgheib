<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_calendar_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('jalali_date', 10)->unique();
            $table->boolean('is_closed');
            $table->string('title', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_calendar_overrides');
    }
};
