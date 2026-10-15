<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Inventory;
use App\Models\InventoryBookingAvailability;
use App\Models\InventoryBookingBreak;
use App\Models\InventoryBookingException;
use App\Models\InventoryBookingResource;
use App\Models\InventoryBookingRule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryBookingController extends Controller
{
    public function show(Inventory $inventory)
    {
        return response()->json($this->payload($inventory));
    }

    public function update(Request $request, Inventory $inventory)
    {
        $data = $request->validate([
            'booking_version' => ['required', 'integer', 'min:0'],
            'settings' => ['required','array'],
            'settings.booking_enabled' => ['required','boolean'],
            'settings.use_default_schedule' => ['required','boolean'],
            'settings.requires_doctor' => ['required','boolean'],
            'settings.requires_operator' => ['required','boolean'],
            'settings.assignment_mode' => ['required','in:auto,manual,customer,staff'],
            'settings.slot_interval_minutes' => ['nullable','integer','min:5','max:480'],
            'settings.conflict_check_enabled' => ['required','boolean'],
            'settings.online_enabled' => ['required','boolean'],
            'settings.online_payment_enabled' => ['required','boolean'],
            'settings.online_cancellation_enabled' => ['required','boolean'],
            'settings.online_start_time' => ['nullable','date_format:H:i'],
            'settings.online_end_time' => ['nullable','date_format:H:i','after:settings.online_start_time'],
            'settings.booking_start_after_days' => ['required','integer','min:0','max:365'],
            'settings.booking_available_days' => ['required','integer','min:1','max:730'],
            'settings.extra_settings' => ['nullable','array'],
            'resources' => ['array'],
            'resources.*.id' => ['nullable','integer','distinct'],
            'resources.*.role' => ['required','in:doctor,operator'],
            'resources.*.doctor_id' => ['nullable','integer','exists:doctors,id'],
            'resources.*.staff_id' => ['nullable','integer','exists:staff,id'],
            'resources.*.active' => ['boolean'],
            'resources.*.availabilities' => ['array'],
            'resources.*.availabilities.*.id' => ['nullable','integer'],
            'resources.*.availabilities.*.weekday' => ['required','integer','between:0,6'],
            'resources.*.availabilities.*.start_time' => ['required','date_format:H:i'],
            'resources.*.availabilities.*.end_time' => ['required','date_format:H:i'],
            'resources.*.availabilities.*.slot_interval_minutes' => ['nullable','integer','min:5','max:480'],
            'resources.*.availabilities.*.active' => ['boolean'],
            'resources.*.availabilities.*.breaks' => ['array'],
            'resources.*.availabilities.*.breaks.*.id' => ['nullable','integer'],
            'resources.*.availabilities.*.breaks.*.title' => ['nullable','string','max:100'],
            'resources.*.availabilities.*.breaks.*.start_time' => ['required','date_format:H:i'],
            'resources.*.availabilities.*.breaks.*.end_time' => ['required','date_format:H:i'],
            'resources.*.availabilities.*.breaks.*.active' => ['boolean'],
            'exceptions' => ['array'],
            'exceptions.*.id' => ['nullable','integer','distinct'],
            'exceptions.*.booking_resource_id' => ['nullable','integer'],
            'exceptions.*.exception_date' => ['required','date'],
            'exceptions.*.type' => ['required','in:closed,custom'],
            'exceptions.*.start_time' => ['nullable','date_format:H:i'],
            'exceptions.*.end_time' => ['nullable','date_format:H:i'],
            'exceptions.*.reason' => ['nullable','string','max:255'],
            'rules' => ['array'],
            'rules.*.id' => ['nullable','integer','distinct'],
            'rules.*.rule_type' => ['required','string','max:80','distinct'],
            'rules.*.rule_value' => ['nullable'],
            'rules.*.active' => ['boolean'],
        ]);

        $selectedResources = [];
        foreach ($data['resources'] ?? [] as $resource) {
            $valid = $resource['role'] === 'doctor' ? !empty($resource['doctor_id']) : !empty($resource['staff_id']);
            abort_unless($valid, 422, 'برای هر منبع، پزشک یا اپراتور متناظر را انتخاب کنید.');
            $resourceKey = $resource['role'].':'.($resource['role'] === 'doctor' ? $resource['doctor_id'] : $resource['staff_id']);
            abort_if(isset($selectedResources[$resourceKey]), 422, 'این پزشک یا اپراتور دوبار انتخاب شده است. در مرحله «منابع وقت‌دهی» مورد تکراری را حذف کنید.');
            $selectedResources[$resourceKey] = true;
            foreach ($resource['availabilities'] ?? [] as $availability) {
                abort_unless($availability['end_time'] > $availability['start_time'], 422, 'ساعت پایان حضور باید بعد از ساعت شروع باشد.');
                foreach ($availability['breaks'] ?? [] as $break) {
                    abort_unless($break['end_time'] > $break['start_time'] && $break['start_time'] >= $availability['start_time'] && $break['end_time'] <= $availability['end_time'], 422, 'زمان استراحت باید داخل بازه حضور باشد.');
                }
            }
        }

        try {
            DB::transaction(function () use ($request, $inventory, $data) {
                // Lock the service itself so two first-time saves cannot both try
                // to INSERT the same unique inventory_booking_settings row.
                Inventory::query()->whereKey($inventory->getKey())->lockForUpdate()->firstOrFail();
                $before = $this->payload($inventory);
                $version = (int) DB::table('inventory_booking_audits')->where('inventory_id', $inventory->id)->max('id');
                abort_if($version !== (int) $data['booking_version'], 409,
                    'تنظیمات این خدمت در صفحهٔ دیگری تغییر کرده است. تنظیمات را دوباره بارگذاری کنید.');
                $settings = $data['settings'];
                foreach (['online_start_time', 'online_end_time'] as $field) {
                    if (! empty($settings[$field])) $settings[$field] = self::databaseTime($settings[$field]);
                }
                $inventory->bookingSetting()->updateOrCreate([], $settings);
                $this->syncResources($inventory, $data['resources'] ?? []);
                $this->syncExceptions($inventory, $data['exceptions'] ?? []);
                $this->syncRules($inventory, $data['rules'] ?? []);
                DB::table('inventory_booking_audits')->insert([
                    'inventory_id'=>$inventory->id, 'changed_by'=>$request->user()?->id,
                    'before_data'=>json_encode($before, JSON_UNESCAPED_UNICODE),
                    'after_data'=>json_encode($this->payload($inventory), JSON_UNESCAPED_UNICODE),
                    'changed_at'=>now(), 'created_at'=>now(), 'updated_at'=>now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'inventory_booking_settings_inventory_id_unique')) {
                throw $exception;
            }
            report($exception);
            return response()->json([
                'message' => 'تنظیمات این خدمت هم‌زمان تغییر کرده است. تنظیمات را دوباره بارگذاری و پس از بررسی ذخیره کنید.',
            ], 409);
        }

        return response()->json(['message'=>'تنظیمات وقت‌دهی خدمت ذخیره شد.', ...$this->payload($inventory)]);
    }

    private function syncResources(Inventory $inventory, array $resources): void
    {
        $existing = $inventory->bookingResources()->lockForUpdate()->get();
        $requestedExistingIds = collect($resources)->map(function ($data) use ($existing) {
            if (! empty($data['id'])) return (int) $data['id'];
            return $existing->first(fn ($row) => $row->role === ($data['role'] ?? null)
                && (int) ($row->role === 'doctor' ? $row->doctor_id : $row->staff_id)
                    === (int) ($row->role === 'doctor' ? ($data['doctor_id'] ?? 0) : ($data['staff_id'] ?? 0)))?->id;
        })->filter()->all();
        $existing->whereNotIn('id', $requestedExistingIds)->each->delete();
        foreach (array_values($resources) as $order => $data) {
            $resource = ! empty($data['id']) ? $existing->firstWhere('id', (int) $data['id']) : $existing->first(fn ($row) =>
                $row->role === $data['role'] && (int) ($data['role'] === 'doctor' ? $row->doctor_id : $row->staff_id)
                    === (int) ($data['role'] === 'doctor' ? $data['doctor_id'] : $data['staff_id']));
            if (! $resource && ! empty($data['id'])) throw ValidationException::withMessages(['resources' => 'یکی از منابع وقت‌دهی دیگر وجود ندارد. تنظیمات را تازه‌سازی کنید.']);
            $resource ??= new InventoryBookingResource(['inventory_id' => $inventory->id]);
            $resource->fill([
                'role' => $data['role'],
                'doctor_id' => $data['role'] === 'doctor' ? $data['doctor_id'] : null,
                'staff_id' => $data['role'] === 'operator' ? $data['staff_id'] : null,
                'active' => $data['active'] ?? true,
                'sort_order' => $order,
            ]);
            if (! $resource->exists || $resource->isDirty()) $resource->save();
            $this->syncAvailabilities($resource, $data['availabilities'] ?? []);
        }
    }

    private function syncAvailabilities(InventoryBookingResource $resource, array $availabilities): void
    {
        $existing = $resource->availabilities()->lockForUpdate()->get();
        $kept = [];
        foreach ($availabilities as $data) {
            $availability = ! empty($data['id']) ? $existing->firstWhere('id', (int) $data['id'])
                : $existing->firstWhere('weekday', (int) $data['weekday']);
            if (! $availability && ! empty($data['id'])) throw ValidationException::withMessages(['resources' => 'یکی از روزهای حضور دیگر وجود ندارد. تنظیمات را تازه‌سازی کنید.']);
            $availability ??= new InventoryBookingAvailability(['booking_resource_id' => $resource->id]);
            $availability->fill([
                'weekday' => $data['weekday'], 'start_time' => self::databaseTime($data['start_time']), 'end_time' => self::databaseTime($data['end_time']),
                'slot_interval_minutes' => $data['slot_interval_minutes'] ?? null, 'active' => $data['active'] ?? true,
            ]);
            if (! $availability->exists || $availability->isDirty()) $availability->save();
            $kept[] = $availability->id;
            $this->syncBreaks($availability, $data['breaks'] ?? []);
        }
        $existing->whereNotIn('id', $kept)->each->delete();
    }

    private function syncBreaks(InventoryBookingAvailability $availability, array $breaks): void
    {
        $existing = $availability->breaks()->lockForUpdate()->get();
        $kept = [];
        foreach ($breaks as $data) {
            $break = ! empty($data['id']) ? $existing->firstWhere('id', (int) $data['id'])
                : $existing->first(fn ($row) => substr($row->start_time, 0, 5) === $data['start_time'] && substr($row->end_time, 0, 5) === $data['end_time']
                    && $row->title === ($data['title'] ?? null) && ! in_array($row->id, $kept, true));
            if (! $break && ! empty($data['id'])) throw ValidationException::withMessages(['resources' => 'یکی از زمان‌های استراحت دیگر وجود ندارد. تنظیمات را تازه‌سازی کنید.']);
            $break ??= new InventoryBookingBreak(['availability_id' => $availability->id]);
            $break->fill([
                'title' => $data['title'] ?? null, 'start_time' => self::databaseTime($data['start_time']), 'end_time' => self::databaseTime($data['end_time']),
                'active' => $data['active'] ?? true,
            ]);
            if (! $break->exists || $break->isDirty()) $break->save();
            $kept[] = $break->id;
        }
        $existing->whereNotIn('id', $kept)->each->delete();
    }

    private function syncExceptions(Inventory $inventory, array $exceptions): void
    {
        $existing = $inventory->bookingExceptions()->lockForUpdate()->get();
        $kept = [];
        foreach ($exceptions as $data) {
            $exception = ! empty($data['id']) ? $existing->firstWhere('id', (int) $data['id']) : null;
            if (! $exception && ! empty($data['id'])) throw ValidationException::withMessages(['exceptions' => 'یکی از استثناهای وقت‌دهی دیگر وجود ندارد. تنظیمات را تازه‌سازی کنید.']);
            $resourceId = $data['booking_resource_id'] ?? null;
            if ($resourceId && ! $inventory->bookingResources()->whereKey($resourceId)->exists()) {
                throw ValidationException::withMessages(['exceptions' => 'منبع این استثنا دیگر وجود ندارد.']);
            }
            $exception ??= new InventoryBookingException(['inventory_id' => $inventory->id]);
            $exception->fill([
                'booking_resource_id' => $resourceId, 'exception_date' => $data['exception_date'],
                'type' => $data['type'], 'start_time' => self::databaseTime($data['start_time'] ?? null),
                'end_time' => self::databaseTime($data['end_time'] ?? null), 'reason' => $data['reason'] ?? null,
            ]);
            if (! $exception->exists || $exception->isDirty()) $exception->save();
            $kept[] = $exception->id;
        }
        $existing->whereNotIn('id', $kept)->each->delete();
    }

    private function syncRules(Inventory $inventory, array $rules): void
    {
        $existing = $inventory->bookingRules()->lockForUpdate()->get();
        $requestedExistingIds = collect($rules)->map(fn ($data) => ! empty($data['id'])
            ? (int) $data['id'] : $existing->firstWhere('rule_type', $data['rule_type'] ?? null)?->id)->filter()->all();
        $existing->whereNotIn('id', $requestedExistingIds)->each->delete();
        foreach ($rules as $data) {
            $rule = ! empty($data['id']) ? $existing->firstWhere('id', (int) $data['id']) : $existing->firstWhere('rule_type', $data['rule_type']);
            if (! $rule && ! empty($data['id'])) throw ValidationException::withMessages(['rules' => 'یکی از قوانین وقت‌دهی دیگر وجود ندارد. تنظیمات را تازه‌سازی کنید.']);
            $rule ??= new InventoryBookingRule(['inventory_id' => $inventory->id]);
            $rule->fill(['rule_type' => $data['rule_type'], 'rule_value' => $data['rule_value'] ?? null, 'active' => $data['active'] ?? true]);
            if (! $rule->exists || $rule->isDirty()) $rule->save();
        }
    }

    private static function databaseTime(?string $time): ?string
    {
        return $time === null || $time === '' ? null : substr($time, 0, 5).':00';
    }

    public function effective(Inventory $inventory)
    {
        $payload = $this->payload($inventory);
        $setting = $payload['settings'];
        $raw = AppSetting::getByKey('clinic_schedule_settings', '{}');
        $clinic = is_string($raw) ? json_decode($raw, true) : $raw;
        $payload['effective_schedule'] = $setting['use_default_schedule'] || empty($payload['resources'])
            ? ['source'=>'clinic', 'schedule'=>is_array($clinic) ? $clinic : []]
            : ['source'=>'service', 'resources'=>$payload['resources']];
        return response()->json($payload);
    }

    private function payload(Inventory $inventory): array
    {
        $inventory->load(['bookingSetting','bookingResources.doctor:id,name,available_days','bookingResources.staff:id,name','bookingResources.availabilities.breaks','bookingExceptions','bookingRules']);
        $defaults = [
            'booking_enabled'=>false,'use_default_schedule'=>true,'requires_doctor'=>false,'requires_operator'=>false,
            'assignment_mode'=>'auto','slot_interval_minutes'=>null,'conflict_check_enabled'=>true,'online_enabled'=>false,
            'online_payment_enabled'=>false,'online_cancellation_enabled'=>false,'online_start_time'=>null,'online_end_time'=>null,
            'booking_start_after_days'=>0,'booking_available_days'=>30,'extra_settings'=>[],
        ];
        $settings = array_replace($defaults, $inventory->bookingSetting?->only(array_keys($defaults)) ?? []);
        foreach (['online_start_time', 'online_end_time'] as $timeKey) {
            if (!empty($settings[$timeKey])) $settings[$timeKey] = substr((string) $settings[$timeKey], 0, 5);
        }
        return [
            'inventory'=>['id'=>$inventory->id,'name'=>$inventory->name],
            'booking_version'=>(int) DB::table('inventory_booking_audits')->where('inventory_id', $inventory->id)->max('id'),
            'settings'=>$settings,
            'resources'=>$inventory->bookingResources->map(fn($resource)=>[
                'id'=>$resource->id,'role'=>$resource->role,'doctor_id'=>$resource->doctor_id,'staff_id'=>$resource->staff_id,
                'name'=>$resource->doctor?->name ?: $resource->staff?->name,'available_days'=>$resource->doctor?->available_days ?: [],'active'=>$resource->active,
                'availabilities'=>$resource->availabilities->map(fn($availability)=>[
                    'id'=>$availability->id,'weekday'=>$availability->weekday,'start_time'=>substr($availability->start_time,0,5),'end_time'=>substr($availability->end_time,0,5),
                    'slot_interval_minutes'=>$availability->slot_interval_minutes,'active'=>$availability->active,
                    'breaks'=>$availability->breaks->map(fn($break)=>['id'=>$break->id,'title'=>$break->title,'start_time'=>substr($break->start_time,0,5),'end_time'=>substr($break->end_time,0,5),'active'=>$break->active])->values(),
                ])->values(),
            ])->values(),
            'exceptions'=>$inventory->bookingExceptions->map(fn($item)=>['id'=>$item->id,'booking_resource_id'=>$item->booking_resource_id,'exception_date'=>$item->exception_date?->format('Y-m-d'),'type'=>$item->type,'start_time'=>$item->start_time ? substr($item->start_time,0,5):null,'end_time'=>$item->end_time ? substr($item->end_time,0,5):null,'reason'=>$item->reason])->values(),
            'rules'=>$inventory->bookingRules->map(fn($item)=>['id'=>$item->id,'rule_type'=>$item->rule_type,'rule_value'=>$item->rule_value,'active'=>$item->active])->values(),
        ];
    }
}
