<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->boolean('track_stock')->default(false);
        });
        Schema::table('inventory_addon_definitions', function (Blueprint $table) {
            $table->boolean('track_stock')->default(false);
        });
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_addon_definition_id')->nullable()->index();
        });

        // Preserve tracking for existing stock, including exhausted items with a movement history.
        DB::table('inventories')->whereNotNull('stock')->where('stock', '!=', '0')->update(['track_stock' => true]);
        DB::table('inventories')->whereIn('id', DB::table('inventory_movements')->whereNotNull('inventory_id')->select('inventory_id'))
            ->update(['track_stock' => true]);
        DB::table('inventory_addon_definitions')->whereNotNull('stock')->where('stock', '!=', 0)
            ->update(['track_stock' => true]);
    }

    public function down(): void
    {
        Schema::table('inventory_movements', fn (Blueprint $table) => $table->dropColumn('inventory_addon_definition_id'));
        Schema::table('inventory_addon_definitions', fn (Blueprint $table) => $table->dropColumn('track_stock'));
        Schema::table('inventories', fn (Blueprint $table) => $table->dropColumn('track_stock'));
    }
};
