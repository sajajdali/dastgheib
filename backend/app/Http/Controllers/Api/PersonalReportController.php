<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AttendanceMonth;
use App\Models\Doctor;
use App\Models\ResourceEarningLine;
use App\Models\Staff;
use Illuminate\Http\Request;

class PersonalReportController extends Controller
{
    public function show(Request $request)
    {
        $data = $request->validate(['month' => ['required', 'regex:/^(13|14)\d{2}-(0[1-9]|1[0-2])$/']]);
        $resource = Doctor::query()->where('user_id', $request->user()->id)->first();
        $type = 'doctor';
        if (! $resource) {
            $resource = Staff::query()->where('user_id', $request->user()->id)->first();
            $type = 'staff';
        }
        abort_unless($resource, 404, 'این قسمت برای پزشکان و کارمندان می‌باشد؛ با حساب آن‌ها وارد شوید تا بتوانید گزارش را مشاهده کنید.');

        [$year, $month] = array_map('intval', explode('-', $data['month']));
        $attendance = AttendanceMonth::query()
            ->where('resource_type', $type)->where('resource_id', $resource->id)
            ->where('year', $year)->where('month', $month)->first();
        $days = collect($attendance?->days ?? []);
        $overtimeHours = $days->sum(fn ($day) => max(0, (float) ($day['diff'] ?? 0)));

        $appointments = Appointment::query()->where('month', $data['month'])->get();
        $appointmentsGiven = $appointments->filter(function ($appointment) use ($resource, $type) {
            if ($type === 'staff') {
                return trim((string) $appointment->consultant) === trim($resource->name)
                    || collect($appointment->services ?? [])->contains(fn ($service) => trim((string) ($service['consultant'] ?? '')) === trim($resource->name));
            }
            return trim((string) $appointment->doctor) === trim($resource->name)
                || collect($appointment->services ?? [])->contains(fn ($service) => trim((string) ($service['doctor'] ?? '')) === trim($resource->name));
        })->count();

        $earningLines = ResourceEarningLine::query()
            ->where('month', $data['month'])
            ->where('resource_type', $type)
            ->where('resource_id', $resource->id)
            ->get();
        $commission = (float) $earningLines
            ->whereIn('earning_type', ['base_commission', 'inventory_commission'])
            ->sum('amount');
        $salesBonus = (float) $earningLines->where('earning_type', 'sales_bonus')->sum('amount');
        $attendanceAdjustment = (float) $earningLines
            ->whereIn('earning_type', ['attendance_overtime', 'attendance_shortage', 'attendance_absence'])
            ->sum('amount');

        $salary = (float) ($resource->salary ?? 0);
        return response()->json([
            'month' => $data['month'],
            'name' => $resource->name,
            'type' => $type,
            'salary' => round($salary),
            'commission' => round($commission),
            'sales_bonus' => round($salesBonus),
            'attendance_adjustment' => round($attendanceAdjustment),
            'total_earned' => round($salary + $commission + $salesBonus + $attendanceAdjustment),
            'overtime_hours' => round($overtimeHours, 2),
            'appointments_given' => $appointmentsGiven,
            'earning_lines' => $earningLines->values(),
        ]);
    }
}
