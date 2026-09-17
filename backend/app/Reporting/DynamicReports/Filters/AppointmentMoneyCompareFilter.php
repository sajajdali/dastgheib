<?php
namespace App\Reporting\DynamicReports\Filters;
use Illuminate\Database\Eloquent\Builder;
abstract class AppointmentMoneyCompareFilter implements DynamicReportQueryFilter
{
    abstract protected function key(): string;
    abstract protected function expression(string $alias): string;
    public function apply(Builder $query, array $filters): void
    {
        $value = data_get($filters, 'values.'.$this->key());
        $operator = data_get($filters, 'comparators.'.$this->key(), 'eq');
        $number = $this->number($value);
        if ($number === null) return;
        $op = ['lt' => '<', 'gt' => '>', 'eq' => '='][$operator] ?? '=';
        $query->whereExists(function ($appointments) use ($number, $op, $filters) {
            $alias = 'money_'.$this->key();
            $appointments->selectRaw('1')->from('appointments as '.$alias)
                ->whereRaw($this->expression($alias).' '.$op.' ?', [$number])
                ->where(function ($link) {
                    $link->where(fn ($q) => $q->whereNotNull('patients.file_number')->where('patients.file_number', '<>', '')->whereColumn('money_'.$this->key().'.file_number', 'patients.file_number'))
                        ->orWhere(fn ($q) => $q->where(fn ($m) => $m->whereNull('patients.file_number')->orWhere('patients.file_number', ''))->whereNotNull('patients.phone')->where('patients.phone', '<>', '')->whereColumn('money_'.$this->key().'.phone', 'patients.phone'));
                });
            AppointmentReportDate::constrain($appointments, $alias, $filters);
        });
    }
    private function number(mixed $value): ?float { $v=strtr(trim((string)$value),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']); $v=str_replace([',','٬',' '],'',$v); return $v!==''&&is_numeric($v)?(float)$v:null; }
}
