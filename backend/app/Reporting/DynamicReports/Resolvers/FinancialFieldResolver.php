<?php

namespace App\Reporting\DynamicReports\Resolvers;

use App\Models\ResourceEarningLine;
use App\Reporting\DynamicReports\ReportPatientContext;

class FinancialFieldResolver implements ReportFieldResolver
{
    private const FIELDS = ['amount', 'income', 'discount', 'debt', 'deposit', 'payment', 'account'];

    public function supports(string $field): bool { return in_array($field, self::FIELDS, true); }

    public function resolve(string $field, ReportPatientContext $context): mixed
    {
        $appointment = $context->latestAppointment;
        return match ($field) {
            'amount' => $appointment ? $this->money($appointment->amount) : null,
            'income' => $this->incomeAndExpense($appointment),
            'discount' => $appointment ? $this->money($appointment->discount) : null,
            'debt' => $appointment ? $this->money($appointment->debt) : null,
            'deposit' => $appointment ? $this->money(data_get($appointment?->payment_details, 'deposit')) : null,
            'payment' => $appointment?->payment_method,
            'account' => $appointment?->payment_account,
        };
    }

    private function incomeAndExpense(mixed $appointment): ?string
    {
        if (! $appointment) return null;
        $income = $this->number($appointment->amount ?? $appointment->original_amount ?? 0);
        $expense = ResourceEarningLine::query()->where('appointment_id', $appointment->id)->sum('material_cost');
        return 'درآمد: '.number_format($income, 0, '.', '٬').' · هزینه: '.number_format((float) $expense, 0, '.', '٬');
    }

    private function number(mixed $value): float
    {
        return (float) str_replace([',', '٬', ' '], '', (string) $value);
    }

    private function money(mixed $value): ?string
    {
        $number = $this->number($value);
        return $number > 0 ? number_format($number, 0, '.', '٬') : '۰';
    }
}
