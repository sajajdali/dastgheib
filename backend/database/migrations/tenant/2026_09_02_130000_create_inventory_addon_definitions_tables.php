<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_addon_definitions')) {
            Schema::create('inventory_addon_definitions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('amount')->nullable();
                $table->string('price')->nullable();
                $table->integer('stock')->nullable();
                $table->integer('min_stock')->default(5);
                $table->boolean('active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
        Schema::dropIfExists('inventory_addon_assignments');
        Schema::create('inventory_addon_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->unsignedBigInteger('inventory_addon_definition_id');
            $table->timestamps();
            $table->foreign('inventory_id', 'iaa_inventory_fk')->references('id')->on('inventories')->cascadeOnDelete();
            $table->foreign('inventory_addon_definition_id', 'iaa_definition_fk')->references('id')->on('inventory_addon_definitions')->cascadeOnDelete();
            $table->unique(['inventory_id', 'inventory_addon_definition_id'], 'iaa_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_addon_assignments');
        Schema::dropIfExists('inventory_addon_definitions');
    }
};
