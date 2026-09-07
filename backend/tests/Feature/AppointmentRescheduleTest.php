<?php

namespace Tests\Feature;

use App\Events\AppointmentChanged;
use App\Http\Controllers\AppointmentController;
use App\Models\Appointment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AppointmentRescheduleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([AppointmentChanged::class]);
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('month');
            $table->integer('day_num');
            $table->string('time');
            $table->integer('lock_version')->default(1);
            $table->string('lastname');
            $table->string('status')->nullable();
            $table->json('services')->nullable();
            $table->json('payment_details')->nullable();
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

    private function move(Appointment $appointment, int $version = 1, string $month = '1405-07')
    {
        return app(AppointmentController::class)->reschedule(
            Request::create('/', 'POST', [
                'lock_version' => $version, 'month' => $month, 'day_num' => 2, 'time' => '11:30',
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
        $this->assertSame(1, Appointment::count());
        $this->assertSame('1405-07', $updated->month);
        $this->assertSame(2, $updated->day_num);
        $this->assertSame('11:30', $updated->time);
        $this->assertSame(2, $updated->lock_version);
        $this->assertSame('انتقال داده شده', $updated->status);
        foreach (['id', 'lastname', 'services', 'payment_details'] as $field) {
            $this->assertEquals($original[$field], $updated->toArray()[$field]);
        }
        Event::assertDispatched(AppointmentChanged::class, fn ($event) => $event->month === '1405-06');
        Event::assertDispatched(AppointmentChanged::class, fn ($event) => $event->month === '1405-07');
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

    public function test_events_use_distinct_private_tenant_channels(): void
    {
        $first = new AppointmentChanged('clinic-a', 'rescheduled', 1, '1405-07', 2);
        $second = new AppointmentChanged('clinic-b', 'rescheduled', 1, '1405-07', 2);
        $this->assertSame('private-clinic.clinic-a.appointments', $first->broadcastOn()[0]->name);
        $this->assertNotSame($first->broadcastOn()[0]->name, $second->broadcastOn()[0]->name);
    }

    public function test_followup_without_services_returns_validation_error(): void
    {
        $appointment = $this->appointment();
        $appointment->update(['services' => []]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Http\Controllers\ServiceFollowupController::class)->scheduleFromAppointment(
            Request::create('/', 'POST', ['due_date' => '2026-10-01']), $appointment
        );
    }
}
