<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceMonth;
use App\Models\Doctor;
use App\Models\ResourceEarningLine;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceMonthController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceMonth::query()->orderByDesc('year')->orderByDesc('month');

        if (! $request->user()->can('attendance.manage')) {
            $staffIds = Staff::query()->where('user_id', $request->user()->id)->pluck('id');
            $doctorIds = Doctor::query()->where('user_id', $request->user()->id)->pluck('id');
            $query->where(function ($query) use ($staffIds, $doctorIds) {
                $query
                    ->where(fn ($query) => $query->where('resource_type', 'staff')->whereIn('resource_id', $staffIds))
                    ->orWhere(fn ($query) => $query->where('resource_type', 'doctor')->whereIn('resource_id', $doctorIds));
            });
        }

        return response()->json(['months' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $this->assertClockPermissionForNewDays($request, $data['days']);

        if (($data['scope'] ?? null) === 'all') {
            abort_unless($request->user()->can('attendance.manage'), 403);

            $resources = collect()
                ->merge(Staff::query()->pluck('id')->map(fn ($id) => ['type' => 'staff', 'id' => $id]))
                ->merge(Doctor::query()->pluck('id')->map(fn ($id) => ['type' => 'doctor', 'id' => $id]));

            $months = DB::transaction(function () use ($resources, $data) {
                return $resources->map(function ($resource) use ($data) {
                    $month = AttendanceMonth::query()->firstOrCreate(
                        [
                            'resource_type' => $resource['type'],
                            'resource_id' => $resource['id'],
                            'year' => $data['year'],
                            'month' => $data['month'],
                        ],
                        [
                            'name' => $data['name'],
                            'daily_hours' => $data['daily_hours'] ?? 8,
                            'days' => $data['days'],
                        ]
                    );
                    $this->syncAttendanceEarningLines($month->fresh());

                    return $month;
                })->values();
            });

            return response()->json(['months' => $months], 201);
        }

        $this->assertResourceAccess($request, $data['resource_type'], (int) $data['resource_id']);

        $month = AttendanceMonth::query()->firstOrCreate(
            [
                'resource_type' => $data['resource_type'],
                'resource_id' => $data['resource_id'],
                'year' => $data['year'],
                'month' => $data['month'],
            ],
            [
                'name' => $data['name'],
                'daily_hours' => $data['daily_hours'] ?? 8,
                'days' => $data['days'],
            ]
        );
        $this->syncAttendanceEarningLines($month->fresh());

        return response()->json([
            'month' => $month->fresh(),
            'created' => $month->wasRecentlyCreated,
        ], $month->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, AttendanceMonth $attendanceMonth): JsonResponse
    {
        $this->assertResourceAccess(
            $request,
            $attendanceMonth->resource_type,
            (int) $attendanceMonth->resource_id
        );

        $data = $request->validate([
            'daily_hours' => ['sometimes', 'numeric', 'min:0', 'max:24'],
            'days' => ['required', 'array', 'max:31'],
            'days.*.day' => ['required', 'integer', 'min:1', 'max:31'],
            'days.*.in' => ['nullable', 'date_format:H:i'],
            'days.*.out' => ['nullable', 'date_format:H:i'],
            'days.*.workedHours' => ['nullable'],
            'days.*.diff' => ['nullable', 'numeric'],
            'days.*.amount' => ['nullable', 'numeric'],
            'days.*.employerApproved' => ['nullable', 'boolean'],
            'days.*.leaveRequestTitle' => ['nullable', 'string', 'max:250'],
            'days.*.leaveApproved' => ['nullable', 'boolean'],
            'days.*.absent' => ['nullable', 'boolean'],
        ]);

        $this->assertClockPermissionForChangedDays($request, $attendanceMonth, $data['days']);

        if (! $request->user()->can('attendance.manage')) {
            $storedDays = collect($attendanceMonth->days)->keyBy('day');
            foreach ($data['days'] as &$day) {
                $stored = $storedDays->get($day['day'], []);
                $day['employerApproved'] = (bool) ($stored['employerApproved'] ?? false);
                $day['leaveApproved'] = (bool) ($stored['leaveApproved'] ?? false);
            }
        }

        $attendanceMonth->update($data);
        $this->syncAttendanceEarningLines($attendanceMonth->fresh());

        return response()->json(['month' => $attendanceMonth->fresh()]);
    }

    public function destroy(Request $request, AttendanceMonth $attendanceMonth): JsonResponse
    {
        $this->assertResourceAccess(
            $request,
            $attendanceMonth->resource_type,
            (int) $attendanceMonth->resource_id,
            true
        );
        $this->deleteAttendanceEarningLines($attendanceMonth);
        $attendanceMonth->delete();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'scope' => ['nullable', Rule::in(['all'])],
            'resource_type' => ['required_unless:scope,all', Rule::in(['staff', 'doctor'])],
            'resource_id' => ['required_unless:scope,all', 'integer', 'min:1'],
            'year' => ['required', 'integer', 'min:1300', 'max:1600'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'name' => ['required', 'string', 'max:50'],
            'daily_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'days' => ['required', 'array', 'min:1', 'max:31'],
        ]);
    }

    private function assertResourceAccess(Request $request, string $type, int $id, bool $managerOnly = false): void
    {
        if ($request->user()->can('attendance.manage')) {
            return;
        }
        abort_if($managerOnly, 403);

        $model = $type === 'doctor' ? Doctor::query() : Staff::query();
        abort_unless($model->whereKey($id)->where('user_id', $request->user()->id)->exists(), 403);
    }

    private function assertClockPermissionForNewDays(Request $request, array $days): void
    {
        if ($request->user()->can('attendance.clock')) {
            return;
        }

        $containsClockValue = collect($days)->contains(
            fn (array $day) => filled($day['in'] ?? null) || filled($day['out'] ?? null)
        );

        abort_if($containsClockValue, 403, 'شما اجازه ثبت ورود و خروج را ندارید.');
    }

    private function assertClockPermissionForChangedDays(
        Request $request,
        AttendanceMonth $attendanceMonth,
        array $days
    ): void {
        if ($request->user()->can('attendance.clock')) {
            return;
        }

        $storedDays = collect($attendanceMonth->days)->keyBy('day');
        $clockChanged = collect($days)->contains(function (array $day) use ($storedDays) {
            $stored = $storedDays->get($day['day'], []);

            return ($day['in'] ?? null) !== ($stored['in'] ?? null)
                || ($day['out'] ?? null) !== ($stored['out'] ?? null);
        });

        abort_if($clockChanged, 403, 'شما اجازه ثبت ورود و خروج را ندارید.');
    }

    private function syncAttendanceEarningLines(AttendanceMonth $attendanceMonth): void
    {
        $this->deleteAttendanceEarningLines($attendanceMonth);

        $resource = $this->attendanceResource($attendanceMonth);
        if (! $resource) {
            return;
        }

        foreach (($attendanceMonth->days ?? []) as $day) {
            if (! is_array($day)) {
                continue;
            }

            $payload = $this->attendanceLinePayload($attendanceMonth, $resource, $day);
            if ($payload) {
                ResourceEarningLine::create($payload);
            }
        }
    }

    private function deleteAttendanceEarningLines(AttendanceMonth $attendanceMonth): void
    {
        ResourceEarningLine::query()
            ->whereNull('appointment_id')
            ->where('month', $this->ledgerMonth($attendanceMonth))
            ->where('resource_type', $attendanceMonth->resource_type)
            ->where('resource_id', $attendanceMonth->resource_id)
            ->whereIn('earning_type', ['attendance_overtime', 'attendance_shortage', 'attendance_absence'])
            ->delete();
    }

    private function attendanceLinePayload(AttendanceMonth $attendanceMonth, Doctor|Staff $resource, array $day): ?array
    {
        $dayNumber = (int) ($day['day'] ?? 0);
        if ($dayNumber <= 0) {
            return null;
        }

        $absent = (bool) ($day['absent'] ?? false);
        $leaveApproved = (bool) ($day['leaveApproved'] ?? false);
        $employerApproved = (bool) ($day['employerApproved'] ?? false);
        $workedHours = max(0, (float) ($day['workedHours'] ?? 0));
        $diff = (float) ($day['diff'] ?? 0);
        $amount = (float) ($day['amount'] ?? 0);
        $earningType = null;
        $rate = 0;
        $description = null;

        if ($absent && ! $leaveApproved) {
            $earningType = 'attendance_absence';
            $rate = (float) ($resource->absence_deduction ?? 0);
            $amount = $amount < 0 ? $amount : -$rate;
            $description = 'کسر عدم حضور روز '.$dayNumber;
        } elseif ($diff > 0 && $employerApproved) {
            $earningType = 'attendance_overtime';
            $rate = (float) ($resource->overtime_hourly_rate ?? 0);
            $amount = $amount > 0 ? $amount : round($diff * $rate);
            $description = 'اضافه‌کاری تاییدشده روز '.$dayNumber;
        } elseif ($diff < 0) {
            $earningType = 'attendance_shortage';
            $rate = (float) ($resource->shortage_hourly_deduction ?? 0);
            $amount = $amount < 0 ? $amount : -round(abs($diff) * $rate);
            $description = 'کسر کسری ساعت روز '.$dayNumber;
        }

        if (! $earningType || abs($amount) <= 0) {
            return null;
        }

        return [
            'appointment_id' => null,
            'month' => $this->ledgerMonth($attendanceMonth),
            'day_num' => $dayNumber,
            'earned_at' => now(),
            'resource_type' => $attendanceMonth->resource_type,
            'resource_id' => $attendanceMonth->resource_id,
            'resource_name' => $resource->name,
            'earning_type' => $earningType,
            'quantity' => $absent ? 1 : abs($diff),
            'gross_amount' => $amount,
            'net_amount' => $amount,
            'commission_base' => $absent ? 1 : abs($diff),
            'commission_type' => $absent ? 'absence' : 'hourly',
            'commission_value' => $rate,
            'amount' => $amount,
            'calculation_snapshot' => [
                'attendance_month_id' => $attendanceMonth->id,
                'daily_hours' => (float) $attendanceMonth->daily_hours,
                'worked_hours' => $workedHours,
                'diff_hours' => $diff,
                'in' => $day['in'] ?? null,
                'out' => $day['out'] ?? null,
                'employer_approved' => $employerApproved,
                'leave_request_title' => $day['leaveRequestTitle'] ?? null,
                'leave_approved' => $leaveApproved,
                'absent' => $absent,
                'overtime_hourly_rate' => (float) ($resource->overtime_hourly_rate ?? 0),
                'shortage_hourly_deduction' => (float) ($resource->shortage_hourly_deduction ?? 0),
                'absence_deduction' => (float) ($resource->absence_deduction ?? 0),
            ],
            'description' => $description,
        ];
    }

    private function attendanceResource(AttendanceMonth $attendanceMonth): Doctor|Staff|null
    {
        $model = $attendanceMonth->resource_type === 'doctor' ? Doctor::query() : Staff::query();

        return $model->find($attendanceMonth->resource_id);
    }

    private function ledgerMonth(AttendanceMonth $attendanceMonth): string
    {
        return sprintf('%04d-%02d', $attendanceMonth->year, $attendanceMonth->month);
    }
}
