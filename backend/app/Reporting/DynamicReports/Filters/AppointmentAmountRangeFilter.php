<?php
namespace App\Reporting\DynamicReports\Filters;
use Illuminate\Database\Eloquent\Builder;
final class AppointmentAmountRangeFilter implements DynamicReportQueryFilter
{
    public function apply(Builder $query, array $filters): void
    {
        $range = data_get($filters, 'range.amount', []);
        $from = $this->number($range['from'] ?? null);
        $to = $this->number($range['to'] ?? null);
        if ($from === null && $to === null) return;
        $query->whereExists(function ($appointments) use ($from, $to) {
            $amount = "CAST(REPLACE(REPLACE(REPLACE(COALESCE(amount, '0'), ',', ''), '٬', ''), ' ', '') AS DECIMAL(18,2))";
            $appointments->selectRaw('1')->from('appointments as amount_appointments')
                ->where(function ($link) {
                    $link->where(fn ($q) => $q->whereNotNull('patients.file_number')->where('patients.file_number', '<>', '')->whereColumn('amount_appointments.file_number', 'patients.file_number'))
                        ->orWhere(fn ($q) => $q->where(fn ($m) => $m->whereNull('patients.file_number')->orWhere('patients.file_number', ''))->whereNotNull('patients.phone')->where('patients.phone', '<>', '')->whereColumn('amount_appointments.phone', 'patients.phone'));
                });
            if ($from !== null) $appointments->whereRaw($amount.' >= ?', [$from]);
            if ($to !== null) $appointments->whereRaw($amount.' <= ?', [$to]);
        });
    }
    private function number(mixed $value): ?float
    {
        $value = strtr(trim((string) $value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        $value = str_replace([',', '٬', ' '], '', $value);
        return $value !== '' && is_numeric($value) ? (float) $value : null;
    }
}
