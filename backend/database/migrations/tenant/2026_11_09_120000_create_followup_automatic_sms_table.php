<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * پیامک اتوماتیک از «پیگیری» فعال می‌شود، نه از خدمات نوبت. هر بار انتخاب یک سناریو برای یک مخاطب
 * یک enrollment است و هر مرحلهٔ آن یک ردیف زمان‌بندی‌شده.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('followup_automatic_sms', function (Blueprint $table) {
            $table->id();
            $table->string('enrollment_key', 40)->index();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->string('followup_row_key', 100)->nullable();
            $table->string('phone', 20)->index();
            $table->string('patient_name')->nullable();
            $table->foreignId('project_id')->nullable()->constrained('automatic_sms_projects')->nullOnDelete();
            $table->foreignId('scenario_id')->nullable()->constrained('automatic_sms_scenarios')->nullOnDelete();
            $table->foreignId('scenario_step_id')->nullable()->constrained('automatic_sms_scenario_steps')->nullOnDelete();
            $table->string('scenario_name')->nullable();
            $table->string('inventory_tag')->nullable();
            $table->string('sms_template')->nullable();
            $table->unsignedSmallInteger('days_after');
            $table->time('send_at');
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->string('status', 40)->default('scheduled')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('disabled_at')->nullable();
            $table->string('disabled_by_name')->nullable();
            $table->text('last_error')->nullable();
            $table->json('provider_response')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'followup_row_key'], 'followup_auto_sms_row_index');
        });

        // پیامک‌های زمان‌بندی‌شده از روی خدمات نوبت دیگر ارسال نمی‌شوند.
        if (Schema::hasTable('appointment_automatic_sms')) {
            DB::table('appointment_automatic_sms')
                ->whereIn('status', ['scheduled', 'waiting_completion', 'failed', 'sending'])
                ->update(['status' => 'cancelled', 'scheduled_at' => null, 'last_error' => 'پیامک اتوماتیک از پیگیری فعال می‌شود، نه از نوبت.', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('followup_automatic_sms');
    }
};
