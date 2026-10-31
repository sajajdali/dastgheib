<?php

namespace App\Services\Assistant;

use App\Models\User;

final class AssistantNavigationCatalog
{
    private const PAGES = [
        'appointments' => ['نوبت‌دهی', 'appointments.view'],
        'followups' => ['پیگیری‌ها', 'followups.view'],
        'service_followups' => ['پیگیری‌های خدمت', 'followups.view'],
        'reports' => ['گزارش‌ها', 'reports.view'],
        'report_builder' => ['گزارش‌ساز', 'reports.view'],
        'activity_logs' => ['گزارش فعالیت‌ها', 'activity_logs.view'],
        'patients' => ['پرونده‌ها', 'patients.view'],
        'inventory' => ['انبار', 'inventory.view'],
        'expenses' => ['هزینه‌ها', 'bills.view'],
        'photos' => ['گالری عکس‌ها', 'photos.view'],
        'beauty' => ['زیبایار', 'beauty.view'],
        'services' => ['خدمت‌یاب', 'services.view'],
        'tickets' => ['تیکت‌ها', 'tickets.view'],
        'attendance' => ['حضور و غیاب', 'attendance.view'],
        'payroll' => ['حقوق و تسویه', 'payroll.view'],
        'settings' => ['تنظیمات ← تنظیمات داخلی', 'settings.view'],
        'satisfaction' => ['تنظیمات ← رضایت‌مندی', 'settings.view'],
        'automation' => ['اتوماسیون', 'settings.view'],
        'automatic_sms' => ['سناریوهای پیامک خودکار', 'settings.view'],
        'store' => ['فروشگاه و اشتراک', 'settings.view'],
        'settings_profile' => ['تنظیمات ← تنظیمات داخلی ← پرونده', 'settings.view'],
        'settings_company' => ['تنظیمات ← تنظیمات داخلی ← اطلاعات مجموعه', 'settings.view'],
        'settings_appointment_columns' => ['تنظیمات ← تنظیمات داخلی ← ستون‌های نوبت‌دهی', 'settings.view'],
        'settings_birthday_sms' => ['تنظیمات ← تنظیمات داخلی ← پیامک تولد', 'settings.view'],
        'settings_wallet' => ['تنظیمات ← تنظیمات داخلی ← کیف پول مشتری و پاداش معرف', 'settings.view'],
        'settings_report_target' => ['تنظیمات ← تنظیمات داخلی ← تنظیمات گزارش', 'settings.view'],
        'settings_clinic_schedule' => ['تنظیمات ← تنظیمات داخلی ← ساعت کاری کلینیک', 'settings.view'],
        'settings_password' => ['تنظیمات ← تنظیمات داخلی ← رمز عبور', 'settings.view'],
        'settings_customer_levels' => ['تنظیمات ← تنظیمات داخلی ← دسته‌بندی مشتری‌ها', 'settings.view'],
        'settings_sms' => ['تنظیمات ← پیامک', 'settings.view'],
        'settings_payments' => ['تنظیمات ← پرداخت', 'settings.view'],
        'settings_voip' => ['تنظیمات ← ارتباط با VoIP', 'settings.view'],
        'settings_roles' => ['تنظیمات ← نقش‌ها', 'settings.view'],
        'settings_storage' => ['تنظیمات ← فضای ذخیره‌سازی', 'settings.view'],
        'settings_resources' => ['تنظیمات ← منابع ← پزشک', 'resources.view'],
        'settings_doctors' => ['تنظیمات ← منابع ← پزشک؛ افزودن و مدیریت پزشکان', 'resources.view'],
        'settings_staff' => ['تنظیمات ← منابع ← پرسنل؛ افزودن و مدیریت پرسنل', 'resources.view'],
        'settings_channels' => ['تنظیمات ← منابع ← کانال‌ها؛ افزودن و مدیریت کانال‌ها', 'resources.view'],
    ];

    public function allowed(User $user): array
    {
        return array_filter(self::PAGES, fn (array $item, string $page) => $user->can($item[1])
            && (! str_starts_with($page, 'settings') || $user->can('settings.view')), ARRAY_FILTER_USE_BOTH);
    }

    public function destination(User $user, string $page): ?array
    {
        return $this->allowed($user)[$page] ?? null;
    }

    public function description(User $user): string
    {
        return collect($this->allowed($user))->map(fn ($item, $key) => $key.' = '.$item[0])->implode('؛ ');
    }
}
