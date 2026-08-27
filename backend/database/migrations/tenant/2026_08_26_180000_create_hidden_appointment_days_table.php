<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hidden_appointment_days', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7);
            $table->unsignedTinyInteger('day_num');
            $table->timestamps();
            $table->unique(['month', 'day_num']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hidden_appointment_days');
    }
};
