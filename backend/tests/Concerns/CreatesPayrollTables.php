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
        Schema::create('inventory_sections', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('parent_id')->nullable(); $table->unsignedInteger('level')->default(1);
            $table->string('name'); $table->string('color')->nullable(); $table->boolean('photos_required')->default(true);
            $table->string('commission_type', 20)->nullable(); $table->decimal('commission_value', 15, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0); $table->timestamps();
        });
        Schema::create('inventories', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('section_id')->nullable(); $table->string('name');
            $table->decimal('amount', 15, 2)->default(0); $table->decimal('price', 15, 2)->default(0);
            $table->string('default_commission_type')->nullable();
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
        Schema::create('resource_adjustments', function (Blueprint $table) {
            $table->id(); $table->string('month', 7); $table->string('resource_type'); $table->unsignedBigInteger('resource_id');
            $table->decimal('amount', 15, 2); $table->string('reason')->nullable(); $table->unsignedBigInteger('created_by')->nullable();
            $table->string('kind')->default('adjustment'); $table->string('occurred_on', 10)->nullable(); $table->string('title')->nullable();
            $table->text('description')->nullable(); $table->string('status')->default('active'); $table->string('created_by_name')->nullable(); $table->timestamps();
        });
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id(); $table->string('resource_type'); $table->unsignedBigInteger('resource_id'); $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('requested_amount', 15, 2); $table->decimal('approved_amount', 15, 2)->nullable(); $table->text('reason')->nullable();
            $table->text('manager_note')->nullable(); $table->string('status')->default('pending'); $table->boolean('deduct_from_salary')->default(true);
            $table->unsignedSmallInteger('installment_count')->default(1); $table->string('deduction_start_month', 7)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable(); $table->dateTime('reviewed_at')->nullable(); $table->dateTime('paid_at')->nullable();
            $table->dateTime('cancelled_at')->nullable(); $table->timestamps();
        });
        Schema::create('salary_advance_installments', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('salary_advance_id'); $table->string('month', 7); $table->decimal('amount', 15, 2);
            $table->string('status')->default('scheduled'); $table->dateTime('deducted_at')->nullable(); $table->timestamps();
        });
        Schema::create('employee_leave_requests', function (Blueprint $table) {
            $table->id(); $table->string('resource_type'); $table->unsignedBigInteger('resource_id'); $table->unsignedBigInteger('user_id')->nullable();
            $table->string('leave_type'); $table->string('start_date', 10); $table->string('end_date', 10); $table->time('start_time')->nullable();
            $table->time('end_time')->nullable(); $table->text('reason')->nullable(); $table->string('status')->default('pending');
            $table->text('manager_note')->nullable(); $table->unsignedBigInteger('reviewed_by')->nullable(); $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable(); $table->timestamps();
        });
    }

    private function payrollResourceTable(Blueprint $table): void
    {
        $table->id(); $table->unsignedBigInteger('user_id')->nullable(); $table->string('name'); $table->decimal('bonus', 8, 2)->default(0);
        $table->string('commission_customer_scope')->default('both');
        $table->decimal('new_customer_bonus', 5, 2)->nullable(); $table->decimal('existing_customer_bonus', 5, 2)->nullable();
        $table->boolean('commission_after_materials')->default(false);
        $table->boolean('sales_bonus_enabled')->default(false); $table->json('sales_bonus_tiers')->nullable();
        $table->string('referral_commission_type')->nullable(); $table->decimal('referral_commission_value', 15, 2)->nullable();
        $table->decimal('salary', 15, 2)->default(0); $table->unsignedBigInteger('overtime_hourly_rate')->default(0);
        $table->unsignedBigInteger('shortage_hourly_deduction')->default(0); $table->unsignedBigInteger('absence_deduction')->default(0);
        $table->decimal('allowed_shortage_hours', 6, 2)->default(0); $table->timestamps();
    }
}
