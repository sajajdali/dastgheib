<?php

namespace App\Services\Assistant;

use App\Models\User;

final class AssistantCapabilityCatalog
{
    private const MODULES = [
        'patients' => ['پرونده‌ها، مشخصات مراجع، سوابق، کیف پول و رسانه‌ها', 'patients.view', 'patients'],
        'appointments' => ['نوبت‌دهی، وضعیت مراجعه، پزشک، خدمات و پرداخت نوبت', 'appointments.view', 'booking'],
        'followups' => ['پیگیری‌های عمومی، کمپین‌ها و پیگیری خدمت', 'followups.view', 'followups'],
        'reports' => ['گزارش‌های مالی، عملکرد و گزارش‌ساز پرونده‌ها و نوبت‌ها', 'reports.view', 'report'],
        'inventory' => ['کالاها، خدمات انبار و موجودی', 'inventory.view', 'inventory'],
        'beauty' => ['زیبایار، طرح درمان و عکس‌های مرتبط', 'beauty.view', 'beauty'],
        'photos' => ['عکس‌ها و مقایسه قبل و بعد', 'photos.view', 'gallery'],
        'services' => ['تعریف خدمات و خدمت‌یاب', 'services.view', 'finder'],
        'resources' => ['پزشکان، پرسنل و کانال‌های جذب', 'resources.view', 'resources'],
        'tickets' => ['تیکت‌ها و وضعیت رسیدگی', 'tickets.view', 'tickets'],
        'bills' => ['هزینه‌ها و صورتحساب‌ها', 'bills.view', 'bills'],
        'attendance' => ['حضور و غیاب', 'attendance.view', 'attendance'],
        'payroll' => ['حقوق، پورسانت و تسویه', 'payroll.view', 'payroll'],
        'settings' => ['تنظیمات، کاربران و دسترسی‌ها', 'settings.view', 'settings'],
        'satisfaction' => ['رضایت‌سنجی مراجعان', 'settings.view', 'satisfaction'],
        'automation' => ['سناریوهای خودکار و پیامک', 'settings.view', 'automation'],
    ];

    public function instructions(User $user): string
    {
        $modules = collect(self::MODULES)
            ->filter(fn (array $module) => $user->can($module[1]) && $this->featureEnabled($module[2]))
            ->map(fn (array $module) => $module[0])->implode('؛ ');

        return "نقشهٔ امکانات قابل مشاهدهٔ این کاربر: {$modules}. "
            .'مراجع/بیمار/پرونده در جدول patients است؛ کاربر نرم‌افزار در users است و این دو یکی نیستند. '
            .'نوبت در appointments ثبت می‌شود؛ نام پزشک در appointments.doctor به صورت متن ذخیره شده و پرونده با file_number و در نبود آن با phone به نوبت مرتبط می‌شود. '
            .'پیگیری خدمت با appointment_id و inventory_id به نوبت و خدمت وصل است؛ گردش انبار با inventory_id به کالا وصل است؛ هزینهٔ کمپین با campaign_id به کمپین وصل است. '
            .'پزشک و پرسنل می‌توانند با user_id به کاربر نرم‌افزار مرتبط باشند. حضور و غیاب با resource_type و resource_id به نیروی انسانی مربوط است. یادداشت زیبایار با patient_id و patient_media_id به پرونده و رسانه وصل می‌شود. '
            .'«بیماران پزشک» یعنی پرونده‌های یکتایی که در بازهٔ خواسته‌شده نوبت منتسب به آن پزشک دارند؛ تعداد نوبت‌ها با تعداد بیماران متفاوت است. '
            .'گزارش‌ساز دو منبع patients و appointments دارد و ستون‌ها و فیلترهای مجاز آن در سمت سرور تعریف می‌شوند. '
            .'برای دادهٔ زنده فقط ابزارهای ارائه‌شده را اجرا کن. معرفی یک ماژول به معنی توانایی خواندن همهٔ داده‌های آن نیست؛ اگر ابزار مربوط موجود نیست، صریح بگو. '
            .'برای شخص هم‌نام، تطبیق پزشک یا پروندهٔ مبهم، نتیجه را حدس نزن؛ گزینه‌ها را نشان بده. '
            .'دکمهٔ باز کردن پرونده یا گزارش را از خروجی ساختاریافتهٔ ابزار بگیر؛ آدرس صفحه را خودت نساز.';
    }

    private function featureEnabled(string $feature): bool
    {
        if (request()->getHost() === 'clinic1.localhost') return true;
        $modules = function_exists('tenant') ? tenant()?->module_ids : null;
        if (! is_array($modules)) return true;
        $aliases = ['appointments'=>'booking', 'appointment'=>'booking', 'time'=>'booking', 'chat'=>'patients',
            'tasks'=>'followups', 'campaign'=>'automation', 'aiReport'=>'beauty', 'staffEval'=>'resources'];
        $enabled = array_map(fn ($item) => $aliases[$item] ?? $item, $modules);

        return in_array($feature, $enabled, true);
    }
}
