<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('resource_earning_lines')) {
            return;
        }

        Schema::create('resource_earning_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->cascadeOnDelete();
            $table->string('month', 7)->index();
            $table->unsignedInteger('day_num')->nullable();
            $table->dateTime('earned_at')->nullable();
            $table->string('resource_type', 20)->index();
            $table->unsignedBigInteger('resource_id')->nullable()->index();
            $table->string('resource_name')->index();
            $table->string('earning_type', 40)->index();
            $table->foreignId('inventory_id')->nullable()->constrained('inventories')->nullOnDelete();
            $table->string('inventory_name')->nullable();
            $table->string('service_name')->nullable();
            $table->unsignedInteger('service_line_index')->nullable();
            $table->boolean('is_addon')->default(false);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('material_cost', 15, 2)->default(0);
            $table->decimal('commission_base', 15, 2)->default(0);
            $table->string('commission_type', 20)->nullable();
            $table->decimal('commission_value', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('commission_after_materials')->default(false);
            $table->string('commission_customer_scope', 20)->nullable();
            $table->boolean('appointment_new_customer')->default(false);
            $table->json('calculation_snapshot')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['month', 'resource_type', 'resource_id'], 'rel_month_resource_idx');
            $table->index(['month', 'resource_name'], 'rel_month_name_idx');
            $table->index(['appointment_id', 'resource_type', 'resource_id'], 'rel_appt_resource_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_earning_lines');
    }
};
