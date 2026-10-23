<?php

namespace Tests\Feature;

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ChannelController;
use App\Models\Appointment;
use App\Models\Channel;
use App\Models\Doctor;
use App\Models\Inventory;
use App\Models\ResourceEarningLine;
use App\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Tests\TestCase;

class ChannelDoctorCommissionRuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('channels', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('icon')->nullable();
            $table->string('icon_image_path')->nullable(); $table->boolean('exclude_doctor_commission')->default(false); $table->timestamps();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id(); $table->string('month')->nullable(); $table->unsignedInteger('day_num')->nullable();
            $table->string('source')->nullable(); $table->string('doctor')->nullable(); $table->string('consultant')->nullable();
            $table->boolean('new_customer')->default(false); $table->decimal('amount', 15, 2)->default(0);
            $table->json('services')->nullable(); $table->timestamps();
        });
        Schema::create('doctors', fn (Blueprint $table) => $this->resourceTable($table));
        Schema::create('staff', fn (Blueprint $table) => $this->resourceTable($table));
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
            $table->boolean('commission_after_materials')->default(false); $table->string('commission_customer_scope')->nullable();
            $table->boolean('appointment_new_customer')->default(false); $table->json('calculation_snapshot')->nullable();
            $table->json('audit_events')->nullable(); $table->string('description')->nullable(); $table->timestamps();
        });
    }

    private function resourceTable(Blueprint $table): void
    {
        $table->id(); $table->string('name'); $table->decimal('bonus', 8, 2)->default(0);
        $table->string('commission_customer_scope')->default('both'); $table->boolean('commission_after_materials')->default(false);
        $table->boolean('sales_bonus_enabled')->default(false); $table->json('sales_bonus_tiers')->nullable();
        $table->decimal('salary', 15, 2)->default(0); $table->timestamps();
    }

    public function test_blocked_channel_removes_only_doctor_commission_and_rebuilds_cleanly(): void
    {
        $channel = Channel::query()->create(['name' => 'تبلیغات ویژه', 'exclude_doctor_commission' => false]);
        $doctor = Doctor::query()->create([
            'name' => 'پزشک تست', 'bonus' => 10, 'sales_bonus_enabled' => true,
            'sales_bonus_tiers' => [['sales_from' => 1, 'salary_addition' => 500]],
        ]);
        $staff = Staff::query()->create(['name' => 'مشاور تست', 'bonus' => 5]);
        Inventory::query()->create(['name' => 'خدمت تست', 'amount' => 1000, 'price' => 100]);
        $appointment = Appointment::query()->create([
            'month' => '1405-08', 'day_num' => 1, 'source' => ' تبلیغات ویژه ',
            'doctor' => $doctor->name, 'consultant' => $staff->name,
            'amount' => 950,
            'services' => [[
                'name' => 'خدمت تست', 'cc' => 1, 'doctor' => $doctor->name, 'consultant' => $staff->name,
            ]],
        ]);

        $controller = app(AppointmentController::class);
        $controller->resyncResourceEarningsForSources(['تبلیغات ویژه']);
        $this->assertDatabaseHas('resource_earning_lines', [
            'appointment_id' => $appointment->id, 'resource_type' => 'doctor', 'earning_type' => 'base_commission', 'amount' => 100,
        ]);

        app(ChannelController::class)->store(Request::create('/api/channels', 'POST', [[
            'id' => $channel->id,
            'name' => $channel->name,
            'icon' => null,
            'exclude_doctor_commission' => true,
        ]]));

        $this->assertDatabaseMissing('resource_earning_lines', ['appointment_id' => $appointment->id, 'resource_type' => 'doctor']);
        $this->assertSame(0, ResourceEarningLine::query()->where('resource_type', 'doctor')->count());
        $this->assertDatabaseHas('resource_earning_lines', [
            'appointment_id' => $appointment->id, 'resource_type' => 'staff', 'earning_type' => 'base_commission', 'amount' => 50,
        ]);
        $this->assertSame(950.0, (float) $appointment->fresh()->amount);
        $this->assertSame('خدمت تست', $appointment->fresh()->services[0]['name']);

        app(ChannelController::class)->store(Request::create('/api/channels', 'POST', [[
            'id' => $channel->id,
            'name' => $channel->name,
            'icon' => null,
            'exclude_doctor_commission' => false,
        ]]));
        $controller->resyncResourceEarningsForSources(['تبلیغات ویژه']);

        $this->assertDatabaseHas('resource_earning_lines', [
            'appointment_id' => $appointment->id, 'resource_type' => 'doctor', 'earning_type' => 'base_commission', 'amount' => 100,
        ]);
        $this->assertSame(1, ResourceEarningLine::query()->where('resource_type', 'doctor')->where('earning_type', 'sales_bonus')->count());
        $this->assertSame(1, ResourceEarningLine::query()->where('appointment_id', $appointment->id)->where('resource_type', 'staff')->count());
    }
}
