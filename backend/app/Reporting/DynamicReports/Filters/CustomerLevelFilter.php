<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\ReportPatientContext;

class CustomerLevelFilter
{
    public function matches(ReportPatientContext $context, array $filters): bool
    {
        $selected = collect(data_get($filters, 'multi.custseg', []))->filter();
        return $selected->isEmpty() || $selected->contains($context->customerLevel);
    }
}
