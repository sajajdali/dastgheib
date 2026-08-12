<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceMonth;
use App\Models\Doctor;
use App\Models\ResourceEarningLine;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollReportController extends Controller
{
    public function resources(): JsonResponse
    {
        $doctors = Doctor::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Doctor $doctor) => $this->resourcePayload('doctor', $doctor));

        $staff = Staff::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Staff $staff) => $this->resourcePayload('staff', $staff));

        return response()->json([
            'resources' => $doctors->merge($staff)->values(),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resource_type' => ['required', Rule::in(['doctor', 'staff'])],
            'resource_id' => ['required', 'integer', 'min:1'],
            'month' => ['required', 'regex:/^(13|14)\d{2}-(0[1-9]|1[0-2])$/'],
        ]);

        $resource = $data['resource_type'] === 'doctor'
            ? Doctor::query()->findOrFail($data['resource_id'])
            : Staff::query()->findOrFail($data['resource_id']);

        [$year, $month] = array_map('intval', explode('-', $data['month']));
        $attendance = AttendanceMonth::query()
            ->where('resource_type', $data['resource_type'])
            ->where('resource_id', $resource->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $lines = ResourceEarningLine::query()
            ->with('appointment:id,month,day_num,time,lastname,phone,file_number,services,amount,original_amount,discount')
            ->where('month', $data['month'])
            ->where('resource_type', $data['resource_type'])
            ->where('resource_id', $resource->id)
            ->orderByRaw('COALESCE(day_num, 99)')
            ->orderBy('id')
            ->get();

        $activeLines = $lines->where('status', 'active');
        $sum = fn (array $types) => (float) $activeLines->whereIn('earning_type', $types)->sum('amount');
        $serviceCommission = $sum(['base_commission']);
        $inventoryCommission = $sum(['inventory_commission']);
        $salesBonus = $sum(['sales_bonus']);
        $overtime = $sum(['attendance_overtime']);
        $shortage = $sum(['attendance_shortage']);
        $absence = $sum(['attendance_absence']);
        $attendanceAdjustment = $overtime + $shortage + $absence;
        $salary = (float) ($resource->salary ?? 0);

        return response()->json([
            'resource' => $this->resourcePayload($data['resource_type'], $resource),
            'month' => $data['month'],
            'summary' => [
                'salary' => round($salary),
                'service_commission' => round($serviceCommission),
                'inventory_commission' => round($inventoryCommission),
                'sales_bonus' => round($salesBonus),
                'overtime' => round($overtime),
                'shortage' => round($shortage),
                'absence' => round($absence),
                'attendance_adjustment' => round($attendanceAdjustment),
                'total_commission' => round($serviceCommission + $inventoryCommission),
                'total_earned' => round($salary + $serviceCommission + $inventoryCommission + $salesBonus + $attendanceAdjustment),
            ],
            'attendance' => [
                'daily_hours' => (float) ($attendance?->daily_hours ?? 0),
                'days' => $attendance?->days ?? [],
            ],
            'lines' => $lines->values(),
        ]);
    }

    public function destroyLine(ResourceEarningLine $line): JsonResponse
    {
        $event = $this->auditEvent('delete', request(), $line);
        $line->update([
            'status' => 'deleted',
            'deleted_by_user_id' => request()->user()?->id,
            'deleted_by_name' => request()->user()?->name,
            'deleted_at' => now(),
            'audit_events' => [...($line->audit_events ?? []), $event],
        ]);

        return response()->json(['success' => true]);
    }

    public function restoreLine(Request $request, ResourceEarningLine $line): JsonResponse
    {
        $line->update([
            'status' => 'active',
            'deleted_by_user_id' => null,
            'deleted_by_name' => null,
            'deleted_at' => null,
            'audit_events' => [
                ...($line->audit_events ?? []),
                $this->auditEvent('restore', $request, $line),
            ],
        ]);

        return response()->json(['line' => $line->fresh('appointment')]);
    }

    public function updateLine(Request $request, ResourceEarningLine $line): JsonResponse
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'inventory_name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'gross_amount' => ['nullable', 'numeric'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'net_amount' => ['nullable', 'numeric'],
            'material_cost' => ['nullable', 'numeric', 'min:0'],
            'commission_base' => ['nullable', 'numeric'],
            'commission_type' => ['nullable', Rule::in(['percent', 'fixed', 'hourly', 'tier', 'absence'])],
            'commission_value' => ['nullable', 'numeric'],
            'amount' => ['required', 'numeric'],
        ]);

        $before = $line->only(array_keys($data));
        $snapshot = $line->calculation_snapshot ?? [];
        $snapshot['manual_edit'] = [
            'edited_at' => now()->toIso8601String(),
            'edited_by_user_id' => $request->user()?->id,
            'edited_by_name' => $request->user()?->name,
            'previous_amount' => (float) $line->amount,
            'previous_commission_base' => (float) $line->commission_base,
            'previous_commission_value' => (float) $line->commission_value,
        ];

        $line->fill([
            ...$data,
            'status' => 'active',
            'manually_edited' => true,
            'edited_by_user_id' => $request->user()?->id,
            'edited_by_name' => $request->user()?->name,
            'edited_at' => now(),
            'calculation_snapshot' => $snapshot,
            'audit_events' => [
                ...($line->audit_events ?? []),
                $this->auditEvent('edit', $request, $line, $before, $data),
            ],
        ])->save();

        return response()->json(['line' => $line->fresh('appointment')]);
    }

    private function resourcePayload(string $type, Doctor|Staff $resource): array
    {
        return [
            'type' => $type,
            'id' => $resource->id,
            'name' => $resource->name,
            'avatar_url' => $resource->avatar_url ?? null,
            'salary' => (float) ($resource->salary ?? 0),
            'bonus' => (float) ($resource->bonus ?? 0),
            'commission_customer_scope' => $resource->commission_customer_scope ?? 'both',
            'commission_after_materials' => (bool) ($resource->commission_after_materials ?? false),
            'sales_bonus_enabled' => (bool) ($resource->sales_bonus_enabled ?? false),
            'sales_bonus_tiers' => $resource->sales_bonus_tiers ?? [],
            'hourly_rate' => (float) ($resource->hourly_rate ?? 0),
            'overtime_hourly_rate' => (float) ($resource->overtime_hourly_rate ?? 0),
            'shortage_hourly_deduction' => (float) ($resource->shortage_hourly_deduction ?? 0),
            'absence_deduction' => (float) ($resource->absence_deduction ?? 0),
        ];
    }

    private function auditEvent(string $action, Request $request, ResourceEarningLine $line, array $before = [], array $after = []): array
    {
        return [
            'action' => $action,
            'at' => now()->toIso8601String(),
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
            'before' => $before ?: [
                'amount' => (float) $line->amount,
                'description' => $line->description,
                'commission_base' => (float) $line->commission_base,
                'commission_type' => $line->commission_type,
                'commission_value' => (float) $line->commission_value,
            ],
            'after' => $after,
        ];
    }
}
