<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_months', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 20);
            $table->unsignedBigInteger('resource_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('name', 50);
            $table->decimal('daily_hours', 5, 2)->default(8);
            $table->json('days');
            $table->timestamps();

            $table->unique(
                ['resource_type', 'resource_id', 'year', 'month'],
                'attendance_resource_year_month_unique'
            );
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_months');
    }
};
