<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Patient;
use App\Reporting\DynamicReports\DynamicReportFieldRegistry;
use App\Reporting\DynamicReports\ReportPatientContext;
use App\Reporting\DynamicReports\Resolvers\ReportFieldResolver;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DynamicReportFieldsTest extends TestCase
{
    public function test_it_maps_selected_patient_and_financial_fields(): void
    {
        $patient = new Patient([
            'first_name' => 'سارا',
            'last_name' => 'محمدی',
            'gender' => 'female',
            'phone' => '09121234567',
            'file_number' => '1042',
            'city' => 'تهران',
            'financial_status' => 'بدهکار',
        ]);
        $appointment = new Appointment([
            'payment_method' => 'کارت‌خوان',
            'month' => '1405-07',
            'day_num' => 12,
            'created_at' => Carbon::create(2024, 3, 20),
        ]);
        $context = new ReportPatientContext($patient, $appointment, collect([$appointment]), collect(), 'gold');
        $fields = app(DynamicReportFieldRegistry::class);

        $this->assertSame('سارا', $fields->resolve('name', $context));
        $this->assertSame('محمدی', $fields->resolve('family', $context));
        $this->assertSame('بدهکار', $fields->resolve('finstatus', $context));
        $this->assertSame('طلایی', $fields->resolve('custseg', $context));
        $this->assertSame('زن', $fields->resolve('gender', $context));
        $this->assertSame('09121234567', $fields->resolve('phone', $context));
        $this->assertSame('1042', $fields->resolve('fileNo', $context));
        $this->assertSame('تهران', $fields->resolve('city', $context));
        $this->assertSame('کارت‌خوان', $fields->resolve('payment', $context));
        $this->assertSame('1405/07/12', $fields->resolve('appointmentDate', $context));
        $this->assertSame('1403/01/01', $fields->resolve('appointmentCreatedDate', $context));
    }

    public function test_every_builder_field_is_whitelisted(): void
    {
        $fields = app(DynamicReportFieldRegistry::class);
        $this->assertCount(33, $fields->keys());
        $this->assertSame($fields->keys(), array_keys($fields->labels()));

        foreach (config('dynamic_reports.field_resolvers') as $resolverClass) {
            $this->assertInstanceOf(ReportFieldResolver::class, app($resolverClass));
        }
    }
}
