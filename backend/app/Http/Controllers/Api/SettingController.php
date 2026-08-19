<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\Module;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use App\Services\CustomerLevelService;
use App\Support\ActivityLogger;

class SettingController extends Controller
{
    private function defaultClinicSchedule(): array
    {
        $days = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        return [
            'active_days' => ['saturday', 'monday', 'wednesday'],
            'interval_minutes' => 15,
            'day_times' => collect($days)
                ->mapWithKeys(fn (string $day) => [$day => ['start' => '09:00', 'end' => '17:00']])
                ->all(),
        ];
    }

    private function clinicScheduleSettings(): array
    {
        $raw = AppSetting::getByKey('clinic_schedule_settings', '{}');
        $stored = is_string($raw) ? json_decode($raw, true) : $raw;
        $defaults = $this->defaultClinicSchedule();

        if (! is_array($stored)) {
            return $defaults;
        }

        return [
            'active_days' => array_values(array_filter($stored['active_days'] ?? $defaults['active_days'])),
            'interval_minutes' => max(1, (int) ($stored['interval_minutes'] ?? $defaults['interval_minutes'])),
            'day_times' => array_replace_recursive($defaults['day_times'], is_array($stored['day_times'] ?? null) ? $stored['day_times'] : []),
        ];
    }

    private function projectOwnerId(): ?int
    {
        $ownerId = (int) AppSetting::getByKey('project_owner_user_id', 0);

        return $ownerId > 0 ? $ownerId : null;
    }

