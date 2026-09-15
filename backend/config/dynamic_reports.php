<?php

use App\Reporting\DynamicReports\Filters\BirthDateFilter;
use App\Reporting\DynamicReports\Filters\AppointmentStatusFilter;
use App\Reporting\DynamicReports\Filters\AppointmentSourceFilter;
use App\Reporting\DynamicReports\Filters\AppointmentWorkFilter;
use App\Reporting\DynamicReports\Filters\AppointmentSectionFilter;
use App\Reporting\DynamicReports\Filters\AppointmentSubsectionFilter;
use App\Reporting\DynamicReports\Filters\AppointmentServiceTagFilter;
use App\Reporting\DynamicReports\Filters\AppointmentAddonFilter;
use App\Reporting\DynamicReports\Filters\AppointmentAmountRangeFilter;
use App\Reporting\DynamicReports\Filters\FinancialStatusFilter;
use App\Reporting\DynamicReports\Filters\AppointmentDebtCompareFilter;
use App\Reporting\DynamicReports\Filters\AppointmentDepositCompareFilter;
use App\Reporting\DynamicReports\Filters\AppointmentPaymentMethodFilter;
use App\Reporting\DynamicReports\Filters\AppointmentPaymentAccountFilter;
use App\Reporting\DynamicReports\Filters\CityFilter;
use App\Reporting\DynamicReports\Filters\FileNumberFilter;
use App\Reporting\DynamicReports\Filters\FirstNameFilter;
use App\Reporting\DynamicReports\Filters\GenderFilter;
use App\Reporting\DynamicReports\Filters\LastNameFilter;
use App\Reporting\DynamicReports\Filters\NoReturnFilter;
use App\Reporting\DynamicReports\Filters\PhoneFilter;
use App\Reporting\DynamicReports\Filters\ReferrerFilter;
use App\Reporting\DynamicReports\Resolvers\AppointmentFieldResolver;
use App\Reporting\DynamicReports\Resolvers\FinancialFieldResolver;
use App\Reporting\DynamicReports\Resolvers\PatientFieldResolver;
use App\Reporting\DynamicReports\Resolvers\ReferrerFieldResolver;

return [
    // Canonical statuses used by the appointment screen and report builder.
    'appointment_statuses' => [
        'وقت داده شد',
        'آمد',
        'کنسل شد',
        'پاسخ نداد',
        'پیگیری',
        'انتقال داده شده',
    ],

    // Future searchable fields are registered here; the report Job does not change.
    'query_filters' => [
        FirstNameFilter::class,
        LastNameFilter::class,
        GenderFilter::class,
        PhoneFilter::class,
        FileNumberFilter::class,
        CityFilter::class,
        BirthDateFilter::class,
        ReferrerFilter::class,
        NoReturnFilter::class,
        AppointmentStatusFilter::class,
        AppointmentSourceFilter::class,
        AppointmentWorkFilter::class,
        AppointmentSectionFilter::class,
        AppointmentSubsectionFilter::class,
        AppointmentServiceTagFilter::class,
        AppointmentAddonFilter::class,
        AppointmentAmountRangeFilter::class,
        FinancialStatusFilter::class,
        AppointmentDebtCompareFilter::class,
        AppointmentDepositCompareFilter::class,
        AppointmentPaymentMethodFilter::class,
        AppointmentPaymentAccountFilter::class,
    ],

    // Adding an output field only requires a label and a dedicated resolver.
    'fields' => [
        'name' => 'نام', 'family' => 'نام خانوادگی', 'gender' => 'جنسیت', 'phone' => 'شماره تماس',
        'fileNo' => 'شماره پرونده', 'city' => 'شهر', 'birth' => 'تاریخ تولد', 'custseg' => 'تفکیک مشتری',
        'referrer' => 'معرف', 'noreturn' => 'عدم بازگشت', 'status' => 'وضعیت', 'source' => 'منبع',
        'work' => 'انجام کار', 'section2' => 'بخش', 'subsection' => 'زیر بخش', 'areas' => 'نواحی',
        'problem' => 'مشکل', 'extra' => 'جانبی', 'count' => 'تعداد', 'amount' => 'مبلغ',
        'income' => 'درآمد و هزینه', 'discount' => 'تخفیف', 'finstatus' => 'وضعیت مالی', 'debt' => 'بدهی',
        'deposit' => 'بیعانه', 'payment' => 'روش پرداخت', 'account' => 'حساب واریزی', 'doctor' => 'پزشک',
        'consultant' => 'مشاور', 'salary' => 'حقوق', 'overtime' => 'اضافه کار',
    ],

    'field_resolvers' => [
        PatientFieldResolver::class,
        AppointmentFieldResolver::class,
        FinancialFieldResolver::class,
        ReferrerFieldResolver::class,
    ],
];
