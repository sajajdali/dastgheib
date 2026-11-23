<?php

namespace Tests\Unit;

use App\Jobs\SendAppointmentReminderSms;
use App\Models\AppSetting;
use App\Models\Appointment;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use App\Services\ShsmsService;
use App\Support\AppointmentStatuses;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SendAppointmentReminderSmsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('month')->nullable();
            $table->unsignedInteger('day_num')->nullable();
            $table->string('time')->nullable();
            $table->string('lastname')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('appointment_reminder_sms_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->unique();
            $table->string('recipient', 30);
            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    public function test_sends_only_for_scheduled_status_once(): void
    {
        $tomorrow = now('Asia/Tehran')->addDay();
        $jalali = (new JalaliMonthWindow())->date($tomorrow);
        $month = substr($jalali, 0, 7);
        $day = (int) substr($jalali, 8, 2);

        AppSetting::updateOrCreate(
            ['key' => 'sms_templates'],
            ['value' => json_encode([['category' => 'appointment_reminder', 'content' => 'reminder_tpl', 'active' => false]])]
        );

        $scheduled = Appointment::create([
            'month' => $month,
            'day_num' => $day,
            'time' => '14:30',
            'lastname' => 'آزمایشی',
            'phone' => '09121234567',
            'status' => AppointmentStatuses::SCHEDULED,
        ]);
        Appointment::create([
            'month' => $month,
            'day_num' => $day,
            'time' => '15:00',
            'lastname' => 'کنسل',
            'phone' => '09121111111',
            'status' => AppointmentStatuses::CANCELLED,
        ]);
        Appointment::create([
            'month' => $month,
            'day_num' => $day,
            'time' => '16:00',
            'lastname' => 'آمده',
            'phone' => '09122222222',
            'status' => AppointmentStatuses::ARRIVED,
        ]);

        $sms = Mockery::mock(ShsmsService::class);
        $sms->shouldReceive('sendTemplate')->once()->withArgs(function ($phone, $template, $params) {
            return $phone === '09121234567'
                && $template === 'reminder_tpl'
                && $params[0] === 'آزمایشی'
                && $params[2] === '14:30';
        });

        (new SendAppointmentReminderSms)->handle($sms);
        (new SendAppointmentReminderSms)->handle($sms);

        $this->assertDatabaseCount('appointment_reminder_sms_logs', 1);
        $this->assertDatabaseHas('appointment_reminder_sms_logs', [
            'appointment_id' => $scheduled->id,
            'recipient' => '09121234567',
        ]);
    }

    public function test_skips_when_status_changed_before_send(): void
    {
        $tomorrow = now('Asia/Tehran')->addDay();
        $jalali = (new JalaliMonthWindow())->date($tomorrow);
        AppSetting::updateOrCreate(
            ['key' => 'sms_templates'],
            ['value' => json_encode([['category' => 'appointment_reminder', 'content' => 'reminder_tpl']])]
        );
        Appointment::create([
            'month' => substr($jalali, 0, 7),
            'day_num' => (int) substr($jalali, 8, 2),
            'time' => '11:00',
            'lastname' => 'جابه‌جا',
            'phone' => '09123334444',
            'status' => AppointmentStatuses::NO_ANSWER,
        ]);

        $sms = Mockery::mock(ShsmsService::class);
        $sms->shouldNotReceive('sendTemplate');
        (new SendAppointmentReminderSms)->handle($sms);
        $this->assertDatabaseCount('appointment_reminder_sms_logs', 0);
    }
}
