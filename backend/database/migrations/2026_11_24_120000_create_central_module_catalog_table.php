<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_module_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('module_key')->unique();
            $table->string('name');
            $table->unsignedBigInteger('price')->default(0);
            $table->json('periods')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_module_catalog');
    }
};
