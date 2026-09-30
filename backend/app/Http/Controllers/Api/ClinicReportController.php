<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Expense;
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

        // فقط نتیجه‌های قطعی «آمد»، «کنسل شد» و «پاسخ نداد» در نرخ دخیل‌اند.
        // «پاسخ نداد» در این گزارش جزو کنسلی‌ها محسوب می‌شود.
        $eligible = (clone $appointments)->whereIn('status', ['آمد', 'کنسل شد', 'پاسخ نداد']);
        $total = (clone $eligible)->count();
        $cancelled = (clone $eligible)->whereIn('status', ['کنسل شد', 'پاسخ نداد'])->count();
        $noAnswer = (clone $eligible)->where('status', 'پاسخ نداد')->count();
        $attended = (clone $eligible)->where('status', 'آمد')->count();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'total' => $total,
            'cancelled' => $cancelled,
            'no_answer' => $noAnswer,
            'attended' => $attended,
            'rate' => $total ? round(($cancelled / $total) * 100, 1) : 0,
            'calculated_at' => now()->toIso8601String(),
        ]);
    }

    public function expenses(Request $request)
    {
        $from = $this->normalizeDate((string) $request->query('from', ''));
        $to = $this->normalizeDate((string) $request->query('to', ''));
        if (! $from || ! $to || $from > $to) {
            return response()->json(['message' => 'بازهٔ تاریخ گزارش معتبر نیست.'], 422);
        }

        $query = Expense::query()
            ->where('type', 'expense')
            ->whereBetween('occurred_on', [$from, $to]);
        $items = (clone $query)
            ->selectRaw('category, SUM(amount) AS amount')
            ->groupBy('category')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'category' => trim((string) $row->category) ?: 'بدون دسته‌بندی',
                'amount' => (float) $row->amount,
            ])
            ->groupBy('category')
            ->map(fn ($rows, $category) => [
                'category' => $category,
                'amount' => round((float) $rows->sum('amount')),
            ])
            ->sortByDesc('amount')
            ->values();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'total' => round((float) (clone $query)->sum('amount')),
            'items' => $items,
            'calculated_at' => now()->toIso8601String(),
        ]);
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
