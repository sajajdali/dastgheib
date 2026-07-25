<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\SendBirthdaySms;
use App\Jobs\SendLeadAlertSms;
use App\Services\ShsmsService;

Schedule::job(new SendBirthdaySms)->dailyAt('09:00')->timezone('Asia/Tehran')->withoutOverlapping();
Schedule::call(fn () => (new SendLeadAlertSms('morning'))->handle(app(ShsmsService::class)))->name('lead-alert-sms-morning')->dailyAt('09:00')->timezone('Asia/Tehran')->withoutOverlapping();
Schedule::call(fn () => (new SendLeadAlertSms('night'))->handle(app(ShsmsService::class)))->name('lead-alert-sms-night')->dailyAt('23:00')->timezone('Asia/Tehran')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
