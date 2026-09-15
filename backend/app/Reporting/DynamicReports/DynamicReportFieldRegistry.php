<?php

namespace App\Reporting\DynamicReports;

use App\Reporting\DynamicReports\Resolvers\ReportFieldResolver;
use InvalidArgumentException;

final class DynamicReportFieldRegistry
{
    public function keys(): array
    {
        return array_keys(config('dynamic_reports.fields', []));
    }

    public function labels(): array
    {
        return config('dynamic_reports.fields', []);
    }

    public function label(string $field): string
    {
        return $this->labels()[$field] ?? $field;
    }

    public function resolve(string $field, ReportPatientContext $context): mixed
    {
        foreach (config('dynamic_reports.field_resolvers', []) as $resolverClass) {
            /** @var ReportFieldResolver $resolver */
            $resolver = app($resolverClass);
            if ($resolver->supports($field)) return $resolver->resolve($field, $context);
        }

        throw new InvalidArgumentException("No report field resolver is registered for [{$field}].");
    }
}
