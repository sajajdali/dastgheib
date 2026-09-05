<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AppointmentConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_or_stale_scheduler_payload_cannot_remove_existing_appointments(): void
    {
        $this->actingAs($this->user());
        $appointment = Appointment::create(['month' => '1405-06', 'day_num' => 1, 'lastname' => 'ثبت‌شده', 'time' => '09:00', 'lock_version' => 2]);

        $this->postJson('/api/appointments', ['month' => '1405-06', 'appointments' => []])->assertUnprocessable();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);

        $this->postJson('/api/appointments', ['month' => '1405-06', 'appointments' => [[
            'appointment_id' => $appointment->id, 'lock_version' => 1, 'day_num' => 1, 'lastname' => 'نسخه قدیمی', 'time' => '09:00', 'services' => [],
        ]]])->assertStatus(409);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'lastname' => 'ثبت‌شده']);
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('appointments.create', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('appointments.update', 'web'));
        return $user;
    }
}