    // ۱. دریافت تمام تنظیمات صفحه به صورت یکجا برای لود اولیه فرانت‌آند
    public function index()
    {
        // گرفتن مقدار خام از دیتابیس
        $profileFieldsRaw = AppSetting::getByKey('profile_fields');
        
        // اگر مقدار رشته بود، آن‌قدر دیکودش می‌کنیم تا تبدیل به آرایه واقعی شود
        if (is_string($profileFieldsRaw)) {
            $profileFieldsData = json_decode($profileFieldsRaw, true);
            // اگر هنوز رشته بود (دو بار انکود شده بود)، یک بار دیگر دیکودش کن
            if (is_string($profileFieldsData)) {
                $profileFieldsData = json_decode($profileFieldsData, true);
            }
        } else {
            $profileFieldsData = $profileFieldsRaw;
        }

        // اگر دیتابیس کلا خالی بود، مقدار پیش‌فرض را بگذار
        $profileFieldsData = array_merge([
            'national_id' => false, 'marriage_date' => false, 'education' => false,
            'father_name' => false, 'second_phone' => false, 'address' => false,
            'city' => false,
        ], is_array($profileFieldsData) ? $profileFieldsData : []);

        $smsTemplatesRaw = AppSetting::getByKey('sms_templates', '[]');
        $smsTemplates = is_string($smsTemplatesRaw)
            ? json_decode($smsTemplatesRaw, true)
            : $smsTemplatesRaw;

        if (! is_array($smsTemplates) || count($smsTemplates) === 0) {
            $legacyTemplates = [
                ['id' => 'appointment', 'title' => 'یادآوری نوبت', 'category' => 'appointment', 'content' => AppSetting::getByKey('sms_appointment', ''), 'guide_text' => 'پارامترها: {name}، {date}، {time}، {doctor}، {clinic}', 'active' => true],
                ['id' => 'info', 'title' => 'اطلاعات مراجعه', 'category' => 'info', 'content' => AppSetting::getByKey('sms_info', ''), 'guide_text' => 'پارامترها: {name}، {date}، {time}، {doctor}، {consultant}، {clinic}', 'active' => true],
                ['id' => 'welcome', 'title' => 'خوش‌آمدگویی', 'category' => 'welcome', 'content' => AppSetting::getByKey('sms_welcome', ''), 'guide_text' => 'پارامترها: {name}، {clinic}', 'active' => true],
            ];

            $smsTemplates = array_values(array_filter(
                $legacyTemplates,
                fn (array $template) => $template['content'] !== ''
            ));
        }

        $completionTemplates = [
            ['id'=>'referral-credit','title'=>'واریز مبلغ برای معرف','category'=>'referral_credit','content'=>'','guide_text'=>'پارامترها: {name}، {amount}، {balance}','active'=>true],
            ['id'=>'treatment-care','title'=>'توصیه‌های بعد از درمان','category'=>'treatment_care','content'=>'','guide_text'=>'پارامترها: {name}، {link}','active'=>true],
            ['id'=>'payment-link','title'=>'لینک پرداخت','category'=>'payment_link','content'=>'','guide_text'=>'پارامترها: {name}، {link}، {amount}','active'=>true],
            ['id'=>'completion-welcome','title'=>'خوش‌آمدگویی بعد از درمان','category'=>'welcome','content'=>'','guide_text'=>'پارامترها: {name}، {clinic}','active'=>true],
        ];
        foreach ($completionTemplates as $template) {
            if (! collect($smsTemplates)->contains(fn ($item) => ($item['category'] ?? '') === $template['category'])) $smsTemplates[] = $template;
        }
        $templateGuides = [
            'appointment' => 'پارامترها: {name}، {date}، {time}، {doctor}، {clinic}',
            'info' => 'پارامترها: {name}، {date}، {time}، {doctor}، {consultant}، {clinic}',
            'welcome' => 'پارامترها: {name}، {clinic}',
            'referral_credit' => 'پارامترها: {name}، {amount}، {balance}',
            'treatment_care' => 'پارامترها: {name}، {link}',
            'payment_link' => 'پارامترها: {name}، {link}، {amount}',
        ];
        $smsTemplates = collect($smsTemplates)
            ->map(fn (array $template) => array_merge($template, [
                'guide_text' => $template['guide_text'] ?? ($templateGuides[$template['category'] ?? ''] ?? 'پارامترها را مطابق الگوی SHSMS وارد کنید.'),
            ]))
            ->values()
            ->all();
        $leadAlertsRaw = AppSetting::getByKey('sms_lead_alerts', '{}');
        $leadAlerts = is_string($leadAlertsRaw) ? json_decode($leadAlertsRaw, true) : $leadAlertsRaw;
        $leadAlerts = array_replace_recursive([
            'enabled' => false,
            'recipients' => [],
            'inventory_empty' => true,
            'active_tickets' => true,
            'daily_appointments' => true,
            'daily_financial' => true,
        ], is_array($leadAlerts) ? $leadAlerts : []);

        $projectOwnerId = $this->projectOwnerId();

        return response()->json([
            // تنظیمات داخلی
            'sms' => [
                'appointment' => AppSetting::getByKey('sms_appointment', ''),
                'info' => AppSetting::getByKey('sms_info', ''),
                'welcome' => AppSetting::getByKey('sms_welcome', ''),
            ],
            'sms_settings' => [
                'provider' => AppSetting::getByKey('sms_provider', 'shsms'),
                'templates' => $smsTemplates,
                'birthday' => [
                    'enabled' => AppSetting::getByKey('birthday_sms_enabled', '0') === '1',
                    'content' => AppSetting::getByKey('birthday_sms_content', ''),
                    'guide_text' => AppSetting::getByKey('birthday_sms_guide_text', 'پارامترها: {name}، {clinic}'),
                ],
                'lead_alerts' => $leadAlerts,
            ],
            
            // ارسال دیتای کاملا آرایه‌ای و تمیز به فرانت
            'profile_fields' => $profileFieldsData, 
            'patient_required_fields' => json_decode((string) AppSetting::getByKey('patient_required_fields', '{}'), true) ?: [],
            
            'company' => [
                'name' => AppSetting::getByKey('company_name', ''),
                'logo' => AppSetting::getByKey('company_logo', null),
                'about' => AppSetting::getByKey('company_about', ''),
            ],
            'customer_levels' => CustomerLevelService::settings(),
            'clinic_schedule' => $this->clinicScheduleSettings(),
            'appointment_columns' => [
                'payment_method' => AppSetting::getByKey('appointment_column_payment_method', '1') !== '0',
                'payment_account' => AppSetting::getByKey('appointment_column_payment_account', '1') !== '0',
                'payment_link' => AppSetting::getByKey('appointment_payment_link_enabled', '0') === '1',
                'best_staff' => AppSetting::getByKey('appointment_best_staff_enabled', '0') === '1',
            ],
            'attendance_enabled' => AppSetting::getByKey('attendance_enabled', '0') === '1',
            'users' => User::query()
                ->with('roles:id,name')
                ->orderBy('name')
                ->get(array_values(array_filter([
                    'id',
                    'name',
                    'email',
                    'mobile',
                    Schema::hasColumn('users', 'nickname') ? 'nickname' : null,
                    Schema::hasColumn('users', 'gender') ? 'gender' : null,
                    Schema::hasColumn('users', 'access_blocked') ? 'access_blocked' : null,
                    'profile_photo_path',
                    'profile_thumbnail_path',
                ])))
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'user' => $user->name,
                    'nickname' => $user->nickname ?? '',
                    'mobile' => $user->mobile,
                    'gender' => $user->gender ?? '',
                    'access_blocked' => (bool) ($user->access_blocked ?? false),
                    'is_project_owner' => (int) $user->id === $projectOwnerId,
                    'role_ids' => $user->roles->pluck('id')->values(),
                    'profile_photo_path' => $user->profile_photo_path,
                    'profile_thumbnail_path' => $user->profile_thumbnail_path,
                    'profile_photo_url' => $user->profile_photo_url,
                    'profile_thumbnail_url' => $user->profile_thumbnail_url,
                    'avatar_url' => $user->avatar_url,
                ]),
            'roles' => Role::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(['id', 'name']),
            'access_sections' => $this->getAccessSections()
        ]);
    }

    public function fetchSettings()
    {
        $settings = \App\Models\AppSetting::pluck('value', 'key');

        return response()->json([
            'sms' => [
                'appointment' => $settings->get('sms_appointment', ''),
                'info' => $settings->get('sms_info', ''),
                'welcome' => $settings->get('sms_welcome', ''),
            ],
            // تبدیل متن جی‌سان دیتابیس به آبجکت برای فرانت‌اِند
            'profile_fields' => json_decode($settings->get('profile_fields', '{}'), true),
            'company' => [
                'name' => $settings->get('company_name', ''),
                'about' => $settings->get('company_about', ''),
            ],
            'users' => \App\Models\User::select('name as user')->get(),
        ]);
    }

    // ۲. ذخیره کل تنظیمات داخلی (پیامک، پرونده، اطلاعات مجموعه)
    public function saveInternalSettings(Request $request)
    {
        $validatedUsers = $request->validate([
            'passwords' => ['sometimes', 'array'],
            'passwords.*.id' => ['nullable', 'integer', 'exists:users,id'],
            'passwords.*.user' => ['nullable', 'string', 'max:255'],
            'passwords.*.nickname' => ['nullable', 'string', 'max:255'],
            'passwords.*.mobile' => ['nullable', 'string', 'max:20'],
            'passwords.*.pass' => ['nullable', 'string', 'min:4'],
            'passwords.*.custom_password' => ['sometimes', 'boolean'],
            'passwords.*.gender' => ['nullable', 'string', 'in:male,female'],
            'passwords.*.access_blocked' => ['sometimes', 'boolean'],
            'passwords.*.is_project_owner' => ['sometimes', 'boolean'],
            'passwords.*.role_ids' => ['sometimes', 'array'],
            'passwords.*.role_ids.*' => ['integer', 'exists:roles,id'],
        ], [
            'passwords.array' => 'اطلاعات کاربران باید به‌صورت فهرست ارسال شود.',
            'passwords.*.id.integer' => 'شناسه کاربر معتبر نیست.',
            'passwords.*.id.exists' => 'کاربر انتخاب‌شده پیدا نشد.',
            'passwords.*.user.string' => 'نام کاربر باید متنی باشد.',
            'passwords.*.user.max' => 'نام کاربر نباید بیشتر از ۲۵۵ کاراکتر باشد.',
            'passwords.*.nickname.string' => 'نام مستعار باید متنی باشد.',
            'passwords.*.nickname.max' => 'نام مستعار نباید بیشتر از ۲۵۵ کاراکتر باشد.',
            'passwords.*.mobile.string' => 'شماره موبایل باید متنی باشد.',
            'passwords.*.mobile.max' => 'شماره موبایل نباید بیشتر از ۲۰ کاراکتر باشد.',
            'passwords.*.pass.string' => 'رمز عبور باید متنی باشد.',
            'passwords.*.pass.min' => 'رمز عبور باید حداقل ۴ کاراکتر باشد.',
            'passwords.*.custom_password.boolean' => 'وضعیت رمز عبور دلخواه معتبر نیست.',
            'passwords.*.gender.in' => 'جنسیت انتخاب‌شده معتبر نیست.',
            'passwords.*.access_blocked.boolean' => 'وضعیت بستن دسترسی معتبر نیست.',
            'passwords.*.is_project_owner.boolean' => 'وضعیت مالک پروژه معتبر نیست.',
            'passwords.*.role_ids.array' => 'نقش‌های کاربر باید به‌صورت فهرست انتخاب شوند.',
            'passwords.*.role_ids.*.integer' => 'نقش انتخاب‌شده معتبر نیست.',
            'passwords.*.role_ids.*.exists' => 'یکی از نقش‌های انتخاب‌شده پیدا نشد.',
        ], [
            'passwords.*.user' => 'نام کاربر',
            'passwords.*.nickname' => 'نام مستعار',
            'passwords.*.mobile' => 'شماره موبایل',
            'passwords.*.pass' => 'رمز عبور',
            'passwords.*.custom_password' => 'رمز عبور دلخواه',
            'passwords.*.gender' => 'جنسیت',
            'passwords.*.access_blocked' => 'بستن دسترسی',
            'passwords.*.is_project_owner' => 'مالک پروژه',
            'passwords.*.role_ids' => 'نقش‌های کاربر',
        ]);

        if ($request->has('customer_levels')) {
            $levels = $request->validate([
                'customer_levels.blue_min_period_amount' => ['required', 'numeric', 'min:0'],
                'customer_levels.blue_max_period_amount' => ['required', 'numeric', 'min:0'],
                'customer_levels.blue_visit_count' => ['required', 'integer', 'min:0'],
                'customer_levels.blue_visit_period_months' => ['required', 'integer', 'min:1', 'max:60'],
                'customer_levels.silver_min_period_amount' => ['required', 'numeric', 'min:0'],
                'customer_levels.silver_max_period_amount' => ['required', 'numeric', 'min:0'],
                'customer_levels.silver_visit_count' => ['required', 'integer', 'min:0'],
                'customer_levels.silver_visit_period_months' => ['required', 'integer', 'min:1', 'max:60'],
                'customer_levels.gold_min_period_amount' => ['required', 'numeric', 'min:0'],
                'customer_levels.gold_max_period_amount' => ['required', 'numeric', 'min:0'],
                'customer_levels.gold_visit_count' => ['required', 'integer', 'min:1'],
                'customer_levels.gold_visit_period_months' => ['required', 'integer', 'min:1', 'max:60'],
            ])['customer_levels'];
            AppSetting::updateOrCreate(
                ['key' => 'customer_level_settings'],
                ['value' => json_encode($levels, JSON_UNESCAPED_UNICODE)]
            );
        }
        // ۱. متون ساده (بدون دستکاری به صورت مستقیم ذخیره می‌شوند)
        if ($request->has('appointment_columns')) {
            AppSetting::updateOrCreate(
                ['key' => 'appointment_column_payment_method'],
                ['value' => $request->boolean('appointment_columns.payment_method') ? '1' : '0']
            );
            AppSetting::updateOrCreate(
                ['key' => 'appointment_column_payment_account'],
                ['value' => $request->boolean('appointment_columns.payment_account') ? '1' : '0']
            );
            AppSetting::updateOrCreate(
                ['key' => 'appointment_payment_link_enabled'],
                ['value' => $request->boolean('appointment_columns.payment_link') ? '1' : '0']
            );
            AppSetting::updateOrCreate(
                ['key' => 'appointment_best_staff_enabled'],
                ['value' => $request->boolean('appointment_columns.best_staff') ? '1' : '0']
            );
        }

        if ($request->has('clinic_schedule')) {
            $schedule = $request->input('clinic_schedule', []);
            $defaults = $this->defaultClinicSchedule();
            $dayKeys = array_keys($defaults['day_times']);
            $dayTimes = [];

            foreach ($dayKeys as $day) {
                $dayTimes[$day] = [
                    'start' => $schedule['day_times'][$day]['start'] ?? $defaults['day_times'][$day]['start'],
                    'end' => $schedule['day_times'][$day]['end'] ?? $defaults['day_times'][$day]['end'],
                ];
            }

            $payload = [
                'active_days' => array_values(array_intersect($schedule['active_days'] ?? [], $dayKeys)),
                'interval_minutes' => max(1, (int) ($schedule['interval_minutes'] ?? 15)),
                'day_times' => $dayTimes,
            ];

            AppSetting::updateOrCreate(
                ['key' => 'clinic_schedule_settings'],
                ['value' => json_encode($payload, JSON_UNESCAPED_UNICODE)]
            );
        }

        if ($request->has('sms')) {
            AppSetting::updateOrCreate(['key' => 'sms_appointment'], ['value' => $request->input('sms.appointment')]);
            AppSetting::updateOrCreate(['key' => 'sms_info'], ['value' => $request->input('sms.info')]);
            AppSetting::updateOrCreate(['key' => 'sms_welcome'], ['value' => $request->input('sms.welcome')]);
        }
        AppSetting::updateOrCreate(['key' => 'company_name'], ['value' => $request->input('company.name')]);
        AppSetting::updateOrCreate(['key' => 'company_about'], ['value' => $request->input('company.about')]);

        // ۲. فیلدهای پرونده (تبدیل به جیسون تمیز بدون کوتیشن بیرونی و با حفظ فونت فارسی)
        $profileFields = $request->input('profile_fields');
        if (is_array($profileFields)) {
            // تبدیل دستی به جیسون بدون تغییر فونت فارسی
            $profileFields = json_encode($profileFields, JSON_UNESCAPED_UNICODE); 
        }
        AppSetting::updateOrCreate(['key' => 'profile_fields'], ['value' => $profileFields]);
        AppSetting::updateOrCreate(['key' => 'patient_required_fields'], [
            'value' => json_encode($request->input('patient_required_fields', []), JSON_UNESCAPED_UNICODE),
        ]);

        // ۳. مدیریت کاربران
        DB::transaction(function () use ($validatedUsers) {
        $projectOwnerId = $this->projectOwnerId();
        $firstSavedUserId = null;
        foreach ($validatedUsers['passwords'] ?? [] as $index => $userData) {
            if (empty($userData['user'])) continue;

            $user = ! empty($userData['id'])
                ? User::findOrFail($userData['id'])
                : new User();

            // مالک پروژه فقط در زمان ایجاد نخستین کاربر تعیین می‌شود و از این بخش قابل تغییر نیست.
            if ($projectOwnerId && $user->exists && (int) $user->id === $projectOwnerId) {
                continue;
            }

            $name = trim($userData['user']);
            $duplicateNameUser = User::query()
                ->where('name', $name)
                ->when($user->exists, fn ($query) => $query->whereKeyNot($user->id))
                ->first();
            if ($duplicateNameUser) {
                $rowNumber = $index + 1;
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'passwords.'.$index.'.user' => 'نام کاربری «'.$name.'» در ردیف '.$rowNumber.' قبلاً برای کاربر «'.$duplicateNameUser->name.'» ثبت شده است.',
                ]);
            }

            $mobile = trim((string) ($userData['mobile'] ?? '')) ?: null;
            $duplicateMobileUser = $mobile
                ? User::query()
                    ->where('mobile', $mobile)
                    ->when($user->exists, fn ($query) => $query->whereKeyNot($user->id))
                    ->first()
                : null;
            if ($duplicateMobileUser) {
                $rowNumber = $index + 1;
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'passwords.'.$index.'.mobile' => 'شماره موبایل '.$mobile.' در ردیف '.$rowNumber.' (کاربر «'.$name.'») قبلاً برای «'.$duplicateMobileUser->name.'» ثبت شده است.',
                ]);
            }

            $usesCustomPassword = (bool) ($userData['custom_password'] ?? false);
            if ($usesCustomPassword && ! $user->exists && empty($userData['pass'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['passwords.'.$index.'.pass' => 'برای کاربر جدید رمز عبور دلخواه وارد کنید.']);
            }
            if (! $user->exists && ! $usesCustomPassword && empty($mobile)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['passwords.'.$index.'.mobile' => 'برای استفاده از شماره موبایل به‌عنوان رمز عبور، شماره موبایل را وارد کنید.']);
            }

            $shouldBlockAccess = (bool) ($userData['access_blocked'] ?? false);
            if ($shouldBlockAccess && $projectOwnerId && (int) $user->id === $projectOwnerId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'passwords.'.$index.'.access_blocked' => 'نمی‌توانید دسترسی مالک پروژه را ببندید.',
                ]);
            }
            if ($shouldBlockAccess && $user->exists && $requestUserId = request()->user()?->id) {
                if ((int) $user->id === (int) $requestUserId) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'passwords.'.$index.'.access_blocked' => 'نمی‌توانید دسترسی حساب کاربری خودتان را ببندید.',
                    ]);
                }
            }

            $user->name = $name;
            $user->email ??= 'user-'.Str::uuid().'@clinic.local';
            $user->mobile = $mobile;
            if (Schema::hasColumn('users', 'nickname')) {
                $user->nickname = trim((string) ($userData['nickname'] ?? '')) ?: null;
            }
            if (Schema::hasColumn('users', 'gender')) {
                $user->gender = $userData['gender'] ?? null;
            }
            $wasAccessBlocked = (bool) ($user->access_blocked ?? false);
            if (Schema::hasColumn('users', 'access_blocked')) {
                $user->access_blocked = $shouldBlockAccess;
            }
            $user->profile_photo_path = $userData['profile_photo_path'] ?? $user->profile_photo_path;
            $user->profile_thumbnail_path = $userData['profile_thumbnail_path'] ?? $user->profile_thumbnail_path;

            if (! $user->exists && ! $usesCustomPassword) {
                $user->password = Hash::make($mobile);
            } elseif ($usesCustomPassword && !empty($userData['pass'])) {
                $user->password = Hash::make($userData['pass']);
            }

            $user->save();

            $firstSavedUserId ??= $user->id;

            if ($shouldBlockAccess && ! $wasAccessBlocked && Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            $roleIds = collect($userData['role_ids'] ?? [])
                ->filter(fn ($id) => is_numeric($id))
                ->values();
            $roles = Role::query()->where('guard_name', 'web')->whereIn('id', $roleIds)->get();
            $user->syncRoles($roles);
        }

        $ownerId = $projectOwnerId ?? $firstSavedUserId;
        if ($ownerId) {
            $owner = User::findOrFail($ownerId);
            $owner->assignRole('مدیر سیستم');
            AppSetting::updateOrCreate(['key' => 'project_owner_user_id'], ['value' => (string) $owner->id]);
        }
        });

        return response()->json(['success' => true, 'message' => 'تنظیمات داخلی با موفقیت ذخیره شدند.']);
    }

    public function saveAttendanceStatus(Request $request)
    {
        abort_unless(
            $request->user()?->roles()->whereIn('name', ['مدیر کل', 'مدیر سیستم', 'super admin', 'super-admin'])->exists(),
            403,
            'فقط مدیر کل می‌تواند وضعیت حضور و غیاب را تغییر دهد.'
        );
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        AppSetting::updateOrCreate(
            ['key' => 'attendance_enabled'],
            ['value' => $validated['enabled'] ? '1' : '0']
        );

        return response()->json([
            'message' => $validated['enabled'] ? 'ثبت حضور و غیاب فعال شد.' : 'ثبت حضور و غیاب غیرفعال شد.',
            'enabled' => $validated['enabled'],
        ]);
    }

    public function uploadUserPhoto(Request $request, User $user)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'thumbnail' => 'required|image|mimes:webp|max:30|dimensions:width=50,height=50',
        ]);

        Storage::disk('public')->delete(array_filter([
            $user->profile_photo_path,
            $user->profile_thumbnail_path,
        ]));

        $directory = "users/{$user->id}/profile";
        $photoPath = $request->file('photo')->storeAs($directory, 'photo-'.Str::uuid().'.webp', 'public');
        $thumbnailPath = $request->file('thumbnail')->storeAs($directory, 'thumbnail-'.Str::uuid().'.webp', 'public');

        $user->update([
            'profile_photo_path' => $photoPath,
            'profile_thumbnail_path' => $thumbnailPath,
        ]);

        return response()->json([
            'message' => 'عکس کاربر با موفقیت ذخیره شد.',
            'user' => $user->fresh(),
        ]);
    }

    public function destroyUser(Request $request, User $user)
    {
        if ((int) $user->id === $this->projectOwnerId()) {
            return response()->json([
                'message' => 'نمی‌توانید حساب مالک پروژه را حذف کنید.',
            ], 422);
        }

        if ($request->user()?->id === $user->id) {
            return response()->json([
                'message' => 'نمی‌توانید حساب کاربری خودتان را حذف کنید.',
            ], 422);
        }

        if (User::query()->count() <= 1) {
            return response()->json([
                'message' => 'حداقل یک کاربر باید در سیستم باقی بماند.',
            ], 422);
        }

        $deletedUser = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'roles' => $user->roles()->pluck('name')->values()->all(),
        ];

        DB::transaction(function () use ($user, $deletedUser) {
            ActivityLogger::manual(
                'deleted',
                'تعریف کاربر',
                $user,
                $deletedUser,
                [],
                ['deleted_user_name' => $user->name]
            );

            Storage::disk('public')->delete(array_filter([
                $user->profile_photo_path,
                $user->profile_thumbnail_path,
            ]));

            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            $user->delete();
        });

        return response()->json(['message' => 'کاربر حذف شد. نوبت‌ها و اطلاعات ثبت‌شده توسط این کاربر حذف نشدند.']);
    }

    public function saveSmsSettings(Request $request)
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:shsms'],
            'templates' => ['present', 'array'],
            'templates.*.id' => ['nullable', 'string', 'max:100'],
            'templates.*.title' => ['required', 'string', 'max:100'],
            'templates.*.category' => ['required', 'in:general,appointment,info,welcome,referral_credit,treatment_care,payment_link'],
            'templates.*.content' => ['required', 'string', 'max:190'],
            'templates.*.guide_text' => ['nullable', 'string', 'max:2000'],
            'templates.*.active' => ['required', 'boolean'],
            'birthday.enabled' => ['required', 'boolean'],
            'birthday.content' => ['required_if:birthday.enabled,true', 'nullable', 'string', 'max:190'],
            'birthday.guide_text' => ['nullable', 'string', 'max:1000'],
            'lead_alerts.enabled' => ['required', 'boolean'],
            'lead_alerts.recipients' => ['present', 'array', 'max:20'],
            'lead_alerts.recipients.*' => ['required', 'regex:/^09\d{9}$/'],
            'lead_alerts.inventory_empty' => ['required', 'boolean'],
            'lead_alerts.active_tickets' => ['required', 'boolean'],
            'lead_alerts.daily_appointments' => ['required', 'boolean'],
            'lead_alerts.daily_financial' => ['required', 'boolean'],
        ], [
            'provider.in' => 'سامانه پیامکی انتخاب‌شده معتبر نیست.',
            'templates.*.title.required' => 'عنوان همه الگوهای پیامک الزامی است.',
            'templates.*.content.required' => 'نام الگوی SHSMS همه الگوها الزامی است.',
        ]);

        $templates = collect($validated['templates'])
            ->values()
            ->map(fn (array $template, int $index) => [
                'id' => $template['id'] ?: 'template-' . ($index + 1),
                'title' => trim($template['title']),
                'category' => $template['category'],
                'content' => trim($template['content']),
                'guide_text' => trim((string) ($template['guide_text'] ?? '')),
                'active' => (bool) $template['active'],
            ]);

        AppSetting::updateOrCreate(['key' => 'sms_provider'], ['value' => $validated['provider']]);
        AppSetting::updateOrCreate(['key' => 'birthday_sms_enabled'], ['value' => $validated['birthday']['enabled'] ? '1' : '0']);
        AppSetting::updateOrCreate(['key' => 'birthday_sms_content'], ['value' => trim((string) ($validated['birthday']['content'] ?? ''))]);
        AppSetting::updateOrCreate(['key' => 'birthday_sms_guide_text'], ['value' => trim((string) ($validated['birthday']['guide_text'] ?? ''))]);
        $leadAlerts = $validated['lead_alerts'];
        $leadAlerts['recipients'] = collect($leadAlerts['recipients'])->map(fn ($number) => trim($number))->unique()->values()->all();
        AppSetting::updateOrCreate(['key' => 'sms_lead_alerts'], ['value' => json_encode($leadAlerts, JSON_UNESCAPED_UNICODE)]);
        AppSetting::updateOrCreate([
            'key' => 'sms_templates',
        ], [
            'value' => json_encode($templates->all(), JSON_UNESCAPED_UNICODE),
        ]);

        foreach (['appointment', 'info', 'welcome'] as $category) {
            $content = $templates
                ->first(fn (array $template) => $template['category'] === $category && $template['active'])['content'] ?? '';
            AppSetting::updateOrCreate(['key' => 'sms_' . $category], ['value' => $content]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تنظیمات و الگوهای پیامک با موفقیت ذخیره شدند.',
            'sms_settings' => [
                'provider' => $validated['provider'],
                'templates' => $templates->all(),
                'birthday' => $validated['birthday'],
                'lead_alerts' => $leadAlerts,
            ],
        ]);
    }

    // ۳. ذخیره یا آپدیت کاربران و دسترسی‌های آن‌ها
    public function saveAccessSettings(Request $request)
    {
        // مدیریت بخش تعریف کاربران (ساخت یا ویرایش رمز عبور)
        foreach ($request->input('passwords', []) as $userData) {
            if (empty($userData['user'])) continue;

            $user = ! empty($userData['id'])
                ? User::findOrFail($userData['id'])
                : User::firstOrNew(
                    ['name' => $userData['user']],
                    ['email' => $userData['user'] . '@system.com']
                );

            if ($this->projectOwnerId() && $user->exists && (int) $user->id === $this->projectOwnerId()) {
                continue;
            }

            $mobile = trim((string) ($userData['mobile'] ?? '')) ?: null;
            if ($mobile) {
                $user->mobile = $mobile;
            }

            if (! $user->exists && ! ($userData['custom_password'] ?? false) && $mobile) {
                $user->password = Hash::make($mobile);
            } elseif (($userData['custom_password'] ?? false) && !empty($userData['pass'])) {
                $user->password = Hash::make($userData['pass']);
            }

            $user->save();
        }

        // مدیریت بخش دسترسی پرسنل به تفکیک ماژول‌ها
        foreach ($request->input('access_sections', []) as $section) {
            $module = Module::firstOrCreate(['title' => $section['title']]);

            foreach ($section['people'] as $person) {
                if (empty($person['name'])) continue;

                $user = User::where('name', $person['name'])->first();
                if (!$user) continue;

                // گرفتن دسترسی‌ها از فرانت
                $permissions = $person['selected_permissions'] ?? [];

                // اگر فرانت آن را به صورت رشته فرستاده، بازش می‌کنیم تا آرایه خالص شود
                if (is_string($permissions)) {
                    $permissions = json_decode($permissions, true);
                }

                // تبدیل به جیسون تمیز نیتیو فارسی بدون دابل‌کوتیشن بیرونی اضافه
                $encodedPermissions = json_encode($permissions, JSON_UNESCAPED_UNICODE);

                UserPermission::updateOrCreate(
                    ['user_id' => $user->id, 'module_id' => $module->id],
                    ['permissions' => $encodedPermissions] 
                );
            }
        }

        return response()->json(['success' => true, 'message' => 'کاربران و سطوح دسترسی بروزرسانی شدند.']);
    }

    // متد داخلی برای پکیج کردن ساختار دسترسی‌ها مطابق ساختار فرانت شما
    private $getAccessSections;
    private function getAccessSections() {
        // این متد لیست ماژول‌ها و پرسنل متصل به آن‌ها را از دیتابیس جمع‌آوری کرده و آرایه ست شده در فرانت را تحویل می‌دهد.
        // جهت جلوگیری از شلوغی کد، دیتای اولیه ساختار فرانت شما را تعذیه می‌کند.
        return []; 
    }
}
