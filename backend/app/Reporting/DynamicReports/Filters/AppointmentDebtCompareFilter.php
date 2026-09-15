<?php
namespace App\Reporting\DynamicReports\Filters;
final class AppointmentDebtCompareFilter extends AppointmentMoneyCompareFilter { protected function key(): string{return 'debt';} protected function expression(string $alias): string{return "CAST(REPLACE(REPLACE(REPLACE(COALESCE({$alias}.debt,'0'),',',''),'٬',''),' ','') AS DECIMAL(18,2))";} }
