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
use App\Models\AppSetting;
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
            $table->string('icon_image_path')->nullable(); $table->boolean('exclude_doctor_commission')->default(false);
            $table->boolean('exclude_staff_commission')->default(false); $table->boolean('exclude_referral_reward')->default(false); $table->timestamps();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id(); $table->string('month')->nullable(); $table->unsignedInteger('day_num')->nullable();
            $table->string('source')->nullable(); $table->string('doctor')->nullable(); $table->string('consultant')->nullable();
            $table->string('file_number')->nullable(); $table->string('phone')->nullable();
            $table->boolean('doctor_commission_enabled')->default(true); $table->boolean('staff_commission_enabled')->default(true);
            $table->boolean('referral_reward_enabled')->default(true); $table->string('done')->nullable();
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
        Schema::create('patients', function (Blueprint $table) {
            $table->id(); $table->string('first_name'); $table->string('last_name');
            $table->string('phone'); $table->string('file_number'); $table->softDeletes(); $table->timestamps();
        });
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id(); $table->string('key')->unique(); $table->text('value'); $table->timestamps();
        });
    }

    private function resourceTable(Blueprint $table): void
    {
        $table->id(); $table->string('name'); $table->decimal('bonus', 8, 2)->default(0);
        $table->string('commission_customer_scope')->default('both'); $table->boolean('commission_after_materials')->default(false);
        $table->boolean('sales_bonus_enabled')->default(false); $table->json('sales_bonus_tiers')->nullable();
        $table->decimal('salary', 15, 2)->default(0); $table->timestamps();
    }

    public function test_channel_rules_apply_to_new_appointments_without_rewriting_old_earnings(): void
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
            'exclude_staff_commission' => true,
            'exclude_referral_reward' => true,
        ]]));

        $this->assertDatabaseHas('resource_earning_lines', ['appointment_id' => $appointment->id, 'resource_type' => 'doctor', 'amount' => 100]);
        $this->assertDatabaseHas('resource_earning_lines', [
            'appointment_id' => $appointment->id, 'resource_type' => 'staff', 'earning_type' => 'base_commission', 'amount' => 50,
        ]);
        $this->assertSame(950.0, (float) $appointment->fresh()->amount);
        $this->assertSame('خدمت تست', $appointment->fresh()->services[0]['name']);

        $blocked = $appointment->replicate(['doctor_commission_enabled', 'staff_commission_enabled', 'referral_reward_enabled']);
        $blocked->day_num = 2;
        $blocked->save();
        $this->assertFalse($blocked->doctor_commission_enabled);
        $this->assertFalse($blocked->staff_commission_enabled);
        $this->assertFalse($blocked->referral_reward_enabled);
        $blocked->update(['source' => 'معرفی دوستان']);
        $this->assertTrue($blocked->doctor_commission_enabled);
        $blocked->update(['source' => 'تبلیغات ویژه']);
        $this->assertFalse($blocked->doctor_commission_enabled);
        $blocked->update(['done' => 'انجام شد']);
        $blocked->update(['source' => 'معرفی دوستان']);
        $this->assertFalse($blocked->doctor_commission_enabled);
        $blocked->update(['source' => 'تبلیغات ویژه']);
        $controller->resyncResourceEarningsForSources(['تبلیغات ویژه']);
        $this->assertDatabaseMissing('resource_earning_lines', ['appointment_id' => $blocked->id]);
        $this->assertDatabaseHas('resource_earning_lines', ['appointment_id' => $appointment->id, 'resource_type' => 'doctor', 'amount' => 100]);

        app(ChannelController::class)->store(Request::create('/api/channels', 'POST', [[
            'id' => $channel->id,
            'name' => $channel->name,
            'icon' => null,
            'exclude_doctor_commission' => false,
            'exclude_staff_commission' => false,
            'exclude_referral_reward' => false,
        ]]));
        $restored = $appointment->replicate(['doctor_commission_enabled', 'staff_commission_enabled', 'referral_reward_enabled']);
        $restored->day_num = 3;
        $restored->save();
        $controller->resyncResourceEarningsForSources(['تبلیغات ویژه']);
        $this->assertTrue($restored->doctor_commission_enabled);
        $this->assertTrue($restored->staff_commission_enabled);
        $this->assertTrue($restored->referral_reward_enabled);
        $this->assertDatabaseMissing('resource_earning_lines', ['appointment_id' => $blocked->id]);
        $this->assertDatabaseHas('resource_earning_lines', ['appointment_id' => $restored->id, 'resource_type' => 'doctor', 'amount' => 100]);
        $this->assertSame(1, ResourceEarningLine::query()->where('resource_type', 'doctor')->where('earning_type', 'sales_bonus')->count());
        $this->assertSame(1, ResourceEarningLine::query()->where('appointment_id', $restored->id)->where('resource_type', 'staff')->count());
    }

    public function test_referrer_reward_uses_the_appointment_snapshot(): void
    {
        Channel::query()->create(['name' => 'معرفی', 'exclude_referral_reward' => true]);
        \DB::table('patients')->insert(['first_name' => 'معرف', 'last_name' => 'تست', 'phone' => '09120000001', 'file_number' => 'R-1']);
        \DB::table('patients')->insert(['first_name' => 'بیمار', 'last_name' => 'تست', 'phone' => '09120000002', 'file_number' => 'P-1']);
        Inventory::query()->create(['name' => 'خدمت تست', 'amount' => 1000, 'price' => 0]);
        AppSetting::query()->create(['key' => 'referral_wallet_settings', 'value' => json_encode([
            'enabled' => true, 'default_type' => 'percent', 'default_value' => 10,
        ])]);
        $appointment = Appointment::query()->create([
            'month' => '1405-08', 'day_num' => 1, 'source' => 'معرفی',
            'file_number' => 'P-1', 'phone' => '09120000002', 'amount' => 1000,
            'services' => [['name' => 'خدمت تست', 'cc' => 1]],
        ]);
        $this->assertFalse($appointment->referral_reward_enabled);

        $draft = [
            ...$appointment->toArray(), 'referrer_phone' => '09120000001',
            'done' => 'انجام شد', 'debt' => 0,
            'payment_details' => ['cash' => 1000],
        ];
        unset($draft['id']);
        $method = new \ReflectionMethod(AppointmentController::class, 'calculateReferralReward');
        $controller = app(AppointmentController::class);
        $this->assertSame(0, $method->invoke($controller, $draft)['amount']);

        Channel::query()->firstOrFail()->update(['exclude_referral_reward' => false]);
        $this->assertSame(0, $method->invoke($controller, $draft)['amount']);
        $allowed = $appointment->replicate(['doctor_commission_enabled', 'staff_commission_enabled', 'referral_reward_enabled']);
        $allowed->day_num = 2;
        $allowed->save();
        $this->assertTrue($allowed->referral_reward_enabled);
        $this->assertSame(100, $method->invoke($controller, [...$draft, 'referral_reward_enabled' => true])['amount']);
    }
}
