<?php
namespace App\Reporting\DynamicReports\Filters;
final class AppointmentDepositCompareFilter extends AppointmentMoneyCompareFilter { protected function key(): string{return 'deposit';} protected function expression(string $alias): string{return "CAST(REPLACE(REPLACE(REPLACE(COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$alias}.payment_details,'$.deposit')),'0'),',',''),'٬',''),' ','') AS DECIMAL(18,2))";} }
