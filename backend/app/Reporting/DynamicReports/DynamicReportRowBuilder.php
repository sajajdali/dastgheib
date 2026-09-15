<?php

namespace App\Reporting\DynamicReports;

final class DynamicReportRowBuilder
{
    public function __construct(private readonly DynamicReportFieldRegistry $fields) {}

    public function build(array $columns, ReportPatientContext $context): array
    {
        $payload = [];
        foreach ($columns as $column) {
            $value = $this->fields->resolve($column, $context);
            $payload[$column] = $value === null ? '—' : $this->formatValue($column, $value);
        }
        return $payload;
    }

    private function formatValue(string $column, mixed $value): mixed
    {
        if (! in_array($column, ['amount', 'discount', 'debt', 'deposit', 'count', 'salary', 'overtime'], true)) return $value;
        $raw = str_replace([',', '٬', ' '], '', (string) $value);
        if (! is_numeric($raw)) return $value;
        $number = (float) $raw;
        return number_format($number, fmod($number, 1) ? 2 : 0, '.', ',');
    }
}
