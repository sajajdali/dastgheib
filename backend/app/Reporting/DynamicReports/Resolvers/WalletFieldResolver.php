<?php

namespace App\Reporting\DynamicReports\Resolvers;

use App\Reporting\DynamicReports\ReportPatientContext;
use App\Reporting\DynamicReports\ReportWalletTotals;

/** جمع واریز و برداشت کیف پول بیمار در بازهٔ گزارش (برای مراجع بدون پرونده خالی است). */
class WalletFieldResolver implements ReportFieldResolver
{
    public function supports(string $field): bool { return array_key_exists($field, ReportWalletTotals::TYPES); }

    public function resolve(string $field, ReportPatientContext $context): mixed
    {
        $patient = $context->patient;
        return ReportWalletTotals::forPatient($patient->exists ? $patient->getKey() : null, ReportWalletTotals::TYPES[$field], ['reportDate' => $context->reportDate]);
    }
}
