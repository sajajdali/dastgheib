<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Inventory;
use App\Models\InventoryBookingAvailability;
use App\Models\InventoryBookingResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryBookingController extends Controller
{
    public function show(Inventory $inventory)
    {
        return response()->json($this->payload($inventory));
    }

    public function update(Request $request, Inventory $inventory)
    {
        $data = $request->validate([
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
            'resources.*.role' => ['required','in:doctor,operator'],
            'resources.*.doctor_id' => ['nullable','integer','exists:doctors,id'],
            'resources.*.staff_id' => ['nullable','integer','exists:staff,id'],
            'resources.*.active' => ['boolean'],
            'resources.*.availabilities' => ['array'],
            'resources.*.availabilities.*.weekday' => ['required','integer','between:0,6'],
            'resources.*.availabilities.*.start_time' => ['required','date_format:H:i'],
            'resources.*.availabilities.*.end_time' => ['required','date_format:H:i'],
            'resources.*.availabilities.*.slot_interval_minutes' => ['nullable','integer','min:5','max:480'],
            'resources.*.availabilities.*.active' => ['boolean'],
            'resources.*.availabilities.*.breaks' => ['array'],
            'resources.*.availabilities.*.breaks.*.title' => ['nullable','string','max:100'],
            'resources.*.availabilities.*.breaks.*.start_time' => ['required','date_format:H:i'],
            'resources.*.availabilities.*.breaks.*.end_time' => ['required','date_format:H:i'],
            'exceptions' => ['array'],
            'exceptions.*.exception_date' => ['required','date'],
            'exceptions.*.type' => ['required','in:closed,custom'],
            'exceptions.*.start_time' => ['nullable','date_format:H:i'],
            'exceptions.*.end_time' => ['nullable','date_format:H:i'],
            'exceptions.*.reason' => ['nullable','string','max:255'],
            'rules' => ['array'],
            'rules.*.rule_type' => ['required','string','max:80'],
            'rules.*.rule_value' => ['nullable'],
            'rules.*.active' => ['boolean'],
        ]);

        foreach ($data['resources'] ?? [] as $index => $resource) {
            $valid = $resource['role'] === 'doctor' ? !empty($resource['doctor_id']) : !empty($resource['staff_id']);
            abort_unless($valid, 422, 'برای هر منبع، پزشک یا اپراتور متناظر را انتخاب کنید.');
            foreach ($resource['availabilities'] ?? [] as $availability) {
                abort_unless($availability['end_time'] > $availability['start_time'], 422, 'ساعت پایان حضور باید بعد از ساعت شروع باشد.');
                foreach ($availability['breaks'] ?? [] as $break) {
                    abort_unless($break['end_time'] > $break['start_time'] && $break['start_time'] >= $availability['start_time'] && $break['end_time'] <= $availability['end_time'], 422, 'زمان استراحت باید داخل بازه حضور باشد.');
                }
            }
        }

        DB::transaction(function () use ($request, $inventory, $data) {
            $before = $this->payload($inventory);
            $inventory->bookingSetting()->updateOrCreate([], $data['settings']);
            $inventory->bookingResources()->delete();
            foreach (array_values($data['resources'] ?? []) as $order => $resourceData) {
                $resource = $inventory->bookingResources()->create([
                    'role'=>$resourceData['role'], 'doctor_id'=>$resourceData['role']==='doctor' ? $resourceData['doctor_id'] : null,
                    'staff_id'=>$resourceData['role']==='operator' ? $resourceData['staff_id'] : null,
                    'active'=>$resourceData['active'] ?? true, 'sort_order'=>$order,
                ]);
                foreach ($resourceData['availabilities'] ?? [] as $availabilityData) {
                    $availability = $resource->availabilities()->create(collect($availabilityData)->except('breaks')->all());
                    foreach ($availabilityData['breaks'] ?? [] as $break) $availability->breaks()->create($break + ['active'=>true]);
                }
            }
            $inventory->bookingExceptions()->delete();
            foreach ($data['exceptions'] ?? [] as $exception) $inventory->bookingExceptions()->create($exception);
            $inventory->bookingRules()->delete();
            foreach ($data['rules'] ?? [] as $rule) $inventory->bookingRules()->create($rule);
            DB::table('inventory_booking_audits')->insert([
                'inventory_id'=>$inventory->id, 'changed_by'=>$request->user()?->id,
                'before_data'=>json_encode($before, JSON_UNESCAPED_UNICODE),
                'after_data'=>json_encode($this->payload($inventory), JSON_UNESCAPED_UNICODE),
                'changed_at'=>now(), 'created_at'=>now(), 'updated_at'=>now(),
            ]);
        });

        return response()->json(['message'=>'تنظیمات وقت‌دهی خدمت ذخیره شد.', ...$this->payload($inventory)]);
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
            'settings'=>$settings,
            'resources'=>$inventory->bookingResources->map(fn($resource)=>[
                'id'=>$resource->id,'role'=>$resource->role,'doctor_id'=>$resource->doctor_id,'staff_id'=>$resource->staff_id,
                'name'=>$resource->doctor?->name ?: $resource->staff?->name,'available_days'=>$resource->doctor?->available_days ?: [],'active'=>$resource->active,
                'availabilities'=>$resource->availabilities->map(fn($availability)=>[
                    'weekday'=>$availability->weekday,'start_time'=>substr($availability->start_time,0,5),'end_time'=>substr($availability->end_time,0,5),
                    'slot_interval_minutes'=>$availability->slot_interval_minutes,'active'=>$availability->active,
                    'breaks'=>$availability->breaks->map(fn($break)=>['title'=>$break->title,'start_time'=>substr($break->start_time,0,5),'end_time'=>substr($break->end_time,0,5),'active'=>$break->active])->values(),
                ])->values(),
            ])->values(),
            'exceptions'=>$inventory->bookingExceptions->map(fn($item)=>['exception_date'=>$item->exception_date?->format('Y-m-d'),'type'=>$item->type,'start_time'=>$item->start_time ? substr($item->start_time,0,5):null,'end_time'=>$item->end_time ? substr($item->end_time,0,5):null,'reason'=>$item->reason])->values(),
            'rules'=>$inventory->bookingRules->map(fn($item)=>['rule_type'=>$item->rule_type,'rule_value'=>$item->rule_value,'active'=>$item->active])->values(),
        ];
    }
}
