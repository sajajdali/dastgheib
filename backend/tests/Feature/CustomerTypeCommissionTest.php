<?php

namespace Tests\Feature;

use App\Http\Controllers\AppointmentController;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Inventory;
use App\Models\ResourceEarningLine;
use App\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** پورسانت جداگانهٔ مشتری جدید و قدیمی؛ درصد تعریف‌نشده یعنی بدون پورسانت. */
class CustomerTypeCommissionTest extends TestCase
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
    }

    private function resourceTable(Blueprint $table): void
    {
        $table->id(); $table->string('name'); $table->decimal('bonus', 8, 2)->default(0);
        $table->string('commission_customer_scope')->default('both');
        $table->decimal('new_customer_bonus', 5, 2)->nullable(); $table->decimal('existing_customer_bonus', 5, 2)->nullable();
        $table->boolean('commission_after_materials')->default(false);
        $table->boolean('sales_bonus_enabled')->default(false); $table->json('sales_bonus_tiers')->nullable();
        $table->decimal('salary', 15, 2)->default(0); $table->timestamps();
    }

    public function test_each_customer_type_uses_its_own_percent_and_undefined_means_no_commission(): void
    {
        $doctor = Doctor::query()->create(['name' => 'پزشک تست', 'new_customer_bonus' => 10, 'existing_customer_bonus' => null]);
        $staff = Staff::query()->create(['name' => 'مشاور تست', 'new_customer_bonus' => 2, 'existing_customer_bonus' => 5]);
        Inventory::query()->create(['name' => 'خدمت تست', 'amount' => 1000, 'price' => 100]);

        $appointment = fn (bool $newCustomer, int $day) => Appointment::query()->create([
            'month' => '1405-08', 'day_num' => $day, 'source' => 'اینستاگرام', 'new_customer' => $newCustomer,
            'doctor' => $doctor->name, 'consultant' => $staff->name, 'amount' => 1000,
            'services' => [['name' => 'خدمت تست', 'cc' => 1, 'doctor' => $doctor->name, 'consultant' => $staff->name]],
        ]);
        $newVisit = $appointment(true, 1);
        $returningVisit = $appointment(false, 2);

        app(AppointmentController::class)->resyncResourceEarningsForSources(['اینستاگرام']);

        $amount = fn (Appointment $visit, string $type) => ResourceEarningLine::query()
            ->where('appointment_id', $visit->id)->where('resource_type', $type)->sum('amount');
        $this->assertSame(100.0, (float) $amount($newVisit, 'doctor'));
        $this->assertSame(20.0, (float) $amount($newVisit, 'staff'));
        $this->assertSame(0.0, (float) $amount($returningVisit, 'doctor'));
        $this->assertSame(50.0, (float) $amount($returningVisit, 'staff'));
    }

    public function test_legacy_and_new_fields_stay_in_sync(): void
    {
        $legacy = Staff::query()->create(['name' => 'پرسنل قدیمی', 'bonus' => 8, 'commission_customer_scope' => 'existing']);
        $this->assertNull($legacy->new_customer_bonus);
        $this->assertSame(8.0, $legacy->existing_customer_bonus);

        $legacy->update(['new_customer_bonus' => 12, 'existing_customer_bonus' => null]);
        $this->assertSame('new', $legacy->commission_customer_scope);
        $this->assertSame(12.0, (float) $legacy->bonus);

        $legacy->update(['new_customer_bonus' => null]);
        $this->assertSame('none', $legacy->commission_customer_scope);
        $this->assertFalse($legacy->receivesCommissionFor(true));
        $this->assertFalse($legacy->receivesCommissionFor(false));
    }
}
