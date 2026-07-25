<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceMonth;
use App\Models\Doctor;
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
                return $resources->map(fn ($resource) => AttendanceMonth::query()->firstOrCreate(
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
                ))->values();
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
}
