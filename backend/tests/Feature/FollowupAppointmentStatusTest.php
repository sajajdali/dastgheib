<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FollowupAppointmentStatusTest extends TestCase
{
    use RefreshDatabase;

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

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('month');
            $table->integer('day_num');
            $table->string('time')->nullable();
            $table->string('lastname')->nullable();
            $table->string('phone')->nullable();
            $table->string('done')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_reports_done_status_by_appointment_id_and_by_phone_and_day_for_old_rows(): void
    {
        $this->actingAs(User::factory()->create());

        $doneId = DB::table('appointments')->insertGetId([
            'month' => '1405-08', 'day_num' => 20, 'time' => '11:00', 'phone' => '09120000001',
            'done' => 'انجام شد', 'completed_at' => '2026-11-11 11:40:00',
        ]);
        $pendingId = DB::table('appointments')->insertGetId([
            'month' => '1405-08', 'day_num' => 21, 'time' => '10:00', 'phone' => '09120000002', 'done' => null,
        ]);
        // ردیف قدیمی بدون شناسهٔ نوبت: همان روز و همان شماره (با ارقام فارسی و +98).
        $legacyId = DB::table('appointments')->insertGetId([
            'month' => '1405-08', 'day_num' => 22, 'time' => '12:30', 'phone' => '+989120000003',
            'done' => 'انجام شد', 'completed_at' => '2026-11-13 13:00:00',
        ]);
        DB::table('appointments')->insert([
            'month' => '1405-08', 'day_num' => 22, 'time' => '12:30', 'phone' => '09129999999', 'done' => null,
        ]);

        $this->postJson('/api/followups/appointment-statuses', ['items' => [
            ['key' => 'row-done', 'appointment_id' => $doneId],
            ['key' => 'row-pending', 'appointment_id' => $pendingId],
            ['key' => 'row-legacy', 'phone' => '۰۹۱۲۰۰۰۰۰۰۳', 'month' => '1405-08', 'day_num' => 22, 'time' => '12:30'],
            ['key' => 'row-missing', 'appointment_id' => 999999],
            ['key' => 'row-other-day', 'phone' => '09120000003', 'month' => '1405-08', 'day_num' => 23],
        ]])
            ->assertOk()
            ->assertJsonPath('statuses.row-done.done', true)
            ->assertJsonPath('statuses.row-done.appointment_id', $doneId)
            ->assertJsonPath('statuses.row-pending.done', false)
            ->assertJsonPath('statuses.row-legacy.done', true)
            ->assertJsonPath('statuses.row-legacy.appointment_id', $legacyId)
            ->assertJsonMissingPath('statuses.row-missing')
            ->assertJsonMissingPath('statuses.row-other-day');
    }
}
