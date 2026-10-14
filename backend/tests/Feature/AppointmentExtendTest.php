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

class AppointmentExtendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([AppointmentChanged::class]);
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('month');
            $table->integer('day_num');
            $table->integer('sort_order')->default(0);
            $table->string('time');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->integer('lock_version')->default(1);
            $table->string('lastname')->nullable();
            $table->string('status')->nullable();
            $table->json('services')->nullable();
            $table->timestamps();
        });
    }

    private function appointment(string $time = '09:00', string $name = 'patient'): Appointment
    {
        return Appointment::create([
            'month' => '1405-07',
            'day_num' => 10,
            'time' => $time,
            'lastname' => $name,
            'status' => 'وقت داده شد',
            'services' => [],
        ]);
    }

    private function extend(Appointment $appointment, string $nextTime, int $duration, int $version = 1)
    {
        return app(AppointmentController::class)->extend(Request::create('/', 'POST', [
            'lock_version' => $version,
            'next_time' => $nextTime,
            'interval_minutes' => 15,
            'current_duration_minutes' => $duration,
        ]), $appointment);
    }

    public function test_it_extends_the_same_appointment_into_the_next_slot(): void
    {
        $appointment = $this->appointment();

        $response = $this->extend($appointment, '09:15', 15);

        $this->assertSame(30, $response->getData(true)['appointment']['duration_minutes']);
        $this->assertSame(30, $appointment->fresh()->duration_minutes);
        $this->assertSame(2, $appointment->fresh()->lock_version);
        $this->assertSame(1, Appointment::count());
        Event::assertDispatched(AppointmentChanged::class);
    }

    public function test_it_rejects_an_occupied_next_slot(): void
    {
        $appointment = $this->appointment();
        $this->appointment('09:15', 'already booked');

        try {
            $this->extend($appointment, '09:15', 15);
            $this->fail('An occupied next slot must not be merged.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertNull($appointment->fresh()->duration_minutes);
        $this->assertSame(1, $appointment->fresh()->lock_version);
    }

    public function test_it_can_extend_an_already_merged_appointment_again(): void
    {
        $appointment = $this->appointment();
        $this->extend($appointment, '09:15', 15);

        $this->extend($appointment->fresh(), '09:30', 30, 2);

        $this->assertSame(45, $appointment->fresh()->duration_minutes);
        $this->assertSame(3, $appointment->fresh()->lock_version);
    }
}
