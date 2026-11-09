<?php

namespace App\Reporting\DynamicReports\Resolvers;

use App\Reporting\DynamicReports\ReportCommissionTotals;
use App\Reporting\DynamicReports\ReportPatientContext;

/** پورسانت پزشک و مشاورِ نوبت ردیف (در گزارش پرونده‌ها: آخرین نوبت بازه)، از دفتر حقوق. */
class CommissionFieldResolver implements ReportFieldResolver
{
    public function supports(string $field): bool { return array_key_exists($field, ReportCommissionTotals::RESOURCE_TYPES); }

    public function resolve(string $field, ReportPatientContext $context): mixed
    {
        return ReportCommissionTotals::forAppointment($context->latestAppointment?->id, ReportCommissionTotals::RESOURCE_TYPES[$field]);
    }
}
