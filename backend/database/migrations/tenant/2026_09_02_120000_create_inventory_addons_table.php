<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
            $table->foreignId('addon_inventory_id')->constrained('inventories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['inventory_id', 'addon_inventory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_addons');
    }
};
