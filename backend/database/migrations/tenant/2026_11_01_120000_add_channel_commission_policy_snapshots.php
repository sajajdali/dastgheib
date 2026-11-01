<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table): void {
            $table->boolean('exclude_staff_commission')->default(false)->after('exclude_doctor_commission');
            $table->boolean('exclude_referral_reward')->default(false)->after('exclude_staff_commission');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->boolean('doctor_commission_enabled')->default(true)->after('source');
            $table->boolean('staff_commission_enabled')->default(true)->after('doctor_commission_enabled');
            $table->boolean('referral_reward_enabled')->default(true)->after('staff_commission_enabled');
        });

        // Preserve the doctor exclusion already in effect for historical rows.
        // New channel changes must never recalculate those appointments.
        $blocked = DB::table('channels')->where('exclude_doctor_commission', true)->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))->filter()->unique()->all();
        if ($blocked === []) return;

        DB::table('appointments')->select('id', 'source')->orderBy('id')->chunkById(500, function ($appointments) use ($blocked): void {
            $ids = $appointments->filter(fn ($appointment) => in_array(mb_strtolower(trim((string) $appointment->source)), $blocked, true))
                ->pluck('id')->all();
            if ($ids !== []) DB::table('appointments')->whereIn('id', $ids)->update(['doctor_commission_enabled' => false]);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn(['doctor_commission_enabled', 'staff_commission_enabled', 'referral_reward_enabled']);
        });
        Schema::table('channels', function (Blueprint $table): void {
            $table->dropColumn(['exclude_staff_commission', 'exclude_referral_reward']);
        });
    }
};
