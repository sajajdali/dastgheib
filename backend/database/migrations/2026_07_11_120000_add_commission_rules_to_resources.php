<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('commission_customer_scope', 20)->default('both')->after('bonus');
            $table->boolean('commission_after_materials')->default(false)->after('commission_customer_scope');
            $table->boolean('sales_bonus_enabled')->default(false)->after('commission_after_materials');
            $table->json('sales_bonus_tiers')->nullable()->after('sales_bonus_enabled');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->string('commission_customer_scope', 20)->default('both')->after('bonus');
            $table->boolean('commission_after_materials')->default(false)->after('commission_customer_scope');
            $table->boolean('sales_bonus_enabled')->default(false)->after('commission_after_materials');
            $table->json('sales_bonus_tiers')->nullable()->after('sales_bonus_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', fn (Blueprint $table) => $table->dropColumn([
            'commission_customer_scope', 'commission_after_materials', 'sales_bonus_enabled', 'sales_bonus_tiers',
        ]));
        Schema::table('staff', fn (Blueprint $table) => $table->dropColumn([
            'commission_customer_scope', 'commission_after_materials', 'sales_bonus_enabled', 'sales_bonus_tiers',
        ]));
    }
};
