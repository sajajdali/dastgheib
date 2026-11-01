<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChannelCommissionMigrationTest extends TestCase
{
    public function test_existing_appointments_keep_the_previous_doctor_rule(): void
    {
        Schema::create('channels', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('exclude_doctor_commission')->default(false);
        });
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->string('source')->nullable();
        });
        DB::table('channels')->insert(['name' => 'تبلیغات ویژه', 'exclude_doctor_commission' => true]);
        DB::table('appointments')->insert([
            ['source' => ' تبلیغات ویژه '],
            ['source' => 'معرفی دوستان'],
        ]);

        (require database_path('migrations/tenant/2026_11_01_120000_add_channel_commission_policy_snapshots.php'))->up();

        $this->assertDatabaseHas('appointments', [
            'source' => ' تبلیغات ویژه ',
            'doctor_commission_enabled' => false,
            'staff_commission_enabled' => true,
            'referral_reward_enabled' => true,
        ]);
        $this->assertDatabaseHas('appointments', [
            'source' => 'معرفی دوستان',
            'doctor_commission_enabled' => true,
            'staff_commission_enabled' => true,
            'referral_reward_enabled' => true,
        ]);
        $this->assertDatabaseCount('appointments', 2);
    }
}
