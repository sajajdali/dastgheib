<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Doctor;
use App\Models\EmployeePortalNotification;
use App\Models\ResourceEarningLine;
use App\Models\SalaryAdvance;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EmployeePortalService
{
    public function resourceForUser(User $user): array
    {
        $resource = Doctor::query()->where('user_id', $user->id)->first();
        if ($resource) return ['type' => 'doctor', 'model' => $resource];

        $resource = Staff::query()->where('user_id', $user->id)->first();
        abort_unless($resource, 404, 'حساب کاربری شما به پزشک یا پرسنل متصل نشده است.');

        return ['type' => 'staff', 'model' => $resource];
    }

    public function resource(string $type, int $id): Doctor|Staff
    {
        abort_unless(in_array($type, ['doctor', 'staff'], true), 422, 'نوع منبع معتبر نیست.');
        return $type === 'doctor' ? Doctor::query()->findOrFail($id) : Staff::query()->findOrFail($id);
    }

    /** @return array<int, string> */
    public function monthsBetween(string $from, string $to): array
    {
        [$year, $month] = array_map('intval', explode('-', substr($from, 0, 7)));
        [$toYear, $toMonth] = array_map('intval', explode('-', substr($to, 0, 7)));
        $months = [];
        while ($year < $toYear || ($year === $toYear && $month <= $toMonth)) {
            $months[] = sprintf('%04d-%02d', $year, $month);
            if (++$month > 12) { $month = 1; $year++; }
            abort_if(count($months) > 72, 422, 'بازه گزارش بیش از حد طولانی است.');
        }
        return $months;
    }

    public function addMonths(string $month, int $offset): string
    {
        [$year, $number] = array_map('intval', explode('-', $month));
        $index = $year * 12 + $number - 1 + $offset;
        return sprintf('%04d-%02d', intdiv($index, 12), $index % 12 + 1);
    }

    public function daysInMonth(int $year, int $month): int
    {
        if ($month <= 6) return 31;
        if ($month <= 11) return 30;
        return in_array(($year + 12) % 33, [1, 5, 9, 13, 17, 22, 26, 30], true) ? 30 : 29;
    }

    public function salaryForRange(Doctor|Staff $resource, string $from, string $to): float
    {
        $monthly = (float) ($resource->salary ?? 0);
        return collect($this->monthsBetween($from, $to))->sum(function (string $month) use ($from, $to, $monthly) {
            [$year, $number] = array_map('intval', explode('-', $month));
            $days = $this->daysInMonth($year, $number);
            $start = $month === substr($from, 0, 7) ? (int) substr($from, 8, 2) : 1;
            $end = $month === substr($to, 0, 7) ? (int) substr($to, 8, 2) : $days;
            return $monthly * max(0, $end - $start + 1) / $days;
        });
    }

    public function lineDate(ResourceEarningLine $line): ?string
    {
        if ($line->day_num) return sprintf('%s-%02d', $line->month, $line->day_num);
        return null;
    }

    public function linesForRange(string $type, int $id, string $from, string $to): Collection
    {
        $months = $this->monthsBetween($from, $to);
        return ResourceEarningLine::query()
            ->with('appointment:id,month,day_num,time,lastname,file_number,services,amount,original_amount,discount,new_customer')
            ->whereIn('month', $months)
            ->where('resource_type', $type)
            ->where('resource_id', $id)
            ->where('status', 'active')
            ->orderBy('month')->orderByRaw('COALESCE(day_num, 99)')->orderBy('id')->get()
            ->filter(function (ResourceEarningLine $line) use ($from, $to) {
                $date = $this->lineDate($line);
                return $date === null || ($date >= $from && $date <= $to);
            })->values();
    }

    public function reportColumns(Doctor|Staff $resource): array
    {
        $allowed = array_keys(config('employee_portal.columns', []));
        $columns = $resource->personal_report_columns;
        if (! is_array($columns) || $columns === []) {
            $stored = AppSetting::getByKey('employee_portal_default_columns');
            $columns = is_string($stored) ? json_decode($stored, true) : $stored;
        }
        if (! is_array($columns) || $columns === []) $columns = config('employee_portal.default_columns', []);
        return collect($columns)->filter(fn ($key) => in_array($key, $allowed, true))->unique()->values()->all();
    }

    public function syncAdvanceInstallments(SalaryAdvance $advance): void
    {
        $advance->installments()->delete();
        if (! $advance->deduct_from_salary || ! $advance->deduction_start_month || ! $advance->approved_amount || ! in_array($advance->status, ['approved', 'ready', 'paid', 'deducted'], true)) return;

        $count = max(1, min(36, (int) $advance->installment_count));
        $total = (int) round((float) $advance->approved_amount);
        $base = intdiv($total, $count);
        $remainder = $total - $base * $count;
        for ($index = 0; $index < $count; $index++) {
            $advance->installments()->create([
                'month' => $this->addMonths($advance->deduction_start_month, $index),
                'amount' => $base + ($index < $remainder ? 1 : 0),
                'status' => 'scheduled',
            ]);
        }
    }

    public function notifyEmployee(string $type, int $id, string $eventType, string $title, ?string $body, string $tab, array $data = []): void
    {
        $resource = $this->resource($type, $id);
        if (! $resource->user_id) return;
        EmployeePortalNotification::query()->create([
            'user_id' => $resource->user_id, 'resource_type' => $type, 'resource_id' => $id,
            'type' => $eventType, 'title' => $title, 'body' => $body, 'action_tab' => $tab, 'data' => $data,
        ]);
    }

    public function notifyManagers(string $type, int $id, string $eventType, string $title, ?string $body, string $tab, array $data = []): void
    {
        User::permission('employee_portal.manage')->pluck('id')->each(function (int $userId) use ($type, $id, $eventType, $title, $body, $tab, $data) {
            EmployeePortalNotification::query()->create([
                'user_id' => $userId, 'resource_type' => $type, 'resource_id' => $id,
                'type' => $eventType, 'title' => $title, 'body' => $body, 'action_tab' => $tab, 'data' => $data,
            ]);
        });
    }
}
