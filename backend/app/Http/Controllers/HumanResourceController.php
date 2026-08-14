<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Channel;
use App\Models\Doctor;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HumanResourceController extends Controller
{
    private function paymentDefaults(): array
    {
        return [
            'methods' => ['کارتخوان', 'کارت به کارت', 'شبا'],
            'accounts' => ['حساب اصلی'],
            'service_categories' => ['زیبایی', 'درمانی', 'لیزر', 'پوست و مو'],
            'service_types' => ['خدمت اصلی', 'خدمت جانبی', 'مشاوره'],
            'service_statuses' => ['فعال', 'غیرفعال', 'نیازمند بررسی'],
        ];
    }

    private function serviceTagDefaults(): array
    {
        return ['بوتاکس پیشانی', 'بوتاکس دور چشم', 'ژل لب', 'فرم‌دهی لب', 'لیزر صورت', 'لیزر فول فیس'];
    }

    private function paymentOptions(): array
    {
        $defaults = $this->paymentDefaults();
        $read = fn (string $key) => json_decode((string) AppSetting::getByKey($key, '[]'), true);
        $methods = $read('payment_methods');
        $accounts = $read('payment_accounts');
        $serviceCategories = $read('payment_service_categories');
        $serviceTypes = $read('payment_service_types');
        $serviceStatuses = $read('payment_service_statuses');

        return [
            'methods' => is_array($methods) && $methods !== [] ? array_values($methods) : $defaults['methods'],
            'accounts' => is_array($accounts) && $accounts !== [] ? array_values($accounts) : $defaults['accounts'],
            'service_categories' => is_array($serviceCategories) && $serviceCategories !== [] ? array_values($serviceCategories) : $defaults['service_categories'],
            'service_types' => is_array($serviceTypes) && $serviceTypes !== [] ? array_values($serviceTypes) : $defaults['service_types'],
            'service_statuses' => is_array($serviceStatuses) && $serviceStatuses !== [] ? array_values($serviceStatuses) : $defaults['service_statuses'],
        ];
    }

    private function normalizePaymentOptionList(Request $request, string $key): array
    {
        return collect($request->input($key, []))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function getPaymentOptions()
    {
        return response()->json($this->paymentOptions());
    }

    public function savePaymentOptions(Request $request)
    {
        $methods = $this->normalizePaymentOptionList($request, 'methods');
        $accounts = $this->normalizePaymentOptionList($request, 'accounts');
        $serviceCategories = $this->normalizePaymentOptionList($request, 'service_categories');
        $serviceTypes = $this->normalizePaymentOptionList($request, 'service_types');
        $serviceStatuses = $this->normalizePaymentOptionList($request, 'service_statuses');

        $defaults = $this->paymentDefaults();
        $settings = [
            'payment_methods' => $methods !== [] ? $methods : $defaults['methods'],
            'payment_accounts' => $accounts !== [] ? $accounts : $defaults['accounts'],
            'payment_service_categories' => $serviceCategories !== [] ? $serviceCategories : $defaults['service_categories'],
            'payment_service_types' => $serviceTypes !== [] ? $serviceTypes : $defaults['service_types'],
            'payment_service_statuses' => $serviceStatuses !== [] ? $serviceStatuses : $defaults['service_statuses'],
        ];

        foreach ($settings as $key => $value) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]
            );
        }

        return response()->json($this->paymentOptions());
    }

    public function getServiceTags()
    {
        return response()->json([
            'tags' => $this->serviceTags(),
            'tag_definitions' => $this->serviceTagDefinitions(),
        ]);
    }

    public function saveServiceTags(Request $request)
    {
        $tags = collect($request->input('tags', []))
            ->map(function ($tag) {
                $name = trim((string) (is_array($tag) ? ($tag['name'] ?? '') : $tag));
                $smsTemplate = trim((string) (is_array($tag) ? ($tag['sms_template'] ?? '') : ''));
                return $name === '' ? null : ['name' => $name, 'sms_template' => $smsTemplate];
            })
            ->filter()
            ->unique('name')
            ->values()
            ->all();

        AppSetting::updateOrCreate(
            ['key' => 'service_tags'],
            ['value' => json_encode($tags !== [] ? $tags : $this->serviceTagDefaults(), JSON_UNESCAPED_UNICODE)]
        );

        return response()->json([
            'tags' => $this->serviceTags(),
            'tag_definitions' => $this->serviceTagDefinitions(),
        ]);
    }

    public function serviceTags(): array
    {
        return collect($this->serviceTagDefinitions())->pluck('name')->all();
    }

    public function serviceTagDefinitions(): array
    {
        $stored = json_decode((string) AppSetting::getByKey('service_tags', '[]'), true);

        $definitions = collect(is_array($stored) ? $stored : [])
            ->map(function ($tag) {
                $name = trim((string) (is_array($tag) ? ($tag['name'] ?? '') : $tag));
                $smsTemplate = trim((string) (is_array($tag) ? ($tag['sms_template'] ?? '') : ''));
                return $name === '' ? null : ['name' => $name, 'sms_template' => $smsTemplate];
            })
            ->filter()
            ->unique('name')
            ->values()
            ->all();

        return $definitions !== []
            ? $definitions
            : collect($this->serviceTagDefaults())->map(fn ($name) => ['name' => $name, 'sms_template' => ''])->all();
    }

    public function getDoctors()
    {
        return response()->json(Doctor::query()->orderBy('name')->get());
    }

    public function getChannels()
    {
        return response()->json(Channel::all());
    }

    public function getStaff()
    {
        return response()->json(Staff::query()->orderBy('name')->get());
    }

    public function saveDoctors(Request $request)
    {
        $validated = $request->validate([
            '*' => ['array'],
            '*.id' => ['nullable', 'integer', 'exists:doctors,id'],
            '*.user_id' => ['nullable', 'integer', 'exists:users,id', 'distinct'],
            '*.name' => ['nullable', 'string', 'max:255'],
            '*.bonus' => ['nullable', 'numeric', 'min:0', 'max:100'],
            '*.commission_customer_scope' => ['nullable', 'in:new,existing,both'],
            '*.commission_after_materials' => ['nullable', 'boolean'],
            '*.sales_bonus_enabled' => ['nullable', 'boolean'],
            '*.sales_bonus_tiers' => ['nullable', 'array'],
            '*.salary' => ['nullable', 'numeric', 'min:0'],
            '*.hourly_rate' => ['nullable', 'numeric', 'min:0'],
            '*.overtime_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            '*.shortage_hourly_deduction' => ['nullable', 'numeric', 'min:0'],
            '*.absence_deduction' => ['nullable', 'numeric', 'min:0'],
            '*.allowed_shortage_hours' => ['nullable', 'numeric', 'min:0'],
            '*.available_days' => ['nullable', 'array'],
            '*.service_section_ids' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($validated) {
        $keptIds = [];
        foreach ($validated as $doctor) {
            if (empty($doctor['name']) && empty($doctor['user_id'])) {
                continue;
            }

            $user = ! empty($doctor['user_id']) ? User::find($doctor['user_id']) : null;
            $record = ! empty($doctor['id']) ? Doctor::find($doctor['id']) : null;
            if (! $record && $user) {
                $record = Doctor::firstWhere('user_id', $user->id);
            }
            $record ??= new Doctor();
            $record->fill([
                'user_id' => $user?->id,
                'name' => $user?->name ?? $doctor['name'],
                'bonus' => $doctor['bonus'] ?? 0,
                'commission_customer_scope' => in_array($doctor['commission_customer_scope'] ?? 'both', ['new', 'existing', 'both'], true) ? $doctor['commission_customer_scope'] : 'both',
                'commission_after_materials' => (bool) ($doctor['commission_after_materials'] ?? false),
                'sales_bonus_enabled' => (bool) ($doctor['sales_bonus_enabled'] ?? false),
                'sales_bonus_tiers' => $this->normalizeSalesBonusTiers($doctor['sales_bonus_tiers'] ?? []),
                'salary' => $doctor['salary'] ?? 0,
                'hourly_rate' => max(0, (int) ($doctor['hourly_rate'] ?? 0)),
                'overtime_hourly_rate' => max(0, (int) ($doctor['overtime_hourly_rate'] ?? 0)),
                'shortage_hourly_deduction' => max(0, (int) ($doctor['shortage_hourly_deduction'] ?? 0)),
                'absence_deduction' => max(0, (int) ($doctor['absence_deduction'] ?? 0)),
                'allowed_shortage_hours' => max(0, (float) ($doctor['allowed_shortage_hours'] ?? 0)),
                'available_days' => $doctor['available_days'] ?? [],
                'service_section_ids' => collect($doctor['service_section_ids'] ?? [])
                    ->filter(fn ($id) => is_numeric($id))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all(),
                'profile_photo_path' => $doctor['profile_photo_path'] ?? $record->profile_photo_path,
                'profile_thumbnail_path' => $doctor['profile_thumbnail_path'] ?? $record->profile_thumbnail_path,
            ]);
            $record->save();
            $keptIds[] = $record->id;
        }

        if ($keptIds !== []) {
            Doctor::whereNotIn('id', $keptIds)->get()->each->delete();
        }
        });

        return response()->json([
            'message' => 'اطلاعات پزشکان با موفقیت به‌روزرسانی شد.',
            'doctors' => Doctor::query()->orderBy('name')->get(),
        ]);
    }

public function saveStaff(Request $request)
    {
        $validated = $request->validate([
            '*' => ['array'],
            '*.id' => ['nullable', 'integer', 'exists:staff,id'],
            '*.user_id' => ['nullable', 'integer', 'exists:users,id', 'distinct'],
            '*.name' => ['nullable', 'string', 'max:255'],
            '*.bonus' => ['nullable', 'numeric', 'min:0', 'max:100'],
            '*.commission_customer_scope' => ['nullable', 'in:new,existing,both'],
            '*.commission_after_materials' => ['nullable', 'boolean'],
            '*.sales_bonus_enabled' => ['nullable', 'boolean'],
            '*.sales_bonus_tiers' => ['nullable', 'array'],
            '*.salary' => ['nullable', 'numeric', 'min:0'],
            '*.hourly_rate' => ['nullable', 'numeric', 'min:0'],
            '*.overtime_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            '*.shortage_hourly_deduction' => ['nullable', 'numeric', 'min:0'],
            '*.absence_deduction' => ['nullable', 'numeric', 'min:0'],
            '*.allowed_shortage_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            $keptIds = [];
            foreach ($validated as $staff) {
                if (empty($staff['name']) && empty($staff['user_id'])) {
                    continue;
                }

                $user = ! empty($staff['user_id']) ? User::find($staff['user_id']) : null;
                $record = ! empty($staff['id']) ? Staff::find($staff['id']) : null;
                if (! $record && $user) {
                    $record = Staff::firstWhere('user_id', $user->id);
                }
                $record ??= new Staff();
                $record->fill([
                    'user_id' => $user?->id,
                    'name' => $user?->name ?? $staff['name'],
                    'bonus' => $staff['bonus'] ?? 0,
                    'commission_customer_scope' => $staff['commission_customer_scope'] ?? 'both',
                    'commission_after_materials' => (bool) ($staff['commission_after_materials'] ?? false),
                    'sales_bonus_enabled' => (bool) ($staff['sales_bonus_enabled'] ?? false),
                    'sales_bonus_tiers' => $this->normalizeSalesBonusTiers($staff['sales_bonus_tiers'] ?? []),
                    'salary' => $staff['salary'] ?? 0,
                    'hourly_rate' => max(0, (int) ($staff['hourly_rate'] ?? 0)),
                    'overtime_hourly_rate' => max(0, (int) ($staff['overtime_hourly_rate'] ?? 0)),
                    'shortage_hourly_deduction' => max(0, (int) ($staff['shortage_hourly_deduction'] ?? 0)),
                    'absence_deduction' => max(0, (int) ($staff['absence_deduction'] ?? 0)),
                    'allowed_shortage_hours' => max(0, (float) ($staff['allowed_shortage_hours'] ?? 0)),
                    'profile_photo_path' => $staff['profile_photo_path'] ?? $record->profile_photo_path,
                    'profile_thumbnail_path' => $staff['profile_thumbnail_path'] ?? $record->profile_thumbnail_path,
                ]);
                $record->save();
                $keptIds[] = $record->id;
            }

            if ($keptIds !== []) {
                Staff::whereNotIn('id', $keptIds)->get()->each->delete();
            }
        });

        return response()->json([
            'message' => 'اطلاعات پرسنل با موفقیت به‌روزرسانی شد.',
            'staff' => Staff::query()->orderBy('name')->get(),
        ]);
    }

    public function uploadDoctorPhoto(Request $request, Doctor $doctor)
    {
        return $this->uploadResourcePhoto($request, $doctor, "doctors/{$doctor->id}/profile");
    }

    public function uploadStaffPhoto(Request $request, Staff $staff)
    {
        return $this->uploadResourcePhoto($request, $staff, "staff/{$staff->id}/profile");
    }

    private function uploadResourcePhoto(Request $request, Model $model, string $directory)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'thumbnail' => 'required|image|mimes:webp|max:30|dimensions:width=50,height=50',
        ]);

        Storage::disk('public')->delete(array_filter([
            $model->profile_photo_path,
            $model->profile_thumbnail_path,
        ]));

        $photoPath = $request->file('photo')->storeAs($directory, 'photo-'.Str::uuid().'.webp', 'public');
        $thumbnailPath = $request->file('thumbnail')->storeAs($directory, 'thumbnail-'.Str::uuid().'.webp', 'public');

        $model->update([
            'profile_photo_path' => $photoPath,
            'profile_thumbnail_path' => $thumbnailPath,
        ]);

        return response()->json([
            'message' => 'عکس با موفقیت ذخیره شد.',
            'resource' => $model->fresh(),
        ]);
    }

    private function normalizeSalesBonusTiers(array $tiers): array
    {
        return collect($tiers)->map(fn ($tier) => [
            'sales_from' => max(0, (int) ($tier['sales_from'] ?? 0)),
            'salary_addition' => max(0, (int) ($tier['salary_addition'] ?? 0)),
        ])->filter(fn ($tier) => $tier['sales_from'] > 0 && $tier['salary_addition'] > 0)
          ->sortBy('sales_from')->values()->all();
    }
}
