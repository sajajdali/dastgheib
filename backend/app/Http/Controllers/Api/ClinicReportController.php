<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Services\ClinicReportService;

class ClinicReportController extends Controller
{
    public function dashboard(Request $request, ClinicReportService $reports)
    {
        return response()->json($reports->dashboard($reports->normalizeFilters($request->all())));
    }

    public function drilldown(Request $request, string $metric, ClinicReportService $reports)
    {
        return response()->json($reports->drilldown($metric, $reports->normalizeFilters($request->all())));
    }

    public function export(Request $request, ClinicReportService $reports)
    {
        $data = $reports->dashboard($reports->normalizeFilters($request->all()));
        $rows = collect($data['kpis'])->map(fn ($value, $key) => [$key, $value])->prepend(['شاخص', 'مقدار']);
        return response($rows->map(fn ($row) => implode(',', $row))->implode("\n"), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="clinic-report.csv"']);
    }
    public function cancellationRate(Request $request)
    {
        $fromInput = (string) $request->query('from', '');
        $toInput = (string) $request->query('to', '');
        $from = $this->normalizeDate($fromInput);
        $to = $this->normalizeDate($toInput);

        if (($fromInput && ! $from) || ($toInput && ! $to) || ($from && ! $to) || (! $from && $to) || ($from && $to && $from > $to)) {
            return response()->json(['message' => 'بازهٔ تاریخ گزارش معتبر نیست.'], 422);
        }

        $appointments = Appointment::query()
            ->when($from, fn (Builder $query) => $this->afterOrOn($query, $from))
            ->when($to, fn (Builder $query) => $this->beforeOrOn($query, $to));

        // نرخ کنسلی فقط بین نتیجه‌های قطعی نوبت محاسبه می‌شود.
        // «پاسخ نداد» نیز طبق قرارداد گزارش، کنسلی محسوب می‌شود.
        $eligible = (clone $appointments)->where(function (Builder $query) {
            $this->cancelledStatus($query)->orWhere('status', 'like', '%آمد%')->orWhere('status', 'like', '%پاسخ نداد%');
        });
        $total = (clone $eligible)->count();
        $cancelled = (clone $eligible)->where(fn (Builder $query) => $this->cancelledStatus($query)->orWhere('status', 'like', '%پاسخ نداد%'))->count();
        $attended = (clone $eligible)->where('status', 'like', '%آمد%')->count();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'total' => $total,
            'cancelled' => $cancelled,
            'attended' => $attended,
            'rate' => $total ? round(($cancelled / $total) * 100, 1) : 0,
        ]);
    }

    private function cancelledStatus(Builder $query): Builder
    {
        return $query->where('status', 'like', '%کنسل%')
            ->orWhere('status', 'like', '%لغو%')
            ->orWhere('status', 'like', '%Ú©Ù†Ø³Ù„%');
    }

    private function normalizeDate(string $value): ?string
    {
        $value = strtr(trim($value), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '/' => '-']);

        return preg_match('/^1[34]\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value) ? $value : null;
    }

    private function afterOrOn(Builder $query, string $date): Builder
    {
        [$month, $day] = [substr($date, 0, 7), (int) substr($date, 8, 2)];

        return $query->where(fn (Builder $q) => $q->where('month', '>', $month)
            ->orWhere(fn (Builder $q) => $q->where('month', $month)->where('day_num', '>=', $day)));
    }

    private function beforeOrOn(Builder $query, string $date): Builder
    {
        [$month, $day] = [substr($date, 0, 7), (int) substr($date, 8, 2)];

        return $query->where(fn (Builder $q) => $q->where('month', '<', $month)
            ->orWhere(fn (Builder $q) => $q->where('month', $month)->where('day_num', '<=', $day)));
    }
}
