<?php

namespace App\Reporting\DynamicReports\Resolvers;

use App\Reporting\DynamicReports\ReportPatientContext;

class ReferrerFieldResolver implements ReportFieldResolver
{
    public function supports(string $field): bool { return $field === 'referrer'; }

    public function resolve(string $field, ReportPatientContext $context): mixed
    {
        $values = $context->appointmentHistory->pluck('referrer_phone')->filter()->unique()->map(function ($phone) use ($context) {
            $referrer = $context->referrers->get($phone);
            $name = $referrer ? trim((string) $referrer->first_name.' '.(string) $referrer->last_name) : '';
            return $name !== '' ? "{$name} ({$phone})" : (string) $phone;
        })->values();
        return $values->isEmpty() ? null : $values->implode('، ');
    }
}
