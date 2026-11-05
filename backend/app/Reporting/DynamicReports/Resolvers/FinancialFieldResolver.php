<?php

namespace App\Reporting\DynamicReports\Resolvers;

use App\Models\AppointmentFinancialTransaction;
use App\Models\Inventory;
use App\Models\InventoryAddonDefinition;
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
            'deposit' => $appointment ? $this->money(AppointmentFinancialTransaction::query()
                ->where('appointment_id', $appointment->id)->where('type', 'deposit')->whereNull('voided_at')->sum('amount')) : null,
            'payment' => $appointment?->payment_method,
            'account' => $appointment?->payment_account,
        };
    }

    private function incomeAndExpense(mixed $appointment): ?string
    {
        if (! $appointment) return null;
        // همان تعریف گزارش کلینیک: مبلغ نوبت بعد از تخفیف + کیف پول، و هزینهٔ مواد خدمات و جانبی‌ها از انبار.
        $income = $this->number($appointment->amount ?? 0) + max(0, $this->number($appointment->wallet_applied ?? 0));
        $expense = collect(is_array($appointment->services) ? $appointment->services : [])
            ->filter(fn ($service) => is_array($service))
            ->sum(fn (array $service) => $this->materialCost($service));
        return 'درآمد: '.number_format($income, 0, '.', '٬').' · هزینه: '.number_format((float) $expense, 0, '.', '٬');
    }

    private function materialCost(array $service): float
    {
        static $inventory = null, $addons = null;
        $inventory ??= Inventory::query()->pluck('price', 'name');
        $addons ??= InventoryAddonDefinition::query()->pluck('price', 'id');
        $quantity = max(0, $this->number(array_key_exists('cc', $service) ? ($service['cc'] ?? 0) : ($service['quantity'] ?? 1)));
        $unit = ! empty($service['addon_definition_id'])
            ? (float) ($addons[(int) $service['addon_definition_id']] ?? 0)
            : (float) ($inventory[trim((string) ($service['name'] ?? ''))] ?? 0);

        return $unit * $quantity + collect($service['addons'] ?? [])->filter(fn ($addon) => is_array($addon))->sum(fn (array $addon) => $this->materialCost($addon));
    }

    private function number(mixed $value): float
    {
        return (float) str_replace([',', '٬', ' '], '', (string) $value);
    }

    private function money(mixed $value): ?string
    {
        $number = $this->number($value);
        return $number > 0 ? number_format($number, 0, '.', '٬') : '0';
    }
}
