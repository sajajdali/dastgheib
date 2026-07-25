<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('services', function (Blueprint $table) {
        $table->id();
        $table->string('file_number');
        $table->date('date')->nullable();
        $table->string('service');
        $table->string('status');
        $table->string('doctor')->nullable();
        $table->string('referral_code')->nullable();
        $table->integer('club_score')->nullable();
        $table->integer('amount')->nullable();
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
