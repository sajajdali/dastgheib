<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id')->nullable()->index();
            $table->string('inventory_name');
            $table->decimal('quantity', 14, 3);
            $table->string('type', 40);
            $table->string('source_key')->nullable()->unique();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();
            $table->string('description')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
