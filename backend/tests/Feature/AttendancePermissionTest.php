<?php

namespace Tests\Feature;

use App\Models\AttendanceMonth;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AttendancePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_attendance_permission_cannot_open_attendance_api(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/attendance/months')
            ->assertForbidden();
    }

    public function test_viewer_only_sees_the_attendance_record_connected_to_their_account(): void
    {
        $viewer = $this->userWithPermission('attendance.view');
        $ownStaff = Staff::create(['name' => 'کاربر تست', 'user_id' => $viewer->id]);
        $otherStaff = Staff::create(['name' => 'کاربر دیگر']);
        $ownMonth = $this->monthFor($ownStaff);
        $this->monthFor($otherStaff);

        $this->actingAs($viewer)
            ->getJson('/api/attendance/months')
            ->assertOk()
            ->assertJsonCount(1, 'months')
            ->assertJsonPath('months.0.id', $ownMonth->id);
    }

    public function test_viewer_cannot_change_clock_values_without_clock_permission(): void
    {
        $viewer = $this->userWithPermission('attendance.view');
        $staff = Staff::create(['name' => 'کاربر تست', 'user_id' => $viewer->id]);
        $month = $this->monthFor($staff);

        $this->actingAs($viewer)
            ->patchJson('/api/attendance/months/'.$month->id, [
                'days' => [['day' => 1, 'in' => '08:00', 'out' => null]],
            ])
            ->assertForbidden();
    }

    public function test_user_with_clock_permission_can_register_their_own_clock_value(): void
    {
        $user = $this->userWithPermission('attendance.view');
        $clockPermission = Permission::firstOrCreate(['name' => 'attendance.clock', 'guard_name' => 'web']);
        $user->givePermissionTo($clockPermission);
        $staff = Staff::create(['name' => 'کاربر ساعت‌زن', 'user_id' => $user->id]);
        $month = $this->monthFor($staff);

        $this->actingAs($user)
            ->patchJson('/api/attendance/months/'.$month->id, [
                'days' => [['day' => 1, 'in' => '08:00', 'out' => null]],
            ])
            ->assertOk()
            ->assertJsonPath('month.days.0.in', '08:00');
    }

    private function userWithPermission(string $permissionName): User
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        $user->givePermissionTo($permission);

        return $user;
    }

    private function monthFor(Staff $staff): AttendanceMonth
    {
        return AttendanceMonth::create([
            'resource_type' => 'staff',
            'resource_id' => $staff->id,
            'year' => 1405,
            'month' => 4,
            'name' => 'تیر ۱۴۰۵',
            'daily_hours' => 8,
            'days' => [['day' => 1, 'in' => null, 'out' => null]],
        ]);
    }
}
