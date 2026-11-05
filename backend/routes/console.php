<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\SendBirthdaySms;
use App\Jobs\SendLeadAlertSms;
use App\Services\ShsmsService;
use App\Models\Tenant;

Schedule::call(function () {
    Tenant::query()->get()->each(function (Tenant $tenant) {
        try {
            tenancy()->initialize($tenant);
            (new SendBirthdaySms)->handle(app(ShsmsService::class));
        } finally {
            tenancy()->end();
        }
    });
})->name('birthday-sms-all-tenants')->dailyAt('09:00')->timezone('Asia/Tehran')->withoutOverlapping();
// پیامک سرنخ باید با تنظیمات و دادهٔ هر کلینیک اجرا شود، نه دیتابیس مرکزی.
$leadAlertsForAllTenants = function (string $report): void {
    Tenant::query()->get()->each(function (Tenant $tenant) use ($report) {
        try {
            tenancy()->initialize($tenant);
            (new SendLeadAlertSms($report))->handle(app(ShsmsService::class));
        } catch (Throwable $exception) {
            report($exception);
        } finally {
            tenancy()->end();
        }
    });
};
Schedule::call(fn () => $leadAlertsForAllTenants('morning'))->name('lead-alert-sms-morning')->dailyAt('09:00')->timezone('Asia/Tehran')->withoutOverlapping();
Schedule::call(fn () => $leadAlertsForAllTenants('night'))->name('lead-alert-sms-night')->dailyAt('23:00')->timezone('Asia/Tehran')->withoutOverlapping();
Schedule::command('modules:expire-subscriptions')->hourly()->timezone('Asia/Tehran')->withoutOverlapping();
Schedule::command('subscriptions:expire')->hourly()->timezone('Asia/Tehran')->withoutOverlapping();
Schedule::command('storage:reconcile')->dailyAt('03:00')->timezone('Asia/Tehran')->withoutOverlapping();
Schedule::command('automatic-sms:send-due')->everyMinute()->timezone('Asia/Tehran')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
