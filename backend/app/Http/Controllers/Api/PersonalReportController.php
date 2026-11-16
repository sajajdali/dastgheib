<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceMonth;
use App\Models\EmployeeLeaveRequest;
use App\Models\ResourceAdjustment;
use App\Models\ResourceEarningLine;
use App\Models\SalaryAdvanceInstallment;
use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalReportController extends Controller
{
    public function __construct(private readonly EmployeePortalService $portal) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'regex:/^(13|14)\d{2}-(0[1-9]|1[0-2])$/'],
            'from' => ['nullable', 'required_without:month', 'regex:/^(13|14)\d{2}-(0[1-9]|1[0-2])-([0-2]\d|3[01])$/'],
            'to' => ['nullable', 'required_without:month', 'regex:/^(13|14)\d{2}-(0[1-9]|1[0-2])-([0-2]\d|3[01])$/', 'after_or_equal:from'],
        ]);
        ['type' => $type, 'model' => $resource] = $this->portal->resourceForUser($request->user());
        [$from, $to] = $this->range($data);
        $months = $this->portal->monthsBetween($from, $to);
        $lines = $this->portal->linesForRange($type, $resource->id, $from, $to);

        $sum = fn (array $types) => (float) $lines->whereIn('earning_type', $types)->sum('amount');
        $serviceCommission = $sum(['base_commission']);
        $inventoryCommission = $sum(ResourceEarningLine::INVENTORY_COMMISSION_TYPES);
        $referralCommission = $sum([ResourceEarningLine::REFERRAL_TYPE]);
        $salesBonus = $sum(['sales_bonus']);
        $overtime = $sum(['attendance_overtime']);
        $shortage = $sum(['attendance_shortage']);
        $absence = $sum(['attendance_absence']);

        $adjustments = ResourceAdjustment::query()
            ->whereIn('month', $months)->where('resource_type', $type)->where('resource_id', $resource->id)
            ->where('status', 'active')->orderBy('occurred_on')->orderBy('id')->get()
            ->filter(fn ($row) => ! $row->occurred_on || ($row->occurred_on >= $from && $row->occurred_on <= $to))->values();
        $manualBonus = (float) $adjustments->where('amount', '>', 0)->sum('amount');
        $manualDeduction = (float) $adjustments->where('amount', '<', 0)->sum('amount');

        $advanceInstallments = SalaryAdvanceInstallment::query()->with('advance')
            ->whereIn('month', $months)
            ->whereHas('advance', fn ($query) => $query->where('resource_type', $type)->where('resource_id', $resource->id)->whereIn('status', ['paid', 'deducted']))
            ->orderBy('month')->get();
        $advanceDeduction = -(float) $advanceInstallments->sum('amount');
        $salary = $this->portal->salaryForRange($resource, $from, $to);
        $total = $salary + $serviceCommission + $inventoryCommission + $referralCommission + $salesBonus
            + $overtime + $shortage + $absence + $manualBonus + $manualDeduction + $advanceDeduction;

        $leaves = EmployeeLeaveRequest::query()->where('resource_type', $type)->where('resource_id', $resource->id)
            ->where('start_date', '<=', $to)->where('end_date', '>=', $from)->orderByDesc('created_at')->get();

        $summary = [
            'salary' => round($salary), 'service_commission' => round($serviceCommission),
            'inventory_commission' => round($inventoryCommission), 'referral_commission' => round($referralCommission),
            'sales_bonus' => round($salesBonus), 'manual_bonus' => round($manualBonus),
            'overtime' => round($overtime), 'shortage' => round($shortage), 'absence' => round($absence),
            'manual_deduction' => round($manualDeduction), 'advance_deduction' => round($advanceDeduction),
            'total_earned' => round($total),
        ];

        return response()->json([
            // Legacy top-level fields are kept for clients that have not yet moved to `summary`.
            'month' => count($months) === 1 ? $months[0] : null,
            'name' => $resource->name,
            'type' => $type,
            'salary' => $summary['salary'],
            'commission' => $summary['service_commission'] + $summary['inventory_commission'],
            'referral_commission' => $summary['referral_commission'],
            'sales_bonus' => $summary['sales_bonus'],
            'attendance_adjustment' => $summary['overtime'] + $summary['shortage'] + $summary['absence'],
            'total_earned' => $summary['total_earned'],
            'range' => ['from' => $from, 'to' => $to, 'months' => $months],
            'resource' => ['type' => $type, 'id' => $resource->id, 'name' => $resource->name, 'avatar_url' => $resource->avatar_url],
            'columns' => $this->portal->reportColumns($resource),
            'column_options' => config('employee_portal.columns'),
            'summary' => $summary,
            'commission_lines' => $lines->whereIn('earning_type', [
                ...ResourceEarningLine::COMMISSION_TYPES, ResourceEarningLine::REFERRAL_TYPE,
            ])->map(fn (ResourceEarningLine $line) => $this->commissionLine($line, $type))->values(),
            'attendance' => $this->attendance($type, $resource->id, $from, $to),
            'adjustments' => $adjustments,
            'leave_requests' => $leaves,
            'advance_installments' => $advanceInstallments,
        ]);
    }

    private function range(array $data): array
    {
        if (! empty($data['month'])) {
            [$year, $month] = array_map('intval', explode('-', $data['month']));
            return [$data['month'].'-01', sprintf('%s-%02d', $data['month'], $this->portal->daysInMonth($year, $month))];
        }
        return [$data['from'], $data['to']];
    }

    private function commissionLine(ResourceEarningLine $line, string $resourceType): array
    {
        $appointment = $line->appointment;
        $rate = match ($line->commission_type) {
            'percent' => rtrim(rtrim(number_format((float) $line->commission_value, 2, '.', ''), '0'), '.').'٪',
            'fixed' => number_format((float) $line->commission_value).' تومان ثابت',
            default => (string) $line->commission_value,
        };
        return [
            'id' => $line->id,
            'date' => $line->day_num ? sprintf('%s-%02d', $line->month, $line->day_num) : $line->month,
            'service' => $line->service_name ?: $line->inventory_name ?: 'پورسانت',
            'patient_name' => $appointment?->lastname,
            'file_number' => $appointment?->file_number,
            'quantity' => (float) $line->quantity,
            'gross_amount' => (float) $line->gross_amount,
            'discount_amount' => (float) $line->discount_amount,
            'net_amount' => (float) $line->net_amount,
            'commission_base' => (float) $line->commission_base,
            'commission_rate' => $rate,
            'commission_amount' => (float) $line->amount,
            'customer_type' => $line->appointment_new_customer ? 'جدید' : 'قدیمی',
            'role' => $line->earning_type === ResourceEarningLine::REFERRAL_TYPE ? 'معرف' : ($resourceType === 'doctor' ? 'پزشک' : 'پرسنل / مشاور'),
            'description' => $line->description,
            'earning_type' => $line->earning_type,
            'calculation_snapshot' => $line->calculation_snapshot,
        ];
    }

    private function attendance(string $type, int $id, string $from, string $to): array
    {
        $financialLines = ResourceEarningLine::query()
            ->where('resource_type', $type)->where('resource_id', $id)->where('status', 'active')
            ->whereIn('month', $this->portal->monthsBetween($from, $to))
            ->whereIn('earning_type', ['attendance_overtime', 'attendance_shortage', 'attendance_absence'])
            ->get()->keyBy(fn (ResourceEarningLine $line) => $line->day_num ? sprintf('%s-%02d', $line->month, $line->day_num) : '');
        $months = AttendanceMonth::query()->where('resource_type', $type)->where('resource_id', $id)
            ->get()->filter(fn ($row) => in_array(sprintf('%04d-%02d', $row->year, $row->month), $this->portal->monthsBetween($from, $to), true))
            ->sortBy(fn ($row) => sprintf('%04d-%02d', $row->year, $row->month));
        $rows = $months->flatMap(function (AttendanceMonth $month) {
            return collect($month->days ?? [])->map(function ($day) use ($month) {
                $date = sprintf('%04d-%02d-%02d', $month->year, $month->month, (int) ($day['day'] ?? 0));
                return [
                    'date' => $date, 'in' => $day['in'] ?? null, 'out' => $day['out'] ?? null,
                    'daily_hours' => (float) $month->daily_hours, 'worked_hours' => (float) ($day['workedHours'] ?? 0),
                    'diff' => (float) ($day['diff'] ?? 0), 'amount' => (float) ($day['amount'] ?? 0),
                    'absent' => (bool) ($day['absent'] ?? false), 'leave_approved' => (bool) ($day['leaveApproved'] ?? false),
                    'leave_title' => $day['leaveRequestTitle'] ?? null, 'employer_approved' => (bool) ($day['employerApproved'] ?? false),
                ];
            });
        })->filter(fn ($row) => $row['date'] >= $from && $row['date'] <= $to)
            ->map(function (array $row) use ($financialLines) {
                $line = $financialLines->get($row['date']);
                $row['rate'] = (float) ($line?->commission_value ?? 0);
                $row['amount'] = (float) ($line?->amount ?? 0);
                $row['earning_type'] = $line?->earning_type;
                return $row;
            })->values();

        return [
            'days' => $rows,
            'totals' => [
                'worked_hours' => round((float) $rows->sum('worked_hours'), 2),
                'overtime_hours' => round((float) $rows->where('diff', '>', 0)->sum('diff'), 2),
                'shortage_hours' => round(abs((float) $rows->where('diff', '<', 0)->sum('diff')), 2),
                'absence_days' => $rows->where('absent', true)->where('leave_approved', false)->count(),
            ],
        ];
    }
}
