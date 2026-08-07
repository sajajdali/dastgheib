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
        Schema::create('contacts', function (Blueprint $table) {

            $table->id();

            $table->string('full_name')->nullable();

            $table->string('phone', 30)->nullable()->index();

            $table->string('date', 20)->nullable();

            $table->string('follow_up_date', 20)->nullable()->index();

            $table->string('gender', 10)->nullable();

            $table->string('consultant')->nullable();

            $table->string('source')->nullable();

            $table->string('status')->nullable()->index();

            $table->string('interest')->nullable();

            $table->text('description')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
