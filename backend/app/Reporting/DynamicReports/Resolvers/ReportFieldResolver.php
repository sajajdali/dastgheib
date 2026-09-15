<?php

namespace App\Reporting\DynamicReports\Resolvers;

use App\Reporting\DynamicReports\ReportPatientContext;

interface ReportFieldResolver
{
    public function supports(string $field): bool;
    public function resolve(string $field, ReportPatientContext $context): mixed;
}
