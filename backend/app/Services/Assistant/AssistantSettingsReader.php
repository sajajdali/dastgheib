<?php

namespace App\Services\Assistant;

use App\Http\Controllers\Api\SettingController;
use App\Models\User;
use InvalidArgumentException;

/**
 * خواندن تنظیمات کلینیک به زبان ساده تا دستیار به سؤال‌هایی مثل «سن در تشکیل پرونده فعاله؟» مستقیم جواب بدهد.
 * فقط بخش‌های بی‌خطر تنظیمات برگردانده می‌شود؛ کاربران، توکن‌ها و کلیدها هرگز.
 */
final class AssistantSettingsReader
{
    public const TOPICS = [
        'patient_form' => ['فرم تشکیل پرونده', 'patients.view|settings.view', 'settings_profile'],
        'appointment_columns' => ['ستون‌ها و قفل‌های نوبت‌دهی', 'appointments.view|settings.view', 'settings_appointment_columns'],
        'sms' => ['پیامک‌ها و هشدارهای مدیر', 'settings.view', 'settings_sms'],
        'birthday_sms' => ['پیامک تولد', 'settings.view', 'settings_birthday_sms'],
        'clinic_info' => ['اطلاعات مجموعه', 'settings.view', 'settings_company'],
        'clinic_schedule' => ['ساعت کاری کلینیک', 'settings.view', 'settings_clinic_schedule'],
        'customer_levels' => ['دسته‌بندی مشتری‌ها', 'settings.view', 'settings_customer_levels'],
        'wallet' => ['کیف پول مشتری و پاداش معرف', 'settings.view', 'settings_wallet'],
        'report_targets' => ['تارگت و ظرفیت گزارش', 'settings.view', 'settings_report_target'],
        'attendance' => ['حضور و غیاب', 'settings.view', 'settings'],
        'ai_assistant' => ['دستیار هوش مصنوعی', 'assistant.use', null],
        'my_access' => ['دسترسی‌های من', 'assistant.use', null],
        'roles' => ['نقش‌ها و دسترسی‌ها', 'settings.view|settings.users', 'settings_roles'],
        'storage' => ['فضای ذخیره‌سازی', 'settings.view', 'settings_storage'],
        'voip' => ['ارتباط با VoIP', 'settings.view', 'settings_voip'],
        'satisfaction' => ['رضایت‌سنجی', 'settings.view', 'satisfaction'],
        'patient_photos' => ['پردازش عکس بیماران', 'settings.view|photos.view', null],
        'automatic_sms' => ['سناریوهای پیامک خودکار', 'settings.view|settings.manage', 'automatic_sms'],
    ];

    private const PROFILE_FIELDS = [
        'national_id' => 'کد ملی', 'foreign_national_code' => 'کد اتباع', 'father_name' => 'نام پدر',
        'marital_status' => 'وضعیت تأهل', 'marriage_date' => 'تاریخ ازدواج', 'education' => 'تحصیلات',
        'weight' => 'وزن', 'second_phone' => 'شماره تماس دوم', 'address' => 'آدرس', 'city' => 'شهر',
    ];
    private const ALWAYS_FIELDS = ['نام', 'نام خانوادگی', 'شماره تماس', 'شماره پرونده', 'جنسیت', 'تاریخ تولد', 'وضعیت مالی', 'تیپ شخصیتی', 'سوابق پزشکی'];
    private const REQUIRABLE = [
        'phone' => 'شماره تماس', 'file_number' => 'شماره پرونده', 'gender' => 'جنسیت', 'birth_date' => 'تاریخ تولد',
        'financial_status' => 'وضعیت مالی', 'patient_history' => 'تیپ شخصیتی', 'medical_history' => 'سوابق پزشکی',
    ];
    private const DAYS = ['saturday' => 'شنبه', 'sunday' => 'یکشنبه', 'monday' => 'دوشنبه', 'tuesday' => 'سه‌شنبه', 'wednesday' => 'چهارشنبه', 'thursday' => 'پنجشنبه', 'friday' => 'جمعه'];

    public function read(User $user, string $topic): array
    {
        if (! isset(self::TOPICS[$topic])) throw new InvalidArgumentException('موضوع تنظیمات نامعتبر است.');
        [$title, $permission, $page] = self::TOPICS[$topic];
        if (! collect(explode('|', $permission))->contains(fn ($name) => $user->can($name))) {
            throw new InvalidArgumentException('اجازهٔ مشاهدهٔ این تنظیمات را ندارید.');
        }
        $fields = match ($topic) {
            'ai_assistant' => $this->assistant(),
            'my_access' => $this->access($user),
            'roles' => $this->roles(),
            'storage' => $this->storage(),
            'voip' => $this->voip(),
            'satisfaction' => $this->satisfaction(),
            'patient_photos' => $this->photos(),
            'automatic_sms' => $this->automaticSms(),
            default => $this->fromSettings($topic, app(SettingController::class)->index()->getData(true)),
        };
        $action = $page !== null && (new AssistantNavigationCatalog)->destination($user, $page)
            ? ['page' => $page, 'label' => 'باز کردن «'.$title.'» در تنظیمات'] : null;

        return [
            'model' => ['topic' => $topic, 'title' => $title, 'settings' => $fields],
            'presentation' => ['type' => 'record', 'title' => 'تنظیمات · '.$title, 'fields' => $fields, 'action' => $action],
        ];
    }

