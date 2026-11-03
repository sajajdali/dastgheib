<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** جدول‌های منابع، انبار و ریز کارکرد که محاسبهٔ پورسانت و حقوق به آن‌ها نیاز دارد. */
trait CreatesPayrollTables
{
    protected function createPayrollTables(): void
    {
        Schema::create('doctors', fn (Blueprint $table) => $this->payrollResourceTable($table));
        Schema::create('staff', fn (Blueprint $table) => $this->payrollResourceTable($table));
        Schema::create('inventories', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('price', 15, 2)->default(0); $table->string('default_commission_type')->nullable();
            $table->decimal('default_commission_value', 15, 2)->default(0); $table->timestamps();
        });
        Schema::create('inventory_commissions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('inventory_id'); $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id')->nullable(); $table->string('recipient_name')->nullable();
            $table->string('commission_type'); $table->decimal('commission_value', 15, 2); $table->timestamps();
        });
        Schema::create('resource_earning_lines', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('appointment_id')->nullable(); $table->string('month', 7);
            $table->unsignedInteger('day_num')->nullable(); $table->dateTime('earned_at')->nullable();
            $table->string('resource_type'); $table->unsignedBigInteger('resource_id')->nullable(); $table->string('resource_name');
            $table->string('earning_type'); $table->unsignedBigInteger('inventory_id')->nullable(); $table->string('inventory_name')->nullable();
            $table->string('service_name')->nullable(); $table->unsignedInteger('service_line_index')->nullable(); $table->boolean('is_addon')->default(false);
            $table->decimal('quantity', 12, 3)->default(1); $table->decimal('gross_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0); $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('material_cost', 15, 2)->default(0); $table->decimal('commission_base', 15, 2)->default(0);
            $table->string('commission_type')->nullable(); $table->decimal('commission_value', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0); $table->string('status')->default('active');
            $table->boolean('manually_edited')->default(false); $table->unsignedBigInteger('edited_by_user_id')->nullable();
            $table->string('edited_by_name')->nullable(); $table->dateTime('edited_at')->nullable();
            $table->unsignedBigInteger('deleted_by_user_id')->nullable(); $table->string('deleted_by_name')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->boolean('commission_after_materials')->default(false); $table->string('commission_customer_scope')->nullable();
            $table->boolean('appointment_new_customer')->default(false); $table->json('calculation_snapshot')->nullable();
            $table->json('audit_events')->nullable(); $table->string('description')->nullable(); $table->timestamps();
        });
        Schema::create('attendance_months', function (Blueprint $table) {
            $table->id(); $table->string('resource_type'); $table->unsignedBigInteger('resource_id'); $table->string('name')->nullable();
            $table->unsignedSmallInteger('year'); $table->unsignedTinyInteger('month');
            $table->decimal('daily_hours', 5, 2)->default(0); $table->json('days')->nullable(); $table->timestamps();
        });
    }

    private function payrollResourceTable(Blueprint $table): void
    {
        $table->id(); $table->unsignedBigInteger('user_id')->nullable(); $table->string('name'); $table->decimal('bonus', 8, 2)->default(0);
        $table->string('commission_customer_scope')->default('both');
        $table->decimal('new_customer_bonus', 5, 2)->nullable(); $table->decimal('existing_customer_bonus', 5, 2)->nullable();
        $table->boolean('commission_after_materials')->default(false);
        $table->boolean('sales_bonus_enabled')->default(false); $table->json('sales_bonus_tiers')->nullable();
        $table->decimal('salary', 15, 2)->default(0); $table->unsignedBigInteger('overtime_hourly_rate')->default(0);
        $table->unsignedBigInteger('shortage_hourly_deduction')->default(0); $table->unsignedBigInteger('absence_deduction')->default(0);
        $table->decimal('allowed_shortage_hours', 6, 2)->default(0); $table->timestamps();
    }
}
