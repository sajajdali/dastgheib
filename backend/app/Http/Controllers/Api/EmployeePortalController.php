<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Doctor;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeMessage;
use App\Models\EmployeePortalNotification;
use App\Models\ResourceAdjustment;
use App\Models\SalaryAdvance;
use App\Models\Staff;
use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmployeePortalController extends Controller
{
    public function __construct(private readonly EmployeePortalService $portal) {}

    public function leaves(Request $request): JsonResponse
    {
        [$type, $resource] = $this->requestedResource($request);
        return response()->json(['requests' => EmployeeLeaveRequest::query()
            ->where('resource_type', $type)->where('resource_id', $resource->id)
            ->orderByDesc('created_at')->get()]);
    }

    public function storeLeave(Request $request): JsonResponse
    {
        $request->user()->can('employee_portal.leave_request') || abort(403);
        ['type' => $type, 'model' => $resource] = $this->portal->resourceForUser($request->user());
        $data = $request->validate([
            'leave_type' => ['required', Rule::in(['daily', 'hourly'])],
            'start_date' => ['required', 'regex:/^(13|14)\d{2}-(0[1-9]|1[0-2])-([0-2]\d|3[01])$/'],
            'end_date' => ['required', 'regex:/^(13|14)\d{2}-(0[1-9]|1[0-2])-([0-2]\d|3[01])$/', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'required_if:leave_type,hourly', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_if:leave_type,hourly', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['leave_type'] === 'hourly') {
            abort_if($data['start_date'] !== $data['end_date'], 422, 'مرخصی ساعتی باید در یک روز باشد.');
            abort_if(($data['end_time'] ?? '') <= ($data['start_time'] ?? ''), 422, 'ساعت پایان باید بعد از ساعت شروع باشد.');
        }

        $leave = EmployeeLeaveRequest::query()->create([
            ...$data, 'resource_type' => $type, 'resource_id' => $resource->id,
            'user_id' => $request->user()->id, 'status' => 'pending',
        ]);
        $this->portal->notifyManagers($type, $resource->id, 'leave_requested', 'درخواست مرخصی جدید', $resource->name, 'leaves', ['leave_id' => $leave->id]);
        return response()->json(['request' => $leave], 201);
    }

    public function cancelLeave(Request $request, EmployeeLeaveRequest $leave): JsonResponse
    {
        ['type' => $type, 'model' => $resource] = $this->portal->resourceForUser($request->user());
        abort_unless($leave->resource_type === $type && (int) $leave->resource_id === (int) $resource->id, 403);
        abort_unless($leave->status === 'pending', 422, 'فقط درخواست در انتظار قابل لغو است.');
        $leave->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->portal->notifyManagers($type, $resource->id, 'leave_cancelled', 'درخواست مرخصی لغو شد', $resource->name, 'leaves', ['leave_id' => $leave->id]);
        return response()->json(['request' => $leave->fresh()]);
    }

    public function reviewLeave(Request $request, EmployeeLeaveRequest $leave): JsonResponse
    {
        $this->assertManager($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'manager_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $leave->update([...$data, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        app(AttendanceMonthController::class)->resyncAttendanceEarnings($leave->resource_type, $leave->resource_id);
        $this->portal->notifyEmployee($leave->resource_type, $leave->resource_id, 'leave_'.$data['status'], $data['status'] === 'approved' ? 'مرخصی تأیید شد' : 'مرخصی رد شد', $data['manager_note'] ?? null, 'leaves', ['leave_id' => $leave->id]);
        return response()->json(['request' => $leave->fresh()]);
    }

    public function advances(Request $request): JsonResponse
    {
        [$type, $resource] = $this->requestedResource($request);
        return response()->json(['advances' => SalaryAdvance::query()->with('installments')
            ->where('resource_type', $type)->where('resource_id', $resource->id)
            ->orderByDesc('created_at')->get()]);
    }

    public function storeAdvance(Request $request): JsonResponse
    {
        $request->user()->can('employee_portal.advance_request') || abort(403);
        ['type' => $type, 'model' => $resource] = $this->portal->resourceForUser($request->user());
        $data = $request->validate([
            'requested_amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $advance = SalaryAdvance::query()->create([
            ...$data, 'resource_type' => $type, 'resource_id' => $resource->id,
            'user_id' => $request->user()->id, 'status' => 'pending',
        ]);
        $this->portal->notifyManagers($type, $resource->id, 'advance_requested', 'درخواست مساعده جدید', $resource->name, 'advances', ['advance_id' => $advance->id]);
        return response()->json(['advance' => $advance->load('installments')], 201);
    }

    public function cancelAdvance(Request $request, SalaryAdvance $advance): JsonResponse
    {
        ['type' => $type, 'model' => $resource] = $this->portal->resourceForUser($request->user());
        abort_unless($advance->resource_type === $type && (int) $advance->resource_id === (int) $resource->id, 403);
        abort_unless($advance->status === 'pending', 422, 'فقط درخواست در انتظار قابل لغو است.');
        $advance->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        return response()->json(['advance' => $advance->fresh()->load('installments')]);
    }

    public function reviewAdvance(Request $request, SalaryAdvance $advance): JsonResponse
    {
        $this->assertManager($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'ready', 'paid', 'deducted'])],
            'approved_amount' => ['nullable', 'required_unless:status,rejected', 'numeric', 'min:1'],
            'manager_note' => ['nullable', 'string', 'max:2000'],
            'deduct_from_salary' => ['required', 'boolean'],
            'installment_count' => ['required', 'integer', 'min:1', 'max:36'],
            'deduction_start_month' => ['nullable', 'required_if:deduct_from_salary,true', 'regex:/^(13|14)\d{2}-(0[1-9]|1[0-2])$/'],
        ]);
        DB::transaction(function () use ($advance, $data, $request) {
            $advance->update([
                ...$data, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
                'paid_at' => in_array($data['status'], ['paid', 'deducted'], true) ? ($advance->paid_at ?? now()) : null,
            ]);
            $this->portal->syncAdvanceInstallments($advance->fresh());
        });
        $this->portal->notifyEmployee($advance->resource_type, $advance->resource_id, 'advance_'.$data['status'], 'وضعیت مساعده تغییر کرد', $data['manager_note'] ?? null, 'advances', ['advance_id' => $advance->id]);
        return response()->json(['advance' => $advance->fresh()->load('installments')]);
    }

    public function adjustments(Request $request): JsonResponse
    {
        [$type, $resource] = $this->requestedResource($request);
        $query = ResourceAdjustment::query()->where('resource_type', $type)->where('resource_id', $resource->id)->where('status', 'active');
        if ($request->filled('month')) $query->where('month', $request->string('month'));
        return response()->json(['adjustments' => $query->orderByDesc('occurred_on')->orderByDesc('id')->get()]);
    }

    public function storeAdjustment(Request $request): JsonResponse
    {
        $request->user()->can('employee_portal.adjustments_manage') || abort(403);
        $data = $this->adjustmentData($request);
        $resource = $this->portal->resource($data['resource_type'], (int) $data['resource_id']);
        $data['month'] = substr($data['occurred_on'], 0, 7);
        $amount = abs((float) $data['amount']) * ($data['kind'] === 'deduction' ? -1 : 1);
        $adjustment = ResourceAdjustment::query()->create([
            ...$data, 'amount' => $amount, 'reason' => $data['title'], 'status' => 'active',
            'created_by' => $request->user()->id, 'created_by_name' => $request->user()->name,
        ]);
        $this->portal->notifyEmployee($data['resource_type'], $resource->id, 'adjustment_created', $data['kind'] === 'deduction' ? 'کسری جدید ثبت شد' : 'پاداش جدید ثبت شد', $data['title'], 'adjustments', ['adjustment_id' => $adjustment->id]);
        return response()->json(['adjustment' => $adjustment], 201);
    }

    public function updateAdjustment(Request $request, ResourceAdjustment $adjustment): JsonResponse
    {
        $request->user()->can('employee_portal.adjustments_manage') || abort(403);
        $data = $this->adjustmentData($request, false);
        unset($data['resource_type'], $data['resource_id']);
        $data['month'] = substr($data['occurred_on'], 0, 7);
        $data['amount'] = abs((float) $data['amount']) * ($data['kind'] === 'deduction' ? -1 : 1);
        $data['reason'] = $data['title'];
        $adjustment->update($data);
        return response()->json(['adjustment' => $adjustment->fresh()]);
    }

    public function destroyAdjustment(Request $request, ResourceAdjustment $adjustment): JsonResponse
    {
        $request->user()->can('employee_portal.adjustments_manage') || abort(403);
        $adjustment->update(['status' => 'deleted']);
        $this->portal->notifyEmployee($adjustment->resource_type, $adjustment->resource_id, 'adjustment_deleted', 'یک ردیف پاداش یا کسری حذف شد', $adjustment->title ?: $adjustment->reason, 'adjustments');
        return response()->json(['success' => true]);
    }

    public function messages(Request $request): JsonResponse
    {
        [$type, $resource] = $this->requestedResource($request);
        $manager = $request->user()->can('employee_portal.manage');
        EmployeeMessage::query()->where('resource_type', $type)->where('resource_id', $resource->id)
            ->whereNull($manager ? 'read_by_manager_at' : 'read_by_employee_at')
            ->where('sender_user_id', '!=', $request->user()->id)
            ->update([$manager ? 'read_by_manager_at' : 'read_by_employee_at' => now()]);
        return response()->json(['messages' => EmployeeMessage::query()
            ->where('resource_type', $type)->where('resource_id', $resource->id)
            ->orderBy('created_at')->limit(500)->get()]);
    }

    public function storeMessage(Request $request): JsonResponse
    {
        $request->user()->can('employee_portal.message') || $request->user()->can('employee_portal.manage') || abort(403);
        [$type, $resource] = $this->requestedResource($request);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'voice' => ['nullable', 'file', 'mimes:webm,ogg,mp3,wav,m4a,mp4,opus', 'max:15360'],
            'voice_duration' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);
        abort_unless(trim((string) ($data['body'] ?? '')) !== '' || $request->hasFile('image') || $request->hasFile('voice'), 422, 'متن، تصویر یا پیام صوتی را وارد کنید.');
        $manager = $request->user()->can('employee_portal.manage');
        $message = EmployeeMessage::query()->create([
            'resource_type' => $type, 'resource_id' => $resource->id,
            'sender_user_id' => $request->user()->id, 'sender_name' => $request->user()->name,
            'body' => trim((string) ($data['body'] ?? '')) ?: null,
            'image_path' => $request->file('image')?->store('employee-portal/images', 'public'),
            'voice_path' => $request->file('voice')?->store('employee-portal/voices', 'public'),
            'voice_duration' => $data['voice_duration'] ?? null,
            'read_by_manager_at' => $manager ? now() : null,
            'read_by_employee_at' => $manager ? null : now(),
        ]);
        if ($manager) $this->portal->notifyEmployee($type, $resource->id, 'message_received', 'پیام جدید از مدیریت', $message->body, 'messages', ['message_id' => $message->id]);
        else $this->portal->notifyManagers($type, $resource->id, 'message_received', 'پیام جدید از '.$resource->name, $message->body, 'messages', ['message_id' => $message->id]);
        return response()->json(['message' => $message->fresh()], 201);
    }

    public function notifications(Request $request): JsonResponse
    {
        $query = EmployeePortalNotification::query()->where('user_id', $request->user()->id);
        return response()->json([
            'notifications' => (clone $query)->orderByDesc('created_at')->limit(100)->get(),
            'unread_count' => (clone $query)->whereNull('read_at')->count(),
        ]);
    }

    public function readNotification(Request $request, EmployeePortalNotification $notification): JsonResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        return response()->json(['notification' => $notification->fresh()]);
    }

    public function readAllNotifications(Request $request): JsonResponse
    {
        EmployeePortalNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function columnOptions(Request $request): JsonResponse
    {
        $request->user()->can('employee_portal.templates_manage') || abort(403);
        $stored = AppSetting::getByKey('employee_portal_default_columns');
        $default = is_string($stored) ? json_decode($stored, true) : $stored;
        return response()->json([
            'columns' => collect(config('employee_portal.columns'))->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'default_columns' => is_array($default) ? $default : config('employee_portal.default_columns'),
            'resources' => Doctor::query()->orderBy('name')->get()->map(fn ($row) => ['type' => 'doctor', 'id' => $row->id, 'name' => $row->name, 'columns' => $row->personal_report_columns])
                ->merge(Staff::query()->orderBy('name')->get()->map(fn ($row) => ['type' => 'staff', 'id' => $row->id, 'name' => $row->name, 'columns' => $row->personal_report_columns]))->values(),
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $request->user()->can('employee_portal.templates_manage') || abort(403);
        $allowed = array_keys(config('employee_portal.columns', []));
        $data = $request->validate([
            'resource_type' => ['nullable', Rule::in(['doctor', 'staff'])],
            'resource_id' => ['nullable', 'integer'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['required', 'string', Rule::in($allowed), 'distinct'],
            'scope' => ['required', Rule::in(['resource', 'selected', 'all_doctors', 'all_staff', 'default'])],
            'targets' => ['nullable', 'required_if:scope,selected', 'array', 'min:1'],
            'targets.*.type' => ['required', Rule::in(['doctor', 'staff'])],
            'targets.*.id' => ['required', 'integer', 'min:1'],
        ]);
        $columns = array_values($data['columns']);
        if ($data['scope'] === 'default') {
            AppSetting::query()->updateOrCreate(['key' => 'employee_portal_default_columns'], ['value' => json_encode($columns, JSON_UNESCAPED_UNICODE)]);
        } elseif ($data['scope'] === 'resource') {
            $this->portal->resource($data['resource_type'], (int) $data['resource_id'])->update(['personal_report_columns' => $columns]);
        } elseif ($data['scope'] === 'selected') {
            collect($data['targets'])->unique(fn ($target) => $target['type'].'-'.$target['id'])
                ->each(fn ($target) => $this->portal->resource($target['type'], (int) $target['id'])->update(['personal_report_columns' => $columns]));
        } else {
            ($data['scope'] === 'all_doctors' ? Doctor::query() : Staff::query())->update(['personal_report_columns' => json_encode($columns, JSON_UNESCAPED_UNICODE)]);
        }
        return response()->json(['success' => true]);
    }

    private function requestedResource(Request $request): array
    {
        if ($request->user()->can('employee_portal.manage') && $request->filled(['resource_type', 'resource_id'])) {
            $type = (string) $request->input('resource_type');
            return [$type, $this->portal->resource($type, (int) $request->input('resource_id'))];
        }
        ['type' => $type, 'model' => $resource] = $this->portal->resourceForUser($request->user());
        return [$type, $resource];
    }

    private function assertManager(Request $request): void
    {
        $request->user()->can('employee_portal.manage') || abort(403);
    }

    private function adjustmentData(Request $request, bool $withResource = true): array
    {
        return $request->validate([
            'resource_type' => [$withResource ? 'required' : 'sometimes', Rule::in(['doctor', 'staff'])],
            'resource_id' => [$withResource ? 'required' : 'sometimes', 'integer'],
            'month' => ['required', 'regex:/^(13|14)\d{2}-(0[1-9]|1[0-2])$/'],
            'occurred_on' => ['required', 'regex:/^(13|14)\d{2}-(0[1-9]|1[0-2])-([0-2]\d|3[01])$/'],
            'kind' => ['required', Rule::in(['bonus', 'deduction', 'adjustment'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);
    }
}
