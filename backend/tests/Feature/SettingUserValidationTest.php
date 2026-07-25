<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingUserValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_can_be_created_with_a_four_character_password(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->postJson('/api/settings/internal', [
            'passwords' => [[
                'user' => 'کاربر چهار رقمی',
                'mobile' => '09120000044',
                'pass' => '1234',
                'role_ids' => [],
            ]],
        ])->assertOk();

        $createdUser = User::query()->where('mobile', '09120000044')->firstOrFail();
        $this->assertTrue(Hash::check('1234', $createdUser->password));
    }

    public function test_a_short_password_returns_a_persian_validation_message(): void
    {
        $manager = $this->manager();

        $response = $this->actingAs($manager)->postJson('/api/settings/internal', [
            'passwords' => [[
                'user' => 'کاربر رمز کوتاه',
                'mobile' => '09120000033',
                'pass' => '123',
                'role_ids' => [],
            ]],
        ])->assertUnprocessable();

        $this->assertSame(
            'رمز عبور باید حداقل ۴ کاراکتر باشد.',
            $response->json('errors')['passwords.0.pass'][0]
        );
    }

    public function test_duplicate_mobile_message_identifies_the_row_and_existing_user(): void
    {
        $manager = $this->manager();
        User::factory()->create([
            'name' => 'صاحب شماره',
            'mobile' => '09121112233',
        ]);

        $response = $this->actingAs($manager)->postJson('/api/settings/internal', [
            'passwords' => [[
                'user' => 'کاربر جدید',
                'mobile' => '09121112233',
                'pass' => '1234',
                'role_ids' => [],
            ]],
        ])->assertUnprocessable();

        $this->assertSame(
            'شماره موبایل 09121112233 در ردیف 1 (کاربر «کاربر جدید») قبلاً برای «صاحب شماره» ثبت شده است.',
            $response->json('errors')['passwords.0.mobile'][0]
        );
    }

    private function manager(): User
    {
        $manager = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'مدیر سیستم', 'guard_name' => 'web']);
        $manager->assignRole($role);

        return $manager;
    }
}
