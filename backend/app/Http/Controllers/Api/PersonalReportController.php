<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AttendanceMonth;
use App\Models\Doctor;
use App\Models\Inventory;
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
        abort_unless($resource, 404, 'حساب شما هنوز به پزشک یا پرسنل متصل نشده است.');

        [$year, $month] = array_map('intval', explode('-', $data['month']));
        $attendance = AttendanceMonth::query()
            ->where('resource_type', $type)->where('resource_id', $resource->id)
            ->where('year', $year)->where('month', $month)->first();
        $days = collect($attendance?->days ?? []);
        $overtimeHours = $days->sum(fn ($day) => max(0, (float) ($day['diff'] ?? 0)));
        $attendanceAdjustment = $days->sum(fn ($day) => (float) ($day['amount'] ?? 0));

        $appointments = Appointment::query()->where('month', $data['month'])->get();
        $appointmentsGiven = $appointments->filter(function ($appointment) use ($resource, $type) {
            if ($type === 'staff') {
                return trim((string) $appointment->consultant) === trim($resource->name)
                    || collect($appointment->services ?? [])->contains(fn ($service) => trim((string) ($service['consultant'] ?? '')) === trim($resource->name));
            }
            return trim((string) $appointment->doctor) === trim($resource->name)
                || collect($appointment->services ?? [])->contains(fn ($service) => trim((string) ($service['doctor'] ?? '')) === trim($resource->name));
        })->count();

        $inventory = Inventory::query()->get()->keyBy('name');
        $commission = 0;
        foreach ($appointments as $appointment) {
            foreach ($appointment->services ?? [] as $service) {
                $matches = $type === 'doctor'
                    ? trim((string) ($service['doctor'] ?? '')) === trim($resource->name)
                    : trim((string) ($service['consultant'] ?? '')) === trim($resource->name);
                if (! $matches) continue;
                $item = $inventory->get($service['name'] ?? '');
                $quantity = (float) ($service['cc'] ?? 0);
                if (! $item || $quantity <= 0) continue;
                $revenue = (float) $item->amount * $quantity;
                $materials = (float) $item->price * $quantity;
                $base = $resource->commission_after_materials ? max(0, $revenue - $materials) : $revenue;
                $commission += $base * ((float) $resource->bonus / 100);
            }
        }

        $salary = (float) ($resource->salary ?? 0);
        return response()->json([
            'month' => $data['month'],
            'name' => $resource->name,
            'type' => $type,
            'salary' => round($salary),
            'commission' => round($commission),
            'attendance_adjustment' => round($attendanceAdjustment),
            'total_earned' => round($salary + $commission + $attendanceAdjustment),
            'overtime_hours' => round($overtimeHours, 2),
            'appointments_given' => $appointmentsGiven,
        ]);
    }
}
