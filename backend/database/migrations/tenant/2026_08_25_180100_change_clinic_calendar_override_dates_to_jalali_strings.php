<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_calendar_overrides', fn (Blueprint $table) => $table->string('jalali_date', 10)->change());
    }

    public function down(): void
    {
        Schema::table('clinic_calendar_overrides', fn (Blueprint $table) => $table->date('jalali_date')->change());
    }
};
