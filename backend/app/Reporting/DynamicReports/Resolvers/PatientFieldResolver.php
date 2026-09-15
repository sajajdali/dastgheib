<?php

namespace App\Reporting\DynamicReports\Resolvers;

use App\Reporting\DynamicReports\ReportPatientContext;

class PatientFieldResolver implements ReportFieldResolver
{
    private const FIELDS = ['name', 'family', 'gender', 'phone', 'fileNo', 'city', 'birth', 'custseg', 'finstatus'];

    public function supports(string $field): bool { return in_array($field, self::FIELDS, true); }

    public function resolve(string $field, ReportPatientContext $context): mixed
    {
        $patient = $context->patient;
        return match ($field) {
            'name' => $patient->first_name,
            'family' => $patient->last_name,
            'gender' => match ($patient->gender) { 'female' => 'زن', 'male' => 'مرد', default => $patient->gender },
            'phone' => $patient->phone,
            'fileNo' => $patient->file_number,
            'city' => $patient->city,
            'birth' => $patient->birth_date ? str_replace('-', '/', (string) $patient->birth_date) : null,
            'custseg' => match ($context->customerLevel) {
                'blue' => 'آبی', 'silver' => 'نقره‌ای', 'gold' => 'طلایی', 'problematic' => 'دردسرساز',
                default => $context->customerLevel,
            },
            'finstatus' => $patient->financial_status,
        };
    }
}
