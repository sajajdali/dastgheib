<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_sections')) {
            Schema::create('inventory_sections', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('inventories', function (Blueprint $table) {
            if (! Schema::hasColumn('inventories', 'section_id')) {
                $table->foreignId('section_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('inventory_sections')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('inventories', 'min_stock')) {
                $table->integer('min_stock')->nullable()->default(5)->after('stock');
            }

            if (! Schema::hasColumn('inventories', 'active')) {
                $table->boolean('active')->default(true)->after('min_stock');
            }

            if (! Schema::hasColumn('inventories', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('active');
            }

            if (! Schema::hasColumn('inventories', 'default_commission_type')) {
                $table->string('default_commission_type', 20)->default('percent')->after('sort_order');
            }

            if (! Schema::hasColumn('inventories', 'default_commission_value')) {
                $table->decimal('default_commission_value', 15, 2)->default(0)->after('default_commission_type');
            }
        });

        if (! Schema::hasTable('inventory_commissions')) {
            Schema::create('inventory_commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
                $table->string('recipient_type', 20);
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->string('recipient_name')->nullable();
                $table->string('commission_type', 20)->default('percent');
                $table->decimal('commission_value', 15, 2)->default(0);
                $table->timestamps();
                $table->index(['recipient_type', 'recipient_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_commissions');

        Schema::table('inventories', function (Blueprint $table) {
            foreach ([
                'default_commission_value',
                'default_commission_type',
                'sort_order',
                'active',
                'min_stock',
            ] as $column) {
                if (Schema::hasColumn('inventories', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('inventories', 'section_id')) {
                $table->dropConstrainedForeignId('section_id');
            }
        });

        Schema::dropIfExists('inventory_sections');
    }
};
