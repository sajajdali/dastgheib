<?php

namespace Tests\Feature;

use App\Events\AppointmentChanged;
use App\Http\Controllers\AppointmentController;
use App\Models\Appointment;
use App\Models\Inventory;
use App\Models\InventoryAddonDefinition;
use App\Models\ResourceEarningLine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesPayrollTables;
use Tests\TestCase;

class AppointmentRescheduleTest extends TestCase
{
    use CreatesPayrollTables;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([AppointmentChanged::class]);
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('month');
            $table->integer('day_num');
            $table->string('time');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->integer('lock_version')->default(1);
            $table->string('lastname');
            $table->string('doctor')->nullable();
            $table->string('consultant')->nullable();
            $table->string('status')->nullable();
            $table->string('done')->nullable();
            $table->json('services')->nullable();
            $table->json('payment_details')->nullable();
            $table->timestamps();
        });
        $this->createPayrollTables();
        Schema::table('inventories', function (Blueprint $table) {
            $table->integer('stock')->nullable();
            $table->boolean('track_stock')->default(false);
        });
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id')->nullable();
            $table->unsignedBigInteger('inventory_addon_definition_id')->nullable();
            $table->string('inventory_name');
            $table->decimal('quantity', 14, 3);
            $table->string('type');
            $table->string('source_key')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
        Schema::create('inventory_addon_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('stock')->nullable();
            $table->boolean('track_stock')->default(false);
            $table->timestamps();
        });
    }

    private function appointment(): Appointment
    {
        return Appointment::create([
            'month' => '1405-06', 'day_num' => 15, 'time' => '09:00',
            'lastname' => 'patient', 'status' => 'booked',
            'services' => [['name' => 'service', 'cc' => 1]],
            'payment_details' => ['deposit' => 500000],
        ]);
    }

    private function syncStock(?Appointment $previous, ?Appointment $current): void
    {
        $method = new \ReflectionMethod(AppointmentController::class, 'syncAppointmentInventoryStock');
        $method->invoke(app(AppointmentController::class), [['previous' => $previous, 'current' => $current]], collect());
    }

    public function test_stock_is_optional_and_reconciles_main_and_addon_by_id(): void
    {
        $main = Inventory::create(['name' => 'service', 'stock' => 10, 'track_stock' => true]);
        $addon = InventoryAddonDefinition::create(['name' => 'addon', 'stock' => 5, 'track_stock' => true]);
        $untracked = Inventory::create(['name' => 'untracked', 'stock' => 9, 'track_stock' => false]);
        $appointment = $this->appointment();
        $appointment->services = [
            ['name' => 'service', 'inventory_id' => $main->id, 'cc' => '', 'addons' => [
                ['name' => 'addon', 'addon_definition_id' => $addon->id, 'cc' => '2'],
            ]],
            ['name' => 'untracked', 'inventory_id' => $untracked->id, 'cc' => '3'],
        ];
        $appointment->save();
        $this->syncStock(null, $appointment);
        $this->assertSame(10, (int) $main->fresh()->stock);
        $this->assertSame(5, (int) $addon->fresh()->stock);

        $before = clone $appointment;
        $appointment->done = 'انجام شد';
        $appointment->save();
        $this->syncStock($before, $appointment);
        $this->assertSame(10, (int) $main->fresh()->stock);
        $this->assertSame(3, (int) $addon->fresh()->stock);
        $this->assertSame(9, (int) $untracked->fresh()->stock);
        $this->syncStock($appointment, $appointment);
        $this->assertSame(3, (int) $addon->fresh()->stock);

        $before = clone $appointment;
        $appointment->services = [['name' => 'renamed', 'inventory_id' => $main->id, 'cc' => 2,
            'addons' => [['name' => 'renamed addon', 'addon_definition_id' => $addon->id, 'cc' => 1]]]];
        $appointment->save();
        $this->syncStock($before, $appointment);
        $this->assertSame(8, (int) $main->fresh()->stock);
        $this->assertSame(4, (int) $addon->fresh()->stock);

        $method = new \ReflectionMethod(AppointmentController::class, 'syncAppointmentInventoryStock');
        $method->invoke(app(AppointmentController::class), [], collect([$appointment]));
        $this->assertSame(10, (int) $main->fresh()->stock);
        $this->assertSame(5, (int) $addon->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_addon_definition_id' => $addon->id, 'appointment_id' => $appointment->id,
            'type' => 'appointment_reversal', 'quantity' => 1,
        ]);
    }

    public function test_moving_completed_appointment_restores_stock(): void
    {
        $main = Inventory::create(['name' => 'service', 'stock' => 10, 'track_stock' => true]);
        $appointment = $this->appointment();
        $appointment->done = 'انجام شد';
        $appointment->services = [['name' => 'service', 'inventory_id' => $main->id, 'cc' => 2]];
        $appointment->save();
        $this->syncStock(null, $appointment);
        $this->assertSame(8, (int) $main->fresh()->stock);
        $this->move($appointment);
        $this->assertSame(10, (int) $main->fresh()->stock);
    }

    public function test_old_appointment_restores_after_inventory_rename(): void
    {
        $main = Inventory::create(['name' => 'service', 'stock' => 10, 'track_stock' => true]);
        $appointment = $this->appointment();
        $appointment->done = 'انجام شد';
        $appointment->services = [['name' => 'service', 'cc' => 2]];
        $appointment->save();
        $this->syncStock(null, $appointment);
        $this->assertSame(8, (int) $main->fresh()->stock);
        $main->update(['name' => 'renamed']);
        $method = new \ReflectionMethod(AppointmentController::class, 'syncAppointmentInventoryStock');
        $method->invoke(app(AppointmentController::class), [], collect([$appointment]));
        $this->assertSame(10, (int) $main->fresh()->stock);
    }

    public function test_enabling_tracking_does_not_restore_stock_for_old_untracked_visit(): void
    {
        $main = Inventory::create(['name' => 'service', 'stock' => 10, 'track_stock' => false]);
        $appointment = $this->appointment();
        $appointment->done = 'انجام شد';
        $appointment->save();
        $this->syncStock(null, $appointment);
        $main->update(['track_stock' => true]);
        $method = new \ReflectionMethod(AppointmentController::class, 'syncAppointmentInventoryStock');
        $method->invoke(app(AppointmentController::class), [], collect([$appointment]));
        $this->assertSame(10, (int) $main->fresh()->stock);
    }

    public function test_fractional_quantity_is_rejected_for_main_and_addon(): void
    {
        foreach ([
            [['name' => 'service', 'cc' => '1.5']],
            [['name' => 'service', 'cc' => 1, 'addons' => [['name' => 'addon', 'cc' => '0.5']]]],
        ] as $services) {
            try {
                app(AppointmentController::class)->saveAppointments(Request::create('/', 'POST', [
                    'month' => '1405-06', 'appointments' => [['services' => $services]],
                ]));
                $this->fail('Fractional quantity was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    private function move(Appointment $appointment, int $version = 1, string $month = '1405-07', bool $allowOccupiedSlot = false)
    {
        return app(AppointmentController::class)->reschedule(
            Request::create('/', 'POST', [
                'lock_version' => $version, 'month' => $month, 'day_num' => 2, 'time' => '11:30',
                'allow_occupied_slot' => $allowOccupiedSlot,
                'services' => [], 'payment_details' => [],
            ]), $appointment
        );
    }

    public function test_move_preserves_identity_services_and_payment_and_notifies_both_months(): void
    {
        $appointment = $this->appointment();
        $original = $appointment->toArray();
        $this->move($appointment);
        $updated = $appointment->fresh();
        $this->assertSame(2, Appointment::count());
        $this->assertSame('1405-07', $updated->month);
        $this->assertSame(2, $updated->day_num);
        $this->assertSame('11:30', $updated->time);
        $this->assertSame(2, $updated->lock_version);
        $this->assertSame('وقت داده شد', $updated->status);
        foreach (['id', 'lastname', 'services', 'payment_details'] as $field) {
            $this->assertEquals($original[$field], $updated->toArray()[$field]);
        }
        $marker = Appointment::query()->whereKeyNot($updated->id)->firstOrFail();
        $this->assertSame('1405-06', $marker->month);
        $this->assertSame(15, $marker->day_num);
        $this->assertSame('09:00', $marker->time);
        $this->assertSame('انتقال داده شده', $marker->status);
        $this->assertSame(0, (int) $marker->amount);
        $this->assertNull($marker->payment_details);
        Event::assertDispatched(AppointmentChanged::class, fn ($event) => $event->month === '1405-06');
        Event::assertDispatched(AppointmentChanged::class, fn ($event) => $event->month === '1405-07');
    }

    public function test_move_removes_old_month_commission_until_the_visit_is_done_again(): void
    {
        $appointment = $this->appointment();
        $appointment->update(['done' => 'انجام شد']);
        ResourceEarningLine::query()->create([
            'appointment_id' => $appointment->id, 'month' => '1405-06', 'day_num' => 15,
            'resource_type' => 'doctor', 'resource_id' => 1, 'resource_name' => 'doctor',
            'earning_type' => 'base_commission', 'amount' => 100000,
        ]);

        $this->move($appointment);

        $this->assertNull($appointment->fresh()->done);
        $this->assertSame(0, ResourceEarningLine::query()->where('month', '1405-06')->count());
    }

    public function test_stale_transfer_does_not_overwrite_newer_appointment(): void
    {
        $appointment = $this->appointment();
        $this->move($appointment);
        try {
            $this->move($appointment, 1, '1405-08');
            $this->fail('Stale move must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame('1405-07', $appointment->fresh()->month);
        $this->assertSame(2, $appointment->fresh()->lock_version);
    }

    public function test_transfer_cannot_overwrite_an_occupied_time_slot(): void
    {
        $appointment = $this->appointment();
        Appointment::create([
            'month' => '1405-07', 'day_num' => 2, 'time' => '11:30',
            'lastname' => 'already booked', 'status' => 'booked',
            'services' => [], 'payment_details' => [],
        ]);

        $response = $this->move($appointment);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('appointment_slot_occupied_confirmation_required', $response->getData(true)['code']);
        $this->assertSame(1, $response->getData(true)['occupied_count']);

        $this->assertSame('1405-06', $appointment->fresh()->month);
        $this->assertSame('09:00', $appointment->fresh()->time);
    }

    public function test_transfer_requires_confirmation_then_allows_same_time_for_a_different_doctor(): void
    {
        $appointment = $this->appointment();
        $appointment->update(['doctor' => 'دکتر الف']);
        Appointment::create([
            'month' => '1405-07', 'day_num' => 2, 'time' => '11:30',
            'lastname' => 'already booked', 'status' => 'booked',
            'doctor' => 'دکتر ب', 'services' => [], 'payment_details' => [],
        ]);

        $warning = $this->move($appointment, $appointment->fresh()->lock_version);
        $this->assertSame(409, $warning->getStatusCode());
        $this->assertSame(1, $warning->getData(true)['occupied_count']);

        $this->move($appointment, $appointment->fresh()->lock_version, '1405-07', true);

        $this->assertSame('1405-07', $appointment->fresh()->month);
        $this->assertSame(2, $appointment->fresh()->day_num);
        $this->assertSame('11:30', $appointment->fresh()->time);
    }

    public function test_transfer_requires_confirmation_for_overlapping_time_for_the_same_doctor(): void
    {
        $appointment = $this->appointment();
        $appointment->update(['doctor' => 'دکتر الف']);
        Appointment::create([
            'month' => '1405-07', 'day_num' => 2, 'time' => '11:30',
            'lastname' => 'already booked', 'status' => 'booked',
            'doctor' => 'دکتر الف', 'services' => [], 'payment_details' => [],
        ]);

        $warning = $this->move($appointment, $appointment->fresh()->lock_version);
        $this->assertSame(409, $warning->getStatusCode());
        $this->assertSame(1, $warning->getData(true)['occupied_count']);
    }

    public function test_confirmed_transfer_can_stack_with_multiple_appointments(): void
    {
        $appointment = $this->appointment();
        foreach (['first booking', 'second booking'] as $name) {
            Appointment::create([
                'month' => '1405-07', 'day_num' => 2, 'time' => '11:30',
                'lastname' => $name, 'status' => 'booked',
                'services' => [], 'payment_details' => [],
            ]);
        }

        $warning = $this->move($appointment);
        $this->assertSame(2, $warning->getData(true)['occupied_count']);

        $this->move($appointment, $appointment->fresh()->lock_version, '1405-07', true);

        $this->assertSame(3, Appointment::query()
            ->where('month', '1405-07')->where('day_num', 2)->where('time', '11:30')->count());
    }

    public function test_transfer_marker_does_not_block_its_old_slot(): void
    {
        $appointment = $this->appointment();
        Appointment::create([
            'month' => '1405-07', 'day_num' => 2, 'time' => '11:30',
            'lastname' => 'previously moved patient', 'status' => 'انتقال داده شده',
            'services' => [], 'payment_details' => [],
        ]);

        $response = $this->move($appointment);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('1405-07', $appointment->fresh()->month);
        $this->assertSame('11:30', $appointment->fresh()->time);
    }

    public function test_events_use_distinct_private_tenant_channels(): void
    {
        $first = new AppointmentChanged('clinic-a', 'rescheduled', 1, '1405-07', 2);
        $second = new AppointmentChanged('clinic-b', 'rescheduled', 1, '1405-07', 2);
        $this->assertSame('private-clinic.clinic-a.appointments', $first->broadcastOn()[0]->name);
        $this->assertNotSame($first->broadcastOn()[0]->name, $second->broadcastOn()[0]->name);
    }

}
