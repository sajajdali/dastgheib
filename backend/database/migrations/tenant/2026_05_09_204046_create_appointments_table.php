<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('month')->nullable(); 
            $table->integer('day_num')->nullable(); 
            $table->string('lastname')->nullable();
            $table->string('gender')->nullable();
            $table->string('phone')->nullable();
            $table->string('file_number')->nullable();
            $table->string('time')->nullable();
            $table->string('status')->nullable();
            $table->string('doctor')->nullable();
            $table->string('consultant')->nullable();
            $table->string('source')->nullable();
            $table->string('description')->nullable();
            $table->string('done')->nullable();
            $table->string('amount')->nullable();
            $table->string('debt')->nullable();
            $table->boolean('new_customer')->default(false);
            $table->string('appointment_sms')->nullable();
            $table->string('info_sms')->nullable();
            $table->json('services')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
