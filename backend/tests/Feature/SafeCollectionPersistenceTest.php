<?php

namespace Tests\Feature;

use App\Models\AutomaticSmsProject;
use App\Models\AutomaticSmsScenario;
use App\Models\Contact;
use App\Models\Inventory;
use App\Models\Patient;
use App\Services\WalletAllocationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SafeCollectionPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \App\Http\Middleware\EnsureTenantIsActive::class,
            \App\Http\Middleware\EnsureTenantSubscriptionActive::class,
            \App\Http\Middleware\EnforceTenantStorageQuota::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
            \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            foreach (['full_name', 'phone', 'date', 'follow_up_date', 'gender', 'consultant', 'source', 'status', 'interest'] as $name) $table->string($name)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
        });
        Schema::create('automatic_sms_projects', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->boolean('is_active')->default(false); $table->timestamps();
        });
        Schema::create('automatic_sms_scenarios', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->string('name'); $table->string('inventory_tag'); $table->boolean('is_active')->default(false); $table->timestamps();
        });
        Schema::create('automatic_sms_scenario_steps', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('scenario_id'); $table->integer('sort_order'); $table->integer('days_after'); $table->string('send_at'); $table->string('sms_template'); $table->timestamps();
            $table->unique(['scenario_id', 'sort_order']);
        });
        Schema::create('inventories', function (Blueprint $table) {
            $table->id(); $table->string('name')->nullable(); $table->timestamps();
        });
        foreach (['doctors', 'staff'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id(); $table->unsignedBigInteger('user_id')->nullable(); $table->string('name');
                $table->decimal('bonus', 10, 2)->default(0); $table->string('commission_customer_scope')->default('both');
                $table->boolean('commission_after_materials')->default(false); $table->boolean('sales_bonus_enabled')->default(false);
                $table->json('sales_bonus_tiers')->nullable(); $table->decimal('salary', 15, 2)->default(0);
                foreach (['hourly_rate', 'overtime_hourly_rate', 'shortage_hourly_deduction', 'absence_deduction', 'allowed_shortage_hours'] as $name) $table->decimal($name, 15, 2)->default(0);
                if ($tableName === 'doctors') { $table->json('available_days')->nullable(); $table->json('service_section_ids')->nullable(); }
                $table->string('profile_photo_path')->nullable(); $table->string('profile_thumbnail_path')->nullable(); $table->timestamps();
            });
        }
        Schema::create('inventory_booking_settings', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('inventory_id')->unique();
            foreach (['booking_enabled', 'use_default_schedule', 'requires_doctor', 'requires_operator', 'conflict_check_enabled', 'online_enabled', 'online_payment_enabled', 'online_cancellation_enabled'] as $name) $table->boolean($name)->default(false);
            $table->string('assignment_mode')->default('auto'); $table->integer('slot_interval_minutes')->nullable();
            $table->string('online_start_time')->nullable(); $table->string('online_end_time')->nullable();
            $table->integer('booking_start_after_days')->default(0); $table->integer('booking_available_days')->default(30);
            $table->json('extra_settings')->nullable(); $table->timestamps();
        });
        Schema::create('inventory_booking_resources', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('inventory_id'); $table->string('role'); $table->unsignedBigInteger('doctor_id')->nullable(); $table->unsignedBigInteger('staff_id')->nullable(); $table->boolean('active'); $table->integer('sort_order'); $table->timestamps();
        });
        Schema::create('inventory_booking_availabilities', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('booking_resource_id'); $table->integer('weekday'); $table->string('start_time'); $table->string('end_time'); $table->integer('slot_interval_minutes')->nullable(); $table->boolean('active'); $table->timestamps();
        });
        Schema::create('inventory_booking_breaks', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('availability_id'); $table->string('title')->nullable(); $table->string('start_time'); $table->string('end_time'); $table->boolean('active'); $table->timestamps();
        });
        Schema::create('inventory_booking_exceptions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('inventory_id'); $table->unsignedBigInteger('booking_resource_id')->nullable(); $table->string('exception_date'); $table->string('type'); $table->string('start_time')->nullable(); $table->string('end_time')->nullable(); $table->string('reason')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('inventory_booking_rules', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('inventory_id'); $table->string('rule_type'); $table->json('rule_value')->nullable(); $table->boolean('active'); $table->timestamps();
        });
        Schema::create('inventory_booking_audits', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('inventory_id'); $table->unsignedBigInteger('changed_by')->nullable(); $table->json('before_data')->nullable(); $table->json('after_data')->nullable(); $table->timestamp('changed_at'); $table->timestamps();
        });
    }

    public function test_contact_save_only_inserts_new_row_and_keeps_existing_id_and_campaign(): void
    {
        $old = Contact::create(['full_name' => 'قبلی', 'campaign_id' => 7]);
        $this->postJson('/api/contacts', ['items' => [['full_name' => 'جدید']]])->assertOk();
        $this->assertDatabaseHas('contacts', ['id' => $old->id, 'full_name' => 'قبلی', 'campaign_id' => 7]);
        $this->assertDatabaseHas('contacts', ['full_name' => 'جدید']);
        $this->postJson('/api/contacts', ['items' => [[
            'id' => $old->id, 'save_version' => $old->fresh()->save_version, 'full_name' => 'ویرایش‌شده',
        ]]])->assertOk();
        $this->assertDatabaseHas('contacts', ['id' => $old->id, 'full_name' => 'ویرایش‌شده', 'campaign_id' => 7]);
        $this->postJson('/api/contacts', ['items' => [[
            'id' => $old->id, 'save_version' => 'stale', 'full_name' => 'نسخه قدیمی',
        ]]])->assertUnprocessable();
    }

    public function test_sms_step_addition_preserves_existing_step_id(): void
    {
        $project = AutomaticSmsProject::create(['name' => 'طرح']);
        $scenario = AutomaticSmsScenario::create(['project_id' => $project->id, 'name' => 'خدمت', 'inventory_tag' => 'ژل']);
        $step = $scenario->steps()->create(['sort_order' => 1, 'days_after' => 3, 'send_at' => '10:00', 'sms_template' => 'الگو']);
        $scenario = $scenario->fresh()->load('steps');
        $oldVersion = $scenario->save_version;
        $this->assertSame($oldVersion, $scenario->fresh()->save_version);

        $this->putJson('/api/automatic-sms-scenarios/'.$scenario->id, [
            'project_id' => $project->id, 'updated_at' => $scenario->updated_at->toJSON(), 'save_version' => $oldVersion, 'name' => 'خدمت', 'inventory_tag' => 'ژل', 'is_active' => false,
            'steps' => [
                ['id' => $step->id, 'days_after' => 3, 'send_at' => '10:00', 'sms_template' => 'الگو'],
                ['days_after' => 6, 'send_at' => '11:00', 'sms_template' => 'الگوی جدید'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('automatic_sms_scenario_steps', ['id' => $step->id, 'sort_order' => 1]);
        $this->assertDatabaseCount('automatic_sms_scenario_steps', 2);
        $this->putJson('/api/automatic-sms-scenarios/'.$scenario->id, [
            'project_id' => $project->id, 'save_version' => $oldVersion, 'name' => 'نام قدیمی', 'inventory_tag' => 'ژل', 'is_active' => false,
            'steps' => [['id' => $step->id, 'days_after' => 3, 'send_at' => '10:00', 'sms_template' => 'الگو']],
        ])->assertStatus(409);
        $this->assertDatabaseCount('automatic_sms_scenario_steps', 2);
    }

    public function test_adding_a_doctor_or_staff_does_not_delete_existing_resources(): void
    {
        $doctor = \App\Models\Doctor::create(['name' => 'پزشک قبلی']);
        $staff = \App\Models\Staff::create(['name' => 'پرسنل قبلی']);

        $this->postJson('/api/doctors', ['items' => [['name' => 'پزشک جدید']]])->assertOk();
        $this->postJson('/api/staff', ['items' => [['name' => 'پرسنل جدید']]])->assertOk();

        $this->assertDatabaseHas('doctors', ['id' => $doctor->id, 'name' => 'پزشک قبلی']);
        $this->assertDatabaseHas('staff', ['id' => $staff->id, 'name' => 'پرسنل قبلی']);
        $this->assertDatabaseCount('doctors', 2);
        $this->assertDatabaseCount('staff', 2);
        $this->postJson('/api/doctors', ['items' => [[
            'id' => $doctor->id, 'save_version' => $doctor->fresh()->save_version, 'bonus' => 5,
        ]]])->assertOk();
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id, 'name' => 'پزشک قبلی', 'bonus' => 5]);
        $this->postJson('/api/staff', ['items' => [[
            'id' => $staff->id, 'save_version' => 'stale', 'bonus' => 9,
        ]]])->assertUnprocessable();
        $this->assertDatabaseHas('staff', ['id' => $staff->id, 'name' => 'پرسنل قبلی', 'bonus' => 0]);
    }

    public function test_booking_settings_addition_preserves_resource_and_availability_ids(): void
    {
        $inventory = Inventory::create(['name' => 'خدمت']);
        DB::table('doctors')->insert(['id' => 1, 'name' => 'پزشک']);
        $settings = [
            'booking_enabled' => true, 'use_default_schedule' => false, 'requires_doctor' => true,
            'requires_operator' => false, 'assignment_mode' => 'manual', 'conflict_check_enabled' => true,
            'online_enabled' => false, 'online_payment_enabled' => false, 'online_cancellation_enabled' => false,
            'booking_start_after_days' => 0, 'booking_available_days' => 30,
        ];
        $first = $this->putJson('/api/inventory/'.$inventory->id.'/booking-settings', [
            'booking_version' => 0, 'settings' => $settings,
            'resources' => [['role' => 'doctor', 'doctor_id' => 1, 'active' => true, 'availabilities' => [
                ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'active' => true, 'breaks' => []],
            ]]], 'exceptions' => [], 'rules' => [],
        ])->assertOk()->json();

        $resourceId = $first['resources'][0]['id'];
        $availabilityId = $first['resources'][0]['availabilities'][0]['id'];
        $second = $this->putJson('/api/inventory/'.$inventory->id.'/booking-settings', [
            'booking_version' => $first['booking_version'], 'settings' => $settings,
            'resources' => [['id' => $resourceId, 'role' => 'doctor', 'doctor_id' => 1, 'active' => true, 'availabilities' => [
                ['id' => $availabilityId, 'weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'active' => true, 'breaks' => [
                    ['title' => 'استراحت', 'start_time' => '12:00', 'end_time' => '13:00', 'active' => true],
                ]],
            ]]], 'exceptions' => [], 'rules' => [],
        ])->assertOk()->json();

        $this->assertDatabaseHas('inventory_booking_resources', ['id' => $resourceId]);
        $this->assertDatabaseHas('inventory_booking_availabilities', ['id' => $availabilityId]);
        $this->assertDatabaseCount('inventory_booking_breaks', 1);
        $this->assertGreaterThan($first['booking_version'], $second['booking_version']);
        $this->putJson('/api/inventory/'.$inventory->id.'/booking-settings', [
            'booking_version' => $first['booking_version'], 'settings' => $settings,
            'resources' => [], 'exceptions' => [], 'rules' => [],
        ])->assertStatus(409);
        $this->assertDatabaseHas('inventory_booking_resources', ['id' => $resourceId]);
    }

    public function test_wallet_allocation_sync_keeps_existing_allocation_id(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id(); $table->softDeletes(); $table->timestamps();
        });
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('patient_id'); $table->string('type');
            $table->decimal('amount', 15, 2); $table->string('source_type')->nullable();
            $table->unsignedBigInteger('reversed_transaction_id')->nullable(); $table->timestamp('expires_at')->nullable(); $table->timestamps();
        });
        Schema::create('wallet_transaction_allocations', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('patient_id'); $table->unsignedBigInteger('credit_transaction_id');
            $table->unsignedBigInteger('debit_transaction_id'); $table->decimal('amount', 15, 2);
            $table->timestamp('used_at'); $table->timestamps();
        });
        $patientId = DB::table('patients')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('wallet_transactions')->insert([
            ['patient_id' => $patientId, 'type' => 'deposit', 'amount' => 100, 'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()],
            ['patient_id' => $patientId, 'type' => 'withdraw', 'amount' => 25, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $patient = Patient::findOrFail($patientId);
        $service = app(WalletAllocationService::class);
        $service->sync($patient);
        $allocationId = DB::table('wallet_transaction_allocations')->value('id');
        $service->sync($patient);
        $this->assertSame($allocationId, DB::table('wallet_transaction_allocations')->value('id'));
        $this->assertDatabaseCount('wallet_transaction_allocations', 1);
        $this->assertDatabaseHas('wallet_transaction_allocations', ['id' => $allocationId, 'amount' => 25]);
    }
}
