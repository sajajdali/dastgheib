<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HumanResourceStaffCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_commission_rules_are_saved_and_percentage_is_limited(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('resources.staff', 'web'));
        $this->actingAs($user);

        $payload = [[
            'name' => 'پرسنل تست',
            'bonus' => 12.5,
            'commission_customer_scope' => 'existing',
            'commission_after_materials' => true,
            'sales_bonus_enabled' => true,
            'sales_bonus_tiers' => [['sales_from' => 1000000, 'salary_addition' => 250000]],
            'salary' => 8000000,
        ]];

        $this->postJson('/api/staff', $payload)->assertOk();
        $staff = Staff::firstOrFail();
        $this->assertSame(12.5, (float) $staff->bonus);
        $this->assertSame('existing', $staff->commission_customer_scope);
        $this->assertTrue($staff->commission_after_materials);
        $this->assertTrue($staff->sales_bonus_enabled);
        $this->assertSame(1000000, (int) $staff->sales_bonus_tiers[0]['sales_from']);

        $payload[0]['bonus'] = 101;
        $this->postJson('/api/staff', $payload)->assertUnprocessable()->assertJsonValidationErrors(['0.bonus']);
    }

    public function test_doctor_commission_rules_are_saved(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('resources.doctors', 'web'));
        $this->actingAs($user);

        $payload = [[
            'name' => 'پزشک تست',
            'bonus' => 18,
            'commission_customer_scope' => 'new',
            'commission_after_materials' => true,
            'sales_bonus_enabled' => true,
            'sales_bonus_tiers' => [['sales_from' => 2000000, 'salary_addition' => 500000]],
            'salary' => 12000000,
            'hourly_rate' => 300000,
            'overtime_hourly_rate' => 450000,
            'shortage_hourly_deduction' => 200000,
            'absence_deduction' => 1000000,
            'allowed_shortage_hours' => 1.5,
        ]];

        $this->postJson('/api/doctors', $payload)->assertOk();
        $doctor = Doctor::firstOrFail();
        $this->assertSame(18.0, (float) $doctor->bonus);
        $this->assertSame('new', $doctor->commission_customer_scope);
        $this->assertTrue($doctor->commission_after_materials);
        $this->assertTrue($doctor->sales_bonus_enabled);
        $this->assertSame(2000000, (int) $doctor->sales_bonus_tiers[0]['sales_from']);
        $this->assertSame(300000, (int) $doctor->hourly_rate);
        $this->assertSame(1.5, (float) $doctor->allowed_shortage_hours);
    }
}
