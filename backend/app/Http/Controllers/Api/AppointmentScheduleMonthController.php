<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentScheduleMonth;
use Illuminate\Http\Request;

class AppointmentScheduleMonthController extends Controller
{
    public function index(Request $request)
    {
        $currentYear = $request->validate([
            'current_year' => ['nullable', 'regex:/^1[34]\d{2}$/'],
        ])['current_year'] ?? null;

        $appointmentMonths = Appointment::query()
            ->whereNotNull('month')
            ->where(fn ($query) => $query->whereNull('file_number')->orWhere('file_number', 'not like', 'LOADTEST-%'))
            ->where(function ($query) {
                foreach (['lastname', 'gender', 'phone', 'file_number', 'status', 'doctor', 'consultant', 'source', 'description', 'done'] as $column) {
                    $query->orWhere(fn ($field) => $field->whereNotNull($column)->where($column, '<>', ''));
                }
                $query->orWhere('amount', '>', 0)->orWhere('debt', '>', 0)->orWhere('discount', '>', 0);
            })
            ->distinct()->pluck('month');

        $explicitMonths = AppointmentScheduleMonth::query()
            // Empty manually-created months are useful in the current year,
            // but must not make an otherwise empty historical year visible.
            ->when($currentYear, fn ($query) => $query->where('month', 'like', $currentYear.'-%'))
            ->pluck('month');

        return response()->json($explicitMonths
            ->merge($appointmentMonths)
            ->filter(fn ($month) => preg_match('/^1[34]\d{2}-(0[1-9]|1[0-2])$/', (string) $month))
            ->unique()->sort()->values());
    }

    public function store(Request $request)
    {
        $data = $request->validate(['month' => ['required', 'regex:/^1[34]\d{2}-(0[1-9]|1[0-2])$/']]);
        $month = AppointmentScheduleMonth::query()->firstOrCreate($data);
        return response()->json($month, $month->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(string $month)
    {
        abort_unless(preg_match('/^1[34]\d{2}-(0[1-9]|1[0-2])$/', $month), 422);
        AppointmentScheduleMonth::query()->where('month', $month)->delete();
        return response()->noContent();
    }
}
