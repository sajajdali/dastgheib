<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
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
}