    private function fromSettings(string $topic, array $data): array
    {
        $on = fn ($value) => $value ? 'فعال' : 'غیرفعال';

        return match ($topic) {
            'patient_form' => [
                'فیلدهای همیشگی فرم' => implode('، ', self::ALWAYS_FIELDS),
                ...collect(self::PROFILE_FIELDS)->mapWithKeys(fn ($label, $key) => [$label => $on($data['profile_fields'][$key] ?? false)])->all(),
                'فیلدهای اجباری' => collect([...self::REQUIRABLE, ...self::PROFILE_FIELDS])
                    ->filter(fn ($label, $key) => ! empty($data['patient_required_fields'][$key]))->values()->implode('، ') ?: 'هیچ‌کدام',
                'سن' => 'فیلد جداگانه ندارد؛ از «تاریخ تولد» که همیشه در فرم هست خودکار محاسبه و در پروفایل بیمار نمایش داده می‌شود.',
            ],
            'appointment_columns' => [
                'ستون روش پرداخت' => $on($data['appointment_columns']['payment_method'] ?? true),
                'ستون حساب واریز' => $on($data['appointment_columns']['payment_account'] ?? true),
                'لینک پرداخت' => $on($data['appointment_columns']['payment_link'] ?? false),
                'نمایش بهترین پرسنل ماه' => $on($data['appointment_columns']['best_staff'] ?? false),
                'وضعیت عکس قبل و بعد' => $on($data['appointment_columns']['photo_status'] ?? true),
                'قفل شماره پرونده در نوبت' => $on($data['appointment_file_number_locked'] ?? true),
                'محدودیت دیدن شماره برای مشاور پیگیری' => $on($data['followup_consultant_phone_restricted'] ?? false),
            ],
            'sms' => [
                'سرویس پیامک' => (string) data_get($data, 'sms_settings.provider', ''),
                'حساب پیامک' => data_get($data, 'sms_settings.account_configured') ? 'تنظیم شده' : 'تنظیم نشده',
                'تعداد الگوهای پیامک' => count((array) data_get($data, 'sms_settings.templates', [])),
                'هشدارهای پیامکی مدیر' => $on(data_get($data, 'sms_settings.lead_alerts.enabled')),
                'هشدار اتمام موجودی انبار' => $on(data_get($data, 'sms_settings.lead_alerts.inventory_empty')),
                'هشدار تیکت فعال' => $on(data_get($data, 'sms_settings.lead_alerts.active_tickets')),
                'تعداد نوبت‌های امروز ساعت ۹' => $on(data_get($data, 'sms_settings.lead_alerts.daily_appointments')),
                'درآمد و سود روزانه' => $on(data_get($data, 'sms_settings.lead_alerts.daily_financial')),
            ],
            'birthday_sms' => [
                'ارسال خودکار پیامک تولد' => $on(data_get($data, 'birthday_sms_automation.enabled')),
                'زمان ارسال' => data_get($data, 'birthday_sms_automation.timing') === 'days_before'
                    ? data_get($data, 'birthday_sms_automation.days_before').' روز قبل از تولد' : 'همان روز تولد',
                'الگوی پیامک تولد' => $on(data_get($data, 'sms_settings.birthday.enabled')),
            ],
            'clinic_info' => [
                'نام مجموعه' => (string) data_get($data, 'company.name', ''),
                'آدرس' => (string) data_get($data, 'company.address', ''),
                'تلفن' => (string) data_get($data, 'company.phone', ''),
                'اینستاگرام' => (string) data_get($data, 'company.instagram_url', ''),
            ],
            'clinic_schedule' => [
                'تنظیم شده' => data_get($data, 'clinic_schedule.is_configured') ? 'بله' : 'خیر (پیش‌فرض)',
                'روزهای کاری' => collect((array) data_get($data, 'clinic_schedule.active_days', []))->map(fn ($day) => self::DAYS[$day] ?? $day)->implode('، '),
                'ساعت‌ها' => collect((array) data_get($data, 'clinic_schedule.day_times', []))
                    ->map(fn ($time, $day) => (self::DAYS[$day] ?? $day).': '.(is_array($time) ? implode(' تا ', array_filter([$time['start'] ?? null, $time['end'] ?? null])) : $time))->implode('؛ '),
            ],
            'customer_levels' => collect((array) ($data['customer_levels'] ?? []))
                ->mapWithKeys(fn ($value, $key) => [(string) $key => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)])->all(),
            'wallet' => [
                'کیف پول و پاداش معرف' => $on(data_get($data, 'wallet_settings.enabled')),
                'نوع پاداش پیش‌فرض' => data_get($data, 'wallet_settings.default_type') === 'percent' ? 'درصدی' : 'مبلغ ثابت',
                'مقدار پاداش پیش‌فرض' => (string) data_get($data, 'wallet_settings.default_value', 0),
                'مهلت استفاده (روز)' => (string) data_get($data, 'wallet_settings.expiry_days', 0),
            ],
            'report_targets' => [
                'سقف تارگت پرسنل (میلیون تومان)' => (string) ($data['report_staff_target'] ?? 0),
                'ظرفیت ماهانه' => (string) ($data['report_monthly_capacity'] ?? 0),
            ],
            'attendance' => ['حضور و غیاب' => $on($data['attendance_enabled'] ?? false)],
            default => [],
        };
    }

    /** نام فارسی مجوزها از config/roles.php */
    private function permissionLabels(): array
    {
        return collect(config('roles.groups', []))->flatMap(fn ($group) => collect($group['permissions'] ?? [])
            ->mapWithKeys(fn ($permission) => [$permission['name'] => $group['label'].' ← '.$permission['label']]))->all();
    }

    private function access(User $user): array
    {
        $labels = $this->permissionLabels();
        $granted = collect($labels)->filter(fn ($label, $name) => $user->can($name));

        return [
            'نقش' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->implode('، ') : '',
            'دسترسی‌های دارید' => $granted->values()->implode('؛ ') ?: 'هیچ',
            'دسترسی‌های ندارید' => collect($labels)->diffKeys($granted)->values()->implode('؛ ') ?: 'هیچ',
        ];
    }

    private function roles(): array
    {
        $labels = $this->permissionLabels();

        return \Spatie\Permission\Models\Role::query()->with(['permissions:id,name'])->orderBy('name')->get()
            ->mapWithKeys(function ($role) use ($labels) {
                $users = User::role($role->name)->pluck('name')->implode('، ');
                $permissions = $role->permissions->pluck('name')->map(fn ($name) => $labels[$name] ?? $name)->implode('؛ ');
                return [$role->name => 'کاربران: '.($users ?: '—').' | دسترسی‌ها: '.($permissions ?: '—')];
            })->all();
    }

    private function storage(): array
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        if (! $tenant) return [];
        $summary = app(\App\Services\TenantStorageService::class)->summary((string) $tenant->getTenantKey());
        $gb = fn ($bytes) => number_format($bytes / 1073741824, 2).' گیگابایت';

        return [
            'فضای کل' => $gb($summary['quota_bytes']), 'مصرف‌شده' => $gb($summary['used_bytes']),
            'باقی‌مانده' => $gb($summary['remaining_bytes']), 'درصد مصرف' => $summary['percentage'].'٪',
            'آخرین بررسی' => (string) ($summary['scanned_at'] ?? '—'),
        ];
    }

    private function voip(): array
    {
        $settings = \App\Support\VoipIntegration::settings();

        return [
            'ارتباط با VoIP' => $settings['enabled'] ? 'فعال' : 'غیرفعال',
            'باز شدن پرونده با تماس ورودی' => $settings['incoming_calls_enabled'] ? 'فعال' : 'غیرفعال',
            'توکن اتصال' => $settings['token'] !== '' ? 'ساخته شده' : 'ساخته نشده',
        ];
    }

    private function satisfaction(): array
    {
        $form = \App\Http\Controllers\Api\SatisfactionController::form();
        $integration = \App\Support\SatisfactionIntegration::settings();

        return [
            'عنوان فرم' => (string) ($form['title'] ?? ''),
            'تعداد سؤال‌ها' => count((array) ($form['questions'] ?? [])),
            'سؤال‌ها' => collect((array) ($form['questions'] ?? []))->map(fn ($q) => $q['title'] ?? $q['label'] ?? '')->filter()->implode('؛ '),
            'نمایش در سایت خارجی' => ! empty($integration['enabled']) ? 'فعال' : 'غیرفعال',
        ];
    }

    private function photos(): array
    {
        $settings = app(\App\Services\PatientPhotoSettings::class)->current();

        return [
            'بیشترین ضلع عکس (پیکسل)' => $settings['max_side'],
            'کیفیت فشرده‌سازی' => $settings['quality'].'٪',
            'حجم هدف هر عکس' => $settings['target_kb'].' کیلوبایت',
        ];
    }

    private function automaticSms(): array
    {
        $scenarios = \App\Models\AutomaticSmsScenario::query()->orderBy('name')->get(['name', 'inventory_tag', 'is_active']);

        return [
            'سناریوهای فعال' => $scenarios->where('is_active', true)->map(fn ($s) => $s->name.($s->inventory_tag ? ' ('.$s->inventory_tag.')' : ''))->implode('؛ ') ?: 'هیچ',
            'سناریوهای غیرفعال' => $scenarios->where('is_active', false)->pluck('name')->implode('؛ ') ?: 'هیچ',
        ];
    }

    private function assistant(): array
    {
        $settings = app(AssistantSettings::class)->current();

        return [
            'دستیار برای کلینیک' => $settings['enabled'] ? 'فعال' : 'غیرفعال',
            'مدل پیش‌فرض' => $settings['default_model'],
            'مدل‌های مجاز' => implode('، ', $settings['allowed_models']),
        ];
    }
}